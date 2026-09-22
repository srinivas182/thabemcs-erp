<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

/**
 * Creates the initial Super Admin from environment variables.
 */
class PlatformSeeder extends Seeder
{
    public function run(): void
    {
        User::query()->firstOrCreate(
            ['email' => config('platform.super_admin.email')],
            [
                'name' => config('platform.super_admin.name'),
                'password' => config('platform.super_admin.password'),
            ],
        )->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
    }
}
