<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use Illuminate\Contracts\Validation\Rule;
use Illuminate\Validation\Rules\Password;

trait PasswordValidationRules
{
    /**
     * Get the validation rules used to validate passwords.
     *
     * @return array<int, Rule|array<mixed>|string>
     */
    protected function passwordRules(): array
    {
        return [
            'required',
            'string',
            Password::min(12)
                ->letters()
                ->numbers()
                // Checked against the public list of passwords known to have been breached. No password
                // leaves the machine: only the first five characters of its hash are sent.
                ->uncompromised(),
            'confirmed',
        ];
    }
}
