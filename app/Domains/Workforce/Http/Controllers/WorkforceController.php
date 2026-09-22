<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Http\Controllers;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Projects\Models\Project;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmployeeAllocation;
use App\Domains\Workforce\Models\EmployeeAllowance;
use App\Domains\Workforce\Models\EmployeeDocument;
use App\Domains\Workforce\Models\LeaveRequest;
use App\Domains\Workforce\Models\OvertimeEntry;
use App\Domains\Workforce\Services\WorkforceService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class WorkforceController
{
    public function __construct(private readonly WorkforceService $workforce, private readonly CurrentCompany $context) {}

    public function index(Request $request): Response
    {
        Gate::authorize('manage-workforce');
        $today = Carbon::today()->toDateString();
        $term = trim($request->string('q')->toString());

        return Inertia::render('workforce/index', [
            'employees' => Employee::query()
                ->with(['allocations' => fn ($q) => $q->whereDate('from_date', '<=', $today)->where(fn ($w) => $w->whereNull('to_date')->orWhereDate('to_date', '>=', $today))->with('project:id,name')])
                ->when($term !== '', fn ($q) => $q->where(fn ($w) => $w->where('first_name', 'like', "%{$term}%")->orWhere('last_name', 'like', "%{$term}%")->orWhere('employee_number', 'like', "%{$term}%")))
                ->orderBy('status')->orderBy('last_name')->paginate(50)->withQueryString()
                ->through(static fn (Employee $e): array => [
                    'id' => $e->ulid, 'number' => $e->employee_number, 'name' => $e->name(), 'jobTitle' => $e->job_title,
                    'type' => $e->employment_type, 'status' => $e->status, 'site' => $e->allocations->first()?->project->name,
                ]),
            'q' => $term,
            'pendingLeave' => LeaveRequest::query()->where('status', 'pending')->count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $data = $request->validate([
            'employee_number' => ['required', 'string', 'max:20', Rule::unique('employees')->where('company_id', $this->context->id())],
            'first_name' => ['required', 'string', 'max:80'],
            'last_name' => ['required', 'string', 'max:80'],
            'id_number' => ['nullable', 'string', 'max:20'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'employment_type' => ['required', 'in:permanent,fixed_term,temporary'],
            'start_date' => ['required', 'date'],
            'end_date' => ['nullable', 'required_if:employment_type,fixed_term', 'date', 'after:start_date'],
            'phone' => ['nullable', 'string', 'max:20'],
            'days_per_week' => ['required', 'integer', 'between:1,6'],
        ], ['end_date.required_if' => 'A fixed-term contract needs an end date.']);

        $employee = Employee::query()->create($data);

        return redirect()->route('workforce.show', $employee)->with('success', "{$employee->name()} added.");
    }

    public function show(Employee $employee): Response
    {
        Gate::authorize('manage-workforce');
        $employee->load(['allocations.project:id,name', 'leave', 'overtime.project:id,name']);

        return Inertia::render('workforce/employee', [
            'employee' => [
                'id' => $employee->ulid, 'number' => $employee->employee_number, 'name' => $employee->name(), 'idNumber' => $employee->maskedIdNumber(),
                'jobTitle' => $employee->job_title, 'type' => $employee->employment_type, 'start' => $employee->start_date->toDateString(),
                'end' => $employee->end_date?->toDateString(), 'phone' => $employee->phone, 'daysPerWeek' => $employee->days_per_week, 'status' => $employee->status,
            ],
            'balances' => $this->workforce->balances($employee),
            'allocations' => $employee->allocations->map(static fn (EmployeeAllocation $a): array => ['id' => $a->id, 'project' => $a->project->name, 'from' => $a->from_date->toDateString(), 'to' => $a->to_date?->toDateString(), 'role' => $a->role_on_site]),
            'leave' => $employee->leave->map(static fn (LeaveRequest $l): array => ['id' => $l->id, 'type' => $l->type, 'from' => $l->from_date->toDateString(), 'to' => $l->to_date->toDateString(), 'days' => (float) $l->days, 'status' => $l->status, 'notes' => $l->notes]),
            'overtime' => $employee->overtime->take(30)->map(static fn (OvertimeEntry $o): array => ['id' => $o->id, 'date' => $o->worked_on->toDateString(), 'hours' => (float) $o->hours, 'multiplier' => (float) $o->rate_multiplier, 'project' => $o->project?->name, 'reason' => $o->reason])->values(),
            'projects' => Project::query()->where('status', 'active')->orderBy('name')->get(['ulid', 'name'])->map(static fn (Project $p): array => ['key' => $p->ulid, 'label' => $p->name])->values(),
            'allowances' => EmployeeAllowance::query()->where('employee_id', $employee->id)->orderByDesc('from_date')->get()
                ->map(static fn ($a): array => ['id' => $a->id, 'type' => $a->type, 'amount' => (float) $a->amount, 'frequency' => $a->frequency, 'from' => $a->from_date->toDateString(), 'to' => $a->to_date?->toDateString(), 'notes' => $a->notes])->values(),
            'documents' => EmployeeDocument::query()->with(['document' => fn ($q) => $q->with('latestVersion')])->where('employee_id', $employee->id)->latest('id')->get()
                ->map(static fn ($d): array => [
                    'id' => $d->id, 'type' => $d->type, 'expires' => $d->expires_on?->toDateString(), 'expired' => $d->expires_on !== null && $d->expires_on->isPast(),
                    'download' => $d->document && $d->document->latestVersion ? "/documents/{$d->document->ulid}/versions/{$d->document->latestVersion->id}/download" : null,
                ])->values(),
            'leaveTypes' => collect((array) config('workforce.leave'))->map(static fn (array $r, string $k): array => ['key' => $k, 'label' => (string) $r['label']])->values(),
        ]);
    }

    public function allocate(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $data = $request->validate([
            'project' => ['required', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
            'from_date' => ['required', 'date'], 'role_on_site' => ['nullable', 'string', 'max:120'],
        ]);
        $from = Carbon::parse((string) $data['from_date']);

        // Close the current allocation the day before the new one starts.
        EmployeeAllocation::query()->where('employee_id', $employee->id)->whereNull('to_date')->whereDate('from_date', '<', $from)
            ->update(['to_date' => $from->copy()->subDay()->toDateString()]);
        EmployeeAllocation::query()->create([
            'employee_id' => $employee->id, 'project_id' => Project::query()->where('ulid', $data['project'])->value('id'),
            'from_date' => $from->toDateString(), 'role_on_site' => $data['role_on_site'] ?? null,
        ]);

        return back()->with('success', 'Site allocation saved.');
    }

    public function requestLeave(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys((array) config('workforce.leave')))],
            'from_date' => ['required', 'date'], 'to_date' => ['required', 'date', 'after_or_equal:from_date'], 'notes' => ['nullable', 'string', 'max:255'],
        ]);
        $leave = $this->workforce->requestLeave($employee, (string) $data['type'], Carbon::parse((string) $data['from_date']), Carbon::parse((string) $data['to_date']), $data['notes'] ?? null);

        return back()->with('success', "{$leave->days} working days of leave recorded, waiting for approval.");
    }

    public function decideLeave(Request $request, LeaveRequest $leave): RedirectResponse
    {
        Gate::authorize('approve-leave');
        $data = $request->validate(['decision' => ['required', 'in:approved,declined']]);
        /** @var User $user */
        $user = $request->user();
        $leave->forceFill(['status' => $data['decision'], 'decided_by' => $user->id])->save();

        return back()->with('success', 'Leave '.$data['decision'].'.');
    }

    public function overtime(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $data = $request->validate([
            'worked_on' => ['required', 'date', 'before_or_equal:today'], 'hours' => ['required', 'numeric', 'gt:0', 'max:12'],
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())], 'reason' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $entry = $this->workforce->recordOvertime(
            $employee, Carbon::parse((string) $data['worked_on']), (float) $data['hours'],
            isset($data['project']) ? Project::query()->where('ulid', $data['project'])->value('id') : null, $data['reason'] ?? null, $user,
        );

        return back()->with('success', "{$entry->hours} hours overtime at {$entry->rate_multiplier}x recorded.");
    }

    public function allowance(Request $request, Employee $employee): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $data = $request->validate([
            'type' => ['required', 'in:travel,site,tool,meal,housing,cellphone,other'], 'amount' => ['required', 'numeric', 'gt:0', 'max:100000'],
            'frequency' => ['required', 'in:day,month,once'], 'from_date' => ['required', 'date'], 'to_date' => ['nullable', 'date', 'after_or_equal:from_date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        EmployeeAllowance::query()->create([...$data, 'employee_id' => $employee->id]);

        return back()->with('success', 'Allowance added. It is included in the payroll inputs export.');
    }

    public function endAllowance(EmployeeAllowance $allowance): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $allowance->update(['to_date' => now()->toDateString()]);

        return back()->with('success', 'Allowance ended today.');
    }

    public function document(Request $request, Employee $employee, DocumentService $documents): RedirectResponse
    {
        Gate::authorize('manage-workforce');
        $data = $request->validate([
            'type' => ['required', 'in:contract,id_copy,qualification,medical,induction,warning,other'],
            'expires_on' => ['nullable', 'date'], 'file' => ['required', 'file', 'mimes:pdf,jpg,jpeg,png,docx', 'max:10240'],
        ]);
        /** @var User $user */
        $user = $request->user();

        // Employment records are restricted: only Company Admins and Directors can open them.
        $document = $documents->upload($request->file('file'), [
            'folder' => "HR/{$employee->employee_number}", 'title' => ucfirst(str_replace('_', ' ', (string) $data['type'])).": {$employee->name()}",
            'category' => DocumentCategory::Other, 'restricted_to_roles' => [Role::CompanyAdmin->value],
        ], $user);
        EmployeeDocument::query()->create(['employee_id' => $employee->id, 'type' => $data['type'], 'document_id' => $document->id, 'expires_on' => $data['expires_on'] ?? null]);

        return back()->with('success', 'Document saved to the employee file.');
    }
}
