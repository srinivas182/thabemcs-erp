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
            ['email' => env('SUPER_ADMIN_EMAIL', 'admin@thabekhulu.local')],
            [
                'name' => env('SUPER_ADMIN_NAME', 'Platform Administrator'),
                'password' => env('SUPER_ADMIN_PASSWORD', 'ChangeMe!2026'),
            ],
        )->forceFill(['is_super_admin' => true, 'email_verified_at' => now()])->save();
    }
}
