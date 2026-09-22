<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Requests;

use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Models\Company;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation shared by creating and editing a company.
 */
class CompanyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true; // Route is already limited to Super Admins.
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $company = $this->route('company');
        $ignoreId = $company instanceof Company ? $company->getKey() : null;

        return [
            'name' => ['required', 'string', 'max:120', Rule::unique('companies', 'name')->ignore($ignoreId)],
            'legal_name' => ['nullable', 'string', 'max:160'],
            'registration_number' => ['nullable', 'string', 'max:32', 'regex:/^\d{4}\/\d{6}\/\d{2}$/'],
            'vat_number' => ['nullable', 'string', 'regex:/^4\d{9}$/'],
            'max_projects' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_users' => ['nullable', 'integer', 'min:1', 'max:1000000'],
            'max_storage_mb' => ['nullable', 'integer', 'min:100'],
            'modules' => ['present', 'array'],
            'modules.*' => ['string', Rule::enum(Module::class)],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'registration_number.regex' => 'Use the CIPC format, for example 2021/123456/07.',
            'vat_number.regex' => 'A South African VAT number is 10 digits starting with 4.',
        ];
    }

    /**
     * Company attributes ready to save.
     *
     * @return array{name: string, legal_name: ?string, registration_number: ?string, vat_number: ?string, max_projects: ?int, max_users: ?int, max_storage_mb: ?int, modules: list<string>}
     */
    public function companyData(): array
    {
        return [
            'name' => $this->string('name')->trim()->toString(),
            'legal_name' => $this->nullableString('legal_name'),
            'registration_number' => $this->nullableString('registration_number'),
            'vat_number' => $this->nullableString('vat_number'),
            'max_projects' => $this->nullableInt('max_projects'),
            'max_users' => $this->nullableInt('max_users'),
            'max_storage_mb' => $this->nullableInt('max_storage_mb'),
            'modules' => array_values(array_map(strval(...), $this->array('modules'))),
        ];
    }

    protected function nullableString(string $key): ?string
    {
        return $this->filled($key) ? $this->string($key)->trim()->toString() : null;
    }

    protected function nullableInt(string $key): ?int
    {
        return $this->filled($key) ? $this->integer($key) : null;
    }
}
