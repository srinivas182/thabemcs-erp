<?php

declare(strict_types=1);

namespace App\Domains\Safety\Http\Controllers;

use App\Domains\Documents\Enums\DocumentCategory;
use App\Domains\Documents\Services\DocumentService;
use App\Domains\Projects\Models\Project;
use App\Domains\Safety\Models\SafetyAppointment;
use App\Domains\Safety\Models\SafetyFileItem;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/**
 * Legal H&S appointments and the project's safety file checklist.
 */
final class SafetyComplianceController
{
    public function __construct(private readonly DocumentService $documents) {}

    /**
     * @return array{appointments: list<array<string, mixed>>, gaps: list<string>, file: list<array<string, mixed>>, fileComplete: int, fileTotal: int}
     */
    public static function summary(Project $project): array
    {
        /** @var array<string, array{label: string, reference: string, required: bool, competency: bool}> $types */
        $types = config('safety_compliance.appointments');
        $current = SafetyAppointment::query()->where('project_id', $project->id)->whereNull('ended_on')->orderBy('type')->get();
        $today = Carbon::today();

        $appointments = array_values($current->map(static fn (SafetyAppointment $a): array => [
            'id' => $a->ulid, 'type' => $a->type, 'label' => $types[$a->type]['label'] ?? $a->type, 'reference' => $types[$a->type]['reference'] ?? null,
            'name' => $a->appointee_name, 'appointedOn' => $a->appointed_on->toDateString(), 'expires' => $a->competency_expires_on?->toDateString(),
            'expired' => $a->competency_expires_on !== null && $a->competency_expires_on->lessThan($today),
            'expiring' => $a->competency_expires_on !== null && $a->competency_expires_on->between($today, $today->copy()->addDays(30)),
        ])->all());

        $gaps = [];
        foreach ($types as $key => $t) {
            if ($t['required'] && ! $current->contains('type', $key)) {
                $gaps[] = $t['label'];
            }
        }

        /** @var array<string, string> $items */
        $items = config('safety_compliance.safety_file');
        $saved = SafetyFileItem::query()->where('project_id', $project->id)->get()->keyBy('item');
        $file = [];
        foreach ($items as $key => $label) {
            $row = $saved->get($key);
            $file[] = ['item' => $key, 'label' => $label, 'status' => $row->status ?? 'missing', 'reviewDue' => $row?->review_due_on?->toDateString(), 'notes' => $row?->notes, 'hasDocument' => $row?->document_id !== null];
        }

        return [
            'appointments' => $appointments, 'gaps' => $gaps, 'file' => $file,
            'fileComplete' => count(array_filter($file, static fn (array $f): bool => $f['status'] !== 'missing')), 'fileTotal' => count($file),
        ];
    }

    public function appoint(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-safety');
        $types = (array) config('safety_compliance.appointments');
        $data = $request->validate([
            'type' => ['required', Rule::in(array_keys($types))],
            'appointee_name' => ['required', 'string', 'max:160'],
            'appointed_on' => ['required', 'date', 'before_or_equal:today'],
            'competency_expires_on' => ['nullable', 'date', 'after:appointed_on'],
            'file' => ['nullable', 'file', 'mimes:pdf,jpg,jpeg,png', 'max:10240'],
        ]);
        /** @var User $user */
        $user = $request->user();

        $documentId = $request->hasFile('file') ? $this->documents->upload($request->file('file'), [
            'project_id' => $project->id, 'folder' => 'Health and Safety/Appointments',
            'title' => ($types[$data['type']]['label'] ?? 'Appointment').": {$data['appointee_name']}", 'category' => DocumentCategory::Compliance,
        ], $user)->id : null;

        SafetyAppointment::query()->create([
            'project_id' => $project->id, 'type' => $data['type'], 'appointee_name' => $data['appointee_name'],
            'appointed_on' => $data['appointed_on'], 'competency_expires_on' => $data['competency_expires_on'] ?? null,
            'document_id' => $documentId, 'recorded_by' => $user->id,
        ]);

        return back()->with('success', 'Appointment recorded.');
    }

    public function endAppointment(SafetyAppointment $appointment): RedirectResponse
    {
        Gate::authorize('manage-safety');
        $appointment->update(['ended_on' => now()->toDateString()]);

        return back()->with('success', 'Appointment ended. Appoint a replacement if the role is still needed.');
    }

    public function fileItem(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-safety');
        $data = $request->validate([
            'item' => ['required', Rule::in(array_keys((array) config('safety_compliance.safety_file')))],
            'status' => ['required', 'in:missing,in_place,not_applicable'],
            'review_due_on' => ['nullable', 'date'],
            'notes' => ['nullable', 'string', 'max:255'],
        ]);
        /** @var User $user */
        $user = $request->user();
        SafetyFileItem::query()->updateOrCreate(
            ['project_id' => $project->id, 'item' => $data['item']],
            ['status' => $data['status'], 'review_due_on' => $data['review_due_on'] ?? null, 'notes' => $data['notes'] ?? null, 'updated_by' => $user->id],
        );

        return back();
    }
}
