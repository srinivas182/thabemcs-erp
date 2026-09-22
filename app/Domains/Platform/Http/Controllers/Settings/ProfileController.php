<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Models\User;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's own profile, password and two-factor authentication.
 * Updates go to Fortify's endpoints (/user/profile-information, /user/password, /user/two-factor-*).
 */
final class ProfileController
{
    public function show(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('settings/profile', [
            'profile' => ['name' => $user->name, 'email' => $user->email, 'phone' => $user->phone],
            'twoFactor' => [
                'enabled' => $user->two_factor_secret !== null,
                'confirmed' => $user->two_factor_confirmed_at !== null,
            ],
        ]);
    }
}
