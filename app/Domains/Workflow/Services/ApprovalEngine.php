<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Services;

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Models\ApprovalDelegation;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Delegation-of-authority approvals.
 *
 * Rules: steps are approved in order; each step needs a person holding the step's role (or their
 * active delegate); nobody approves their own request; one person cannot approve two steps of the
 * same request; a rejection ends the request.
 */
final class ApprovalEngine
{
    /**
     * Roles required for a policy and amount (excl. VAT).
     *
     * @return list<string>
     */
    public function stepsFor(string $policy, float $amount): array
    {
        /** @var list<array{up_to: int|null, steps: list<string>}> $bands */
        $bands = config("delegation_of_authority.policies.{$policy}") ?? throw new ApprovalException("Unknown approval policy [{$policy}].");

        foreach ($bands as $band) {
            if ($band['up_to'] === null || $amount <= $band['up_to']) {
                return $band['steps'];
            }
        }

        throw new ApprovalException("No approval band covers R{$amount} for [{$policy}].");
    }

    public function submit(Model&Approvable $approvable, string $policy, float $amount, User $requester): ApprovalRequest
    {
        $pending = ApprovalRequest::query()->where('approvable_type', $approvable->getMorphClass())
            ->where('approvable_id', $approvable->getKey())->where('status', 'pending')->exists();
        if ($pending) {
            throw new ApprovalException('This is already waiting for approval.');
        }

        $roles = $this->stepsFor($policy, $amount);
        $hours = (int) config('delegation_of_authority.escalate_after_hours', 48);

        $request = DB::transaction(function () use ($approvable, $policy, $amount, $requester, $roles, $hours): ApprovalRequest {
            $request = ApprovalRequest::query()->create([
                'policy' => $policy,
                'approvable_type' => $approvable->getMorphClass(),
                'approvable_id' => $approvable->getKey(),
                'amount' => $amount,
                'status' => 'pending',
                'current_step' => 1,
                'requested_by' => $requester->id,
            ]);

            foreach ($roles as $i => $role) {
                ApprovalStep::query()->create([
                    'approval_request_id' => $request->id,
                    'sequence' => $i + 1,
                    'role' => $role,
                    'decision' => 'pending',
                    'due_at' => $i === 0 ? now()->addHours($hours) : null,
                ]);
            }

            return $request;
        });

        $this->notifyApprovers($request, $approvable);

        return $request;
    }

    /**
     * Why this user cannot act on the request's current step, or null if they can.
     * Returns the person they would be acting for when it is a delegation.
     *
     * @return array{0: bool, 1: string|null, 2: User|null}
     */
    public function eligibility(ApprovalRequest $request, User $user): array
    {
        if ($request->status !== 'pending') {
            return [false, 'This request is no longer waiting for approval.', null];
        }
        if ($request->requested_by === $user->id) {
            return [false, 'You cannot approve your own request.', null];
        }

        $step = $this->currentStep($request);
        $alreadyActed = ApprovalStep::query()->where('approval_request_id', $request->id)
            ->where('decision', 'approved')->where('decided_by', $user->id)->exists();
        if ($alreadyActed) {
            return [false, 'You have already approved an earlier step of this request.', null];
        }

        if ($user->hasRole($step->role)) {
            return [true, null, null];
        }

        foreach ($this->principalsFor($user) as $principal) {
            if ($principal->hasRole($step->role) && $principal->id !== $request->requested_by) {
                return [true, null, $principal];
            }
        }

        return [false, 'This step needs a '.$this->roleLabel($step->role).'.', null];
    }

    public function decide(ApprovalRequest $request, User $user, bool $approve, ?string $comment = null): void
    {
        [$allowed, $reason, $principal] = $this->eligibility($request, $user);
        if (! $allowed) {
            throw new ApprovalException((string) $reason);
        }
        if (! $approve && ! $comment) {
            throw new ApprovalException('Give a reason for rejecting.');
        }

        /** @var Model&Approvable $approvable */
        $approvable = $request->approvable;

        DB::transaction(function () use ($request, $user, $approve, $comment, $principal, $approvable): void {
            $step = $this->currentStep($request);
            $step->forceFill([
                'decision' => $approve ? 'approved' : 'rejected',
                'decided_by' => $user->id,
                'on_behalf_of' => $principal?->id,
                'comment' => $comment,
                'decided_at' => now(),
            ])->save();

            if (! $approve) {
                $request->forceFill(['status' => 'rejected', 'completed_at' => now()])->save();
                $approvable->onApprovalRejected($request, $comment);

                return;
            }

            $next = ApprovalStep::query()->where('approval_request_id', $request->id)->where('sequence', $step->sequence + 1)->first();

            if ($next === null) {
                $request->forceFill(['status' => 'approved', 'completed_at' => now()])->save();
                $approvable->onApprovalGranted($request);

                return;
            }

            $next->forceFill(['due_at' => now()->addHours((int) config('delegation_of_authority.escalate_after_hours', 48))])->save();
            $request->forceFill(['current_step' => $next->sequence])->save();
        });

        activity('approvals')->causedBy($user)->performedOn($approvable)
            ->withProperties(['request' => $request->ulid, 'on_behalf_of' => $principal?->name, 'comment' => $comment])
            ->log($approve ? 'Approved' : 'Rejected');

        $request->refresh();
        $requester = $request->requester;

        if ($request->status === 'pending') {
            $this->notifyApprovers($request, $approvable);
        } else {
            $requester->notify(new SystemMessage(
                $approvable->approvalTitle().($request->status === 'approved' ? ' approved' : ' rejected'),
                $request->status === 'approved' ? 'All approvals are complete.' : "Rejected by {$user->name}: {$comment}",
                $approvable->approvalUrl(),
                $request->status === 'approved' ? 'info' : 'warning',
            ));
        }
    }

