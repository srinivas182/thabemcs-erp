<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Requests;

use App\Domains\Platform\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class UpdateCompanyUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('manage-company-users') ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'role' => ['sometimes', 'required', Rule::enum(Role::class)],
            'is_active' => ['sometimes', 'required', 'boolean'],
            'job_title' => ['sometimes', 'nullable', 'string', 'max:120'],
        ];
    }
}
