<?php

declare(strict_types=1);

namespace App\Domains\Forms\Services;

use App\Domains\Forms\Models\FormSubmission;
use App\Domains\Forms\Models\FormTemplate;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Projects\Models\Project;
use App\Domains\Site\Services\SiteCaptureService;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Custom checklists: templates built in the browser, filled in on the site app.
 */
final class FormService
{
    public const array TYPES = ['text', 'number', 'yesno', 'passfail', 'choice', 'photo', 'date'];

    public function __construct(private readonly SiteCaptureService $capture) {}

    /**
     * Clean and check the fields from the builder.
     *
     * @param  array<int, mixed>  $fields
     * @return list<array{id: string, label: string, type: string, required: bool, options?: list<string>}>
     */
    public function normaliseFields(array $fields): array
    {
        $clean = [];
        foreach ($fields as $i => $f) {
            if (! is_array($f) || trim((string) ($f['label'] ?? '')) === '' || ! in_array($f['type'] ?? null, self::TYPES, true)) {
                throw ValidationException::withMessages(['fields' => 'Question '.($i + 1).' needs a label and a valid type.']);
            }
            $field = [
                'id' => preg_match('/^[a-z0-9]{6,16}$/', (string) ($f['id'] ?? '')) ? (string) $f['id'] : Str::lower(Str::random(8)),
                'label' => mb_substr(trim((string) $f['label']), 0, 200),
                'type' => (string) $f['type'],
                'required' => (bool) ($f['required'] ?? false),
            ];
            if ($field['type'] === 'choice') {
                $options = array_values(array_filter(array_map(static fn ($o): string => mb_substr(trim((string) $o), 0, 80), (array) ($f['options'] ?? []))));
                if (count($options) < 2) {
                    throw ValidationException::withMessages(['fields' => "\"{$field['label']}\" needs at least two choices."]);
                }
                $field['options'] = $options;
            }
            $clean[] = $field;
        }
        if ($clean === []) {
            throw ValidationException::withMessages(['fields' => 'Add at least one question.']);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  array<string, UploadedFile>  $photos  keyed by field id
     */
    public function submit(Project $project, FormTemplate $template, string $clientId, array $answers, array $photos, User $by): FormSubmission
    {
        if ($existing = FormSubmission::query()->where('client_id', $clientId)->first()) {
            return $existing;
        }

        $clean = [];
        $errors = [];
        $passed = null;
        foreach ($template->fields as $field) {
            $id = $field['id'];
            $value = $answers[$id] ?? null;
            if ($field['type'] === 'photo') {
                $value = isset($photos[$id]) ? 'photo' : null;
            }
            $empty = $value === null || $value === '';
            if ($field['required'] && $empty) {
                $errors[$id] = "\"{$field['label']}\" is required.";

                continue;
            }
            if ($empty) {
                continue;
            }
            $ok = match ($field['type']) {
                'number' => is_numeric($value),
                'yesno' => in_array($value, ['yes', 'no'], true),
                'passfail' => in_array($value, ['pass', 'fail', 'na'], true),
                'choice' => in_array($value, $field['options'] ?? [], true),
                'date' => strtotime((string) $value) !== false,
                default => is_scalar($value) && mb_strlen((string) $value) <= 2000,
            };
            if (! $ok) {
                $errors[$id] = "\"{$field['label']}\" has an invalid answer.";

                continue;
            }
            if ($field['type'] === 'passfail' && $value !== 'na') {
                $passed = ($passed ?? true) && $value === 'pass';
            }
            $clean[$id] = $field['type'] === 'number' ? (float) $value : $value;
        }
        if ($errors !== []) {
            throw ValidationException::withMessages(['answers' => array_values($errors)]);
        }

        $submission = FormSubmission::query()->create([
            'project_id' => $project->id, 'form_template_id' => $template->id, 'template_version' => $template->version, 'client_id' => $clientId,
            'fields' => $template->fields, 'answers' => $clean, 'passed' => $passed, 'submitted_at' => now(), 'submitted_by' => $by->id,
        ]);

        foreach ($photos as $fieldId => $file) {
            try {
                [$photo] = $this->capture->photo($project, ['client_id' => (string) Str::uuid(), 'captured_at' => now()->toIso8601String(), 'caption' => "{$template->name}: {$fieldId}"], $file, $by, $submission);
                $clean[$fieldId] = ['photo' => $photo->id];
            } catch (QuotaExceededException) {
                $clean[$fieldId] = 'photo not stored (storage full)';
            }
        }
        $submission->forceFill(['answers' => $clean])->save();

        return $submission;
    }
}
