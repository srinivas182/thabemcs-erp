<?php

declare(strict_types=1);

namespace App\Domains\Workflow\Http\Controllers;

use App\Domains\Platform\Enums\Role;
use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Models\ApprovalDelegation;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * One place to approve everything: requisitions, purchase orders and (later) variations and payments.
 */
final class InboxController
{
    public function __construct(private readonly ApprovalEngine $engine, private readonly CurrentCompany $context) {}

    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $describe = function (ApprovalRequest $r): array {
            /** @var Model&Approvable $item */
            $item = $r->approvable;
            $step = $r->steps->firstWhere('sequence', $r->current_step);

            return [
                'id' => $r->ulid, 'title' => $item->approvalTitle(), 'url' => $item->approvalUrl(), 'amount' => (float) $r->amount,
                'policy' => $r->policy, 'requester' => $r->requester->name, 'submittedAt' => $r->created_at->toIso8601String(),
                'step' => $r->current_step, 'steps' => $r->steps->count(), 'status' => $r->status,
                'stepRole' => $step ? (Role::tryFrom($step->role)?->label() ?? $step->role) : null,
                'overdue' => $step?->due_at?->isPast() ?? false,
            ];
        };

        return Inertia::render('inbox', [
            'waiting' => $this->engine->pendingFor($user)->map($describe)->values(),
            'mine' => ApprovalRequest::query()->with(['approvable', 'requester:id,name', 'steps'])->where('requested_by', $user->id)
                ->latest('id')->limit(20)->get()->map($describe)->values(),
            'delegations' => ApprovalDelegation::query()->with('delegate:id,name')->where('user_id', $user->id)
                ->whereDate('ends_on', '>=', now('Africa/Johannesburg')->toDateString())->get()
                ->map(static fn (ApprovalDelegation $d): array => ['id' => $d->id, 'delegate' => $d->delegate->name, 'from' => $d->starts_on->toDateString(), 'to' => $d->ends_on->toDateString()]),
        ]);
    }

    public function decide(Request $request, ApprovalRequest $approval): RedirectResponse
    {
        $data = $request->validate(['decision' => ['required', 'in:approve,reject'], 'comment' => ['nullable', 'string', 'max:2000']]);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->engine->decide($approval, $user, $data['decision'] === 'approve', $data['comment'] ?? null);
        } catch (ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', $data['decision'] === 'approve' ? 'Approved.' : 'Rejected. The requester has been told why.');
    }

    public function delegate(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'delegate' => ['required', 'string', Rule::exists('users', 'ulid')->where('company_id', $this->context->id())->where('is_active', true)],
            'starts_on' => ['required', 'date', 'after_or_equal:today'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on', 'before:'.now()->addDays(91)->toDateString()],
            'reason' => ['nullable', 'string', 'max:255'],
        ], ['ends_on.before' => 'Delegations can last up to 90 days.']);

        /** @var User $user */
        $user = $request->user();
        $delegate = User::query()->where('ulid', $data['delegate'])->firstOrFail();
        abort_if($delegate->is($user), 422);

        ApprovalDelegation::query()->create([
            'user_id' => $user->id, 'delegate_id' => $delegate->id, 'starts_on' => $data['starts_on'], 'ends_on' => $data['ends_on'], 'reason' => $data['reason'] ?? null,
        ]);

        activity('approvals')->causedBy($user)->performedOn($delegate)->log('Approvals delegated');

        return back()->with('success', "{$delegate->name} can approve on your behalf from ".$data['starts_on'].' to '.$data['ends_on'].'.');
    }

    public function revoke(Request $request, ApprovalDelegation $delegation): RedirectResponse
    {
        abort_unless($delegation->user_id === $request->user()?->id, 404);
        $delegation->delete();

        return back()->with('success', 'Delegation ended.');
    }
}
