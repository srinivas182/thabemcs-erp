<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Requests;

/**
 * Creating a company also creates its first Company Admin, who receives an invitation email.
 */
final class StoreCompanyRequest extends CompanyRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            ...parent::rules(),
            'admin_name' => ['required', 'string', 'max:120'],
            'admin_email' => ['required', 'email:rfc', 'max:190', 'unique:users,email'],
        ];
    }

    /**
     * @return array{name: string, email: string}
     */
    public function adminData(): array
    {
        return [
            'name' => $this->string('admin_name')->trim()->toString(),
            'email' => $this->string('admin_email')->trim()->lower()->toString(),
        ];
    }
}