    /**
     * Pending requests whose current step this user may approve (their inbox).
     *
     * @return Collection<int, ApprovalRequest>
     */
    public function pendingFor(User $user): Collection
    {
        $roles = $user->getRoleNames()->all();
        foreach ($this->principalsFor($user) as $principal) {
            $roles = [...$roles, ...$principal->getRoleNames()->all()];
        }

        if ($roles === [] && ! $user->is_super_admin) {
            return collect();
        }

        return ApprovalRequest::query()
            ->with(['approvable', 'requester:id,name', 'steps'])
            ->where('status', 'pending')
            ->where('requested_by', '!=', $user->id)
            ->whereHas('steps', fn ($q) => $q->whereColumn('sequence', 'approval_requests.current_step')->whereIn('role', array_unique($roles)))
            ->oldest()
            ->get()
            ->filter(fn (ApprovalRequest $r): bool => $this->eligibility($r, $user)[0])
            ->values();
    }

    /**
     * Escalate steps waiting longer than the configured time to Directors and Company Admins.
     */
    public function escalateOverdue(int $companyId): int
    {
        $count = 0;
        ApprovalStep::query()->with('request.approvable')->where('decision', 'pending')->whereNull('escalated_at')
            ->where('due_at', '<', now())->get()
            ->each(function (ApprovalStep $step) use ($companyId, &$count): void {
                $request = $step->request;
                if ($request->status !== 'pending' || $request->current_step !== $step->sequence) {
                    return;
                }
                /** @var Model&Approvable $approvable */
                $approvable = $request->approvable;

                $this->usersWithRoles($companyId, [Role::Director->value, Role::CompanyAdmin->value], $request->requested_by)
                    ->each(fn (User $u) => $u->notify(new SystemMessage(
                        'Approval overdue: '.$approvable->approvalTitle(),
                        'Waiting more than '.config('delegation_of_authority.escalate_after_hours').' hours for a '.$this->roleLabel($step->role).'.',
                        $approvable->approvalUrl(),
                        'warning',
                    )));

                $step->forceFill(['escalated_at' => now()])->save();
                $count++;
            });

        return $count;
    }

    public function currentStep(ApprovalRequest $request): ApprovalStep
    {
        return ApprovalStep::query()->where('approval_request_id', $request->id)->where('sequence', $request->current_step)->firstOrFail();
    }

    /**
     * People who have delegated their approvals to this user today.
     *
     * @return Collection<int, User>
     */
    private function principalsFor(User $user): Collection
    {
        return ApprovalDelegation::query()->active()->where('delegate_id', $user->id)->with('user')->get()
            ->map(static fn (ApprovalDelegation $d): User => $d->user)
            ->filter(static fn (User $u): bool => $u->is_active)
            ->values();
    }

    private function notifyApprovers(ApprovalRequest $request, Model&Approvable $approvable): void
    {
        $step = $this->currentStep($request);
        $approvers = $this->usersWithRoles((int) $request->getAttribute('company_id'), [$step->role], $request->requested_by);

        $delegates = ApprovalDelegation::query()->active()->whereIn('user_id', $approvers->pluck('id'))->with('delegate')->get()
            ->map(static fn (ApprovalDelegation $d): User => $d->delegate);

        $approvers->merge($delegates)->unique('id')->each(fn (User $u) => $u->notify(new SystemMessage(
            'Approval needed: '.$approvable->approvalTitle(),
            'R'.number_format((float) $request->amount, 2, '.', ' ').' excl. VAT. Step '.$step->sequence.' of '.$request->steps()->count().'.',
            route('inbox'),
        )));
    }

    /**
     * @param  list<string>  $roles
     * @return Collection<int, User>
     */
    private function usersWithRoles(int $companyId, array $roles, int $exceptUserId): Collection
    {
        setPermissionsTeamId($companyId);

        return User::query()->where('company_id', $companyId)->where('is_active', true)->whereKeyNot($exceptUserId)->role($roles)->get();
    }

    private function roleLabel(string $role): string
    {
        return Role::tryFrom($role)?->label() ?? $role;
    }
}
