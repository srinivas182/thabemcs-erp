<?php

declare(strict_types=1);

namespace Database\Factories;

use App\Domains\Platform\Models\Company;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'phone' => null,
            'job_title' => fake()->jobTitle(),
            'company_id' => null,
            'is_super_admin' => false,
            'is_active' => true,
            'last_login_at' => null,
            // Two-factor is required of everyone, so test users have it set up already. A test that is
            // checking the requirement itself clears these with forceFill.
            'two_factor_secret' => encrypt('test-secret'),
            'two_factor_recovery_codes' => null,
            'two_factor_confirmed_at' => now(),
        ];
    }

    public function forCompany(Company $company): static
    {
        return $this->state(fn (array $attributes) => ['company_id' => $company->getKey()]);
    }

    public function superAdmin(): static
    {
        // Super Admins must have two-factor authentication; tests get it already set up.
        return $this->state(fn (array $attributes) => [
            'company_id' => null, 'is_super_admin' => true,
            'two_factor_secret' => encrypt('test-secret'), 'two_factor_confirmed_at' => now(),
        ]);
    }

    /** For tests about signing in or setting two-factor up in the first place. */
    public function withoutTwoFactor(): static
    {
        return $this->state(fn (array $attributes) => ['two_factor_secret' => null, 'two_factor_confirmed_at' => null]);
    }

    public function inactive(): static
    {
        return $this->state(fn (array $attributes) => ['is_active' => false]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
