<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Services;

use App\Domains\Compliance\Models\DataSubjectRequest;
use App\Domains\Workforce\Models\CrewAttendance;
use App\Domains\Workforce\Models\Employee;
use App\Domains\Workforce\Models\EmployeeAllowance;
use App\Domains\Workforce\Models\EmployeeDocument;
use App\Domains\Workforce\Models\LeaveRequest;
use App\Domains\Workforce\Models\OvertimeEntry;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Spatie\Activitylog\Models\Activity;

/**
 * Requests from people to access, correct or delete their personal information, with a due date
 * and an export of what the system holds about an employee or a user.
 */
final class DataSubjectService
{
    /**
     * @param  array{requester_name: string, requester_email?: string|null, type: string, subject_type: string, subject_id?: int|null, details?: string|null, received_on: string}  $data
     */
    public function open(array $data): DataSubjectRequest
    {
        return DB::transaction(fn (): DataSubjectRequest => DataSubjectRequest::query()->create([
            ...$data,
            'number' => (int) DataSubjectRequest::query()->lockForUpdate()->max('number') + 1,
            'due_on' => Carbon::parse($data['received_on'])->addDays((int) config('popia.request_days', 30))->toDateString(),
            'status' => 'open',
        ]));
    }

    /**
     * Everything held about the person, as a structured array (sent to them as JSON).
     *
     * @return array<string, mixed>
     */
    public function export(DataSubjectRequest $request): array
    {
        return match ($request->subject_type) {
            'employee' => $this->employee(Employee::query()->findOrFail($request->subject_id)),
            'user' => $this->user(User::query()->findOrFail($request->subject_id)),
            default => ['note' => 'Collect this person\'s information manually; they are not an employee or system user.'],
        };
    }

    /**
     * @return array<string, mixed>
     */
    private function employee(Employee $e): array
    {
        return [
            'person' => ['employee_number' => $e->employee_number, 'name' => $e->name(), 'id_number' => $e->id_number, 'job_title' => $e->job_title,
                'employment_type' => $e->employment_type, 'start_date' => $e->start_date->toDateString(), 'end_date' => $e->end_date?->toDateString(),
                'phone' => $e->phone, 'emergency_contact' => $e->emergency_contact],
            'site_allocations' => $e->allocations()->with('project:id,name')->get()->map(static fn ($a) => ['project' => $a->project->name, 'from' => $a->from_date->toDateString(), 'to' => $a->to_date?->toDateString()])->all(),
            'leave' => LeaveRequest::query()->where('employee_id', $e->id)->get(['type', 'from_date', 'to_date', 'days', 'status'])->toArray(),
            'overtime' => OvertimeEntry::query()->where('employee_id', $e->id)->get(['worked_on', 'hours', 'rate_multiplier'])->toArray(),
            'attendance' => CrewAttendance::query()->where('employee_id', $e->id)->get(['worked_on', 'time_in', 'time_out', 'status'])->toArray(),
            'allowances' => EmployeeAllowance::query()->where('employee_id', $e->id)->get(['type', 'amount', 'frequency', 'from_date', 'to_date'])->toArray(),
            'documents' => EmployeeDocument::query()->where('employee_id', $e->id)->get(['type', 'expires_on', 'created_at'])->toArray(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function user(User $u): array
    {
        return [
            'person' => ['name' => $u->name, 'email' => $u->email, 'job_title' => $u->getAttribute('job_title'), 'created_at' => $u->created_at?->toIso8601String()],
            'activity' => Activity::query()->where('causer_type', $u->getMorphClass())->where('causer_id', $u->id)->latest()->limit(1000)
                ->get(['description', 'log_name', 'created_at'])->toArray(),
        ];
    }
}
