<?php

declare(strict_types=1);

namespace App\Domains\Cms\Services;

use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsFormSubmission;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Rentals\Models\Tenant;
use App\Domains\Sales\Models\Buyer;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * What happens when someone fills in a form on the website.
 *
 * The answers are kept, the people who should know are told, and where the form is an enquiry the
 * visitor becomes a buyer or tenant record so the sales team works it like any other lead.
 */
final class FormSubmissionService
{
    /**
     * @param  array<string, mixed>  $answers
     */
    public function submit(CmsForm $form, array $answers, bool $consented, ?string $ip, ?string $page): CmsFormSubmission
    {
        if (! $form->active) {
            throw new CmsException('This form is no longer taking messages.');
        }
        if (! $consented) {
            throw ValidationException::withMessages(['consented' => 'Please agree to us using your details to answer you.']);
        }

        $clean = $this->check($form, $answers);

        return DB::transaction(function () use ($form, $clean, $consented, $ip, $page): CmsFormSubmission {
            $submission = CmsFormSubmission::query()->create([
                'cms_form_id' => $form->id,
                'answers' => $clean,
                'name' => $this->pick($clean, ['name', 'full_name', 'your_name']),
                'email' => $this->pick($clean, ['email', 'email_address']),
                'phone' => $this->pick($clean, ['phone', 'telephone', 'mobile', 'contact_number']),
                'consented' => $consented,
                'ip_address' => $ip,
                'page' => $page,
                'status' => 'new',
            ]);

            $this->createEnquiry($form, $submission);
            $this->tellPeople($form, $submission);

            return $submission;
        });
    }

    /**
     * An enquiry becomes a real record, so it is worked like any other lead rather than sitting in an inbox.
     */
    private function createEnquiry(CmsForm $form, CmsFormSubmission $submission): void
    {
        if ($submission->name === null || $form->creates === 'none') {
            return;
        }

        if ($form->creates === 'buyer') {
            $buyer = Buyer::query()->create([
                'name' => $submission->name, 'entity_type' => 'individual', 'email' => $submission->email,
                'phone' => $submission->phone, 'status' => 'enquiry', 'source' => 'Website',
                'notes' => $this->summary($submission),
            ]);
            $submission->forceFill(['buyer_id' => $buyer->id])->save();
        }

        if ($form->creates === 'tenant') {
            $tenant = Tenant::query()->create([
                'name' => $submission->name, 'entity_type' => 'individual', 'email' => $submission->email,
                'phone' => $submission->phone, 'status' => 'applicant', 'notes' => $this->summary($submission),
            ]);
            $submission->forceFill(['tenant_id' => $tenant->id])->save();
        }
    }

    private function tellPeople(CmsForm $form, CmsFormSubmission $submission): void
    {
        $recipients = $form->recipients ?? [];
        if ($recipients === []) {
            return;
        }

        User::query()->whereIn('ulid', $recipients)->where('is_active', true)->each(
            fn (User $person) => $person->notify(new SystemMessage(
                "Website enquiry: {$form->name}",
                trim(($submission->name ?? 'Someone').' - '.$this->summary($submission)),
                route('cms.submissions'),
            )),
        );
    }

    /**
     * @param  array<string, mixed>  $answers
     * @return array<string, mixed>
     */
    private function check(CmsForm $form, array $answers): array
    {
        $clean = [];
        $errors = [];

        foreach ($form->fields as $field) {
            $value = $answers[$field['name']] ?? null;
            $given = is_string($value) ? trim($value) : $value;

            if (($field['required'] ?? false) && ($given === null || $given === '')) {
                $errors["answers.{$field['name']}"] = "{$field['label']} is needed.";

                continue;
            }
            if ($given === null || $given === '') {
                continue;
            }
            if ($field['type'] === 'email' && ! filter_var((string) $given, FILTER_VALIDATE_EMAIL)) {
                $errors["answers.{$field['name']}"] = 'That email address does not look right.';

                continue;
            }
            if ($field['type'] === 'select' && ! in_array($given, $field['options'] ?? [], true)) {
                $errors["answers.{$field['name']}"] = "Choose one of the options for {$field['label']}.";

                continue;
            }
            $clean[$field['name']] = is_string($given) ? mb_substr($given, 0, 2000) : $given;
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }

        return $clean;
    }

    /**
     * @param  array<string, mixed>  $answers
     * @param  list<string>  $names
     */
    private function pick(array $answers, array $names): ?string
    {
        foreach ($names as $name) {
            if (isset($answers[$name]) && is_string($answers[$name]) && $answers[$name] !== '') {
                return mb_substr($answers[$name], 0, 190);
            }
        }

        return null;
    }

    private function summary(CmsFormSubmission $submission): string
    {
        $parts = [];
        foreach ($submission->answers as $key => $value) {
            if (is_scalar($value)) {
                $parts[] = str_replace('_', ' ', (string) $key).': '.$value;
            }
        }

        return mb_substr(implode('; ', $parts), 0, 1500);
    }
}
