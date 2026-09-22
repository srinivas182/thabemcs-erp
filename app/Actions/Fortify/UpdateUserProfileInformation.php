<?php

declare(strict_types=1);

namespace App\Actions\Fortify;

use App\Models\User;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

/**
 * Lets a signed-in user update their own name, email and mobile number.
 */
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    /**
     * @param  array<string, mixed>  $input
     *
     * @throws ValidationException
     */
    public function update(User $user, array $input): void
    {
        if (isset($input['phone']) && is_string($input['phone'])) {
            $input['phone'] = preg_replace('/\s+/', '', $input['phone']);
        }

        /** @var array{name: string, email: string, phone?: string|null} $data */
        $data = Validator::make($input, [
            'name' => ['required', 'string', 'max:120'],
            'email' => ['required', 'string', 'email:rfc', 'max:190', Rule::unique('users')->ignore($user->id)],
            'phone' => ['nullable', 'string', 'regex:/^(\+27|0)\d{9}$/'],
        ], [
            'phone.regex' => 'Use a South African number, for example 082 123 4567 or +27821234567.',
        ])->validate();

        $user->forceFill([
            'name' => $data['name'],
            'email' => strtolower($data['email']),
            'phone' => $data['phone'] ?? null,
        ])->save();
    }
}
