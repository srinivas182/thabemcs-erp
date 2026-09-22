<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Requests;

use App\Domains\Platform\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

final class InviteUserRequest extends FormRequest
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
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
            'job_title' => ['nullable', 'string', 'max:120'],
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^(\+27|0)\d{9}$/'],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return ['phone.regex' => 'Use a South African number, for example 082 123 4567 or +27821234567.'];
    }

    /**
     * @return array{name: string, email: string, job_title: string|null, phone: string|null}
     */
    public function userData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'email' => $this->string('email')->trim()->lower()->toString(),
            'job_title' => $this->filled('job_title') ? $this->string('job_title')->trim()->toString() : null,
            'phone' => $this->filled('phone') ? preg_replace('/\s+/', '', $this->string('phone')->toString()) : null,
        ];
    }

    public function role(): Role
    {
        return Role::from($this->string('role')->toString());
    }

    protected function prepareForValidation(): void
    {
        if ($this->filled('phone')) {
            $this->merge(['phone' => preg_replace('/\s+/', '', $this->string('phone')->toString())]);
        }
    }
}
