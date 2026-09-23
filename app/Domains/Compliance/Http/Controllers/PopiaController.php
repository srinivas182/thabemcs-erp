<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Http\Controllers;

use App\Domains\Compliance\Models\DataSubjectRequest;
use App\Domains\Compliance\Services\DataSubjectService;
use App\Domains\Compliance\Services\RetentionService;
use App\Domains\Workforce\Models\Employee;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PopiaController
{
    public function __construct(
        private readonly RetentionService $retention,
        private readonly DataSubjectService $requests,
        private readonly CurrentCompany $context,
    ) {}

    public function index(): Response
    {
        Gate::authorize('manage-popia');
        /** @var array<string, array{label: string, default_months: int, minimum_months: int}> $defaults */
        $defaults = config('popia.retention');
        $today = Carbon::today();

        return Inertia::render('settings/popia', [
            'officer' => config('popia.information_officer'),
            'register' => config('popia.register'),
            'rules' => array_values(array_map(static fn (string $record, $rule): array => [
                'record' => $record, 'label' => $defaults[$record]['label'], 'months' => $rule->keep_months, 'minimum' => $defaults[$record]['minimum_months'],
                'lastRun' => $rule->last_run_at?->toIso8601String(), 'lastCount' => $rule->last_run_count,
            ], array_keys($this->retention->rules()), $this->retention->rules())),
            'requests' => DataSubjectRequest::query()->with('handler:id,name')->orderByRaw("case status when 'open' then 0 when 'in_progress' then 1 else 2 end")->orderBy('due_on')->get()
                ->map(static fn (DataSubjectRequest $r): array => [
                    'id' => $r->ulid, 'number' => $r->number, 'name' => $r->requester_name, 'email' => $r->requester_email, 'type' => $r->type,
                    'subjectType' => $r->subject_type, 'hasSubject' => $r->subject_id !== null, 'details' => $r->details, 'received' => $r->received_on->toDateString(),
                    'due' => $r->due_on->toDateString(), 'overdue' => ! in_array($r->status, ['completed', 'refused'], true) && $r->due_on->lessThan($today),
                    'status' => $r->status, 'outcome' => $r->outcome, 'handler' => $r->handler?->name,
                ]),
        ]);
    }

    public function updateRule(Request $request, string $record): RedirectResponse
    {
        Gate::authorize('manage-popia');
        $defaults = (array) config("popia.retention.{$record}");
        abort_if($defaults === [], 404);
        $data = $request->validate(['months' => ['required', 'integer', 'min:'.$defaults['minimum_months'], 'max:240']], [
            'months.min' => 'Other laws require keeping these records at least :min months.',
        ]);
        $this->retention->rules()[$record]->update(['keep_months' => (int) $data['months']]);

        return back()->with('success', 'Retention period saved.');
    }

    public function runCleanup(): RedirectResponse
    {
        Gate::authorize('manage-popia');
        $done = $this->retention->runForCurrentCompany();

        return back()->with('success', 'Clean-up finished: '.array_sum($done).' records removed or anonymised.');
    }

    public function storeRequest(Request $request): RedirectResponse
    {
        Gate::authorize('manage-popia');
        $data = $request->validate([
            'requester_name' => ['required', 'string', 'max:160'], 'requester_email' => ['nullable', 'email', 'max:190'],
            'type' => ['required', 'in:access,correction,deletion,objection'], 'subject_type' => ['required', 'in:employee,user,investor,other'],
            'subject' => ['nullable', 'string'], 'details' => ['nullable', 'string', 'max:5000'], 'received_on' => ['required', 'date', 'before_or_equal:today'],
        ]);

        $subjectId = match ($data['subject_type']) {
            'employee' => isset($data['subject']) ? Employee::query()->where('ulid', $data['subject'])->value('id') : null,
            'user' => isset($data['subject']) ? User::query()->where('ulid', $data['subject'])->where('company_id', $this->context->id())->value('id') : null,
            default => null,
        };

        $r = $this->requests->open([
            'requester_name' => (string) $data['requester_name'], 'requester_email' => $data['requester_email'] ?? null, 'type' => (string) $data['type'],
            'subject_type' => (string) $data['subject_type'], 'subject_id' => $subjectId === null ? null : (int) $subjectId,
            'details' => $data['details'] ?? null, 'received_on' => (string) $data['received_on'],
        ]);

        return back()->with('success', "Request {$r->number} recorded. Respond by ".$r->due_on->format('j F Y').'.');
    }

    public function updateRequest(Request $request, DataSubjectRequest $dataRequest): RedirectResponse
    {
        Gate::authorize('manage-popia');
        $data = $request->validate(['status' => ['required', 'in:open,in_progress,completed,refused'], 'outcome' => ['nullable', 'required_if:status,completed,refused', 'string', 'max:5000']],
            ['outcome.required_if' => 'Record what was done, or why the request was refused.']);
        /** @var User $user */
        $user = $request->user();
        $dataRequest->forceFill([...$data, 'handled_by' => $user->id, 'completed_at' => in_array($data['status'], ['completed', 'refused'], true) ? now() : null])->save();

        return back()->with('success', 'Request updated.');
    }

    public function export(Request $request, DataSubjectRequest $dataRequest): JsonResponse
    {
        Gate::authorize('manage-popia');
        activity('popia')->causedBy($request->user())->performedOn($dataRequest)->log('Personal information exported for a data subject request');

        return response()->json($this->requests->export($dataRequest), 200, [
            'Content-Disposition' => 'attachment; filename="personal-information-request-'.$dataRequest->number.'.json"',
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }
}
