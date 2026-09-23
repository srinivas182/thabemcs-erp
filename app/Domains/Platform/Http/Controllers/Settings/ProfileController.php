<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
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
                'required' => $user->is_super_admin || $user->hasAnyRole((array) config('platform.two_factor_required_roles', [])),
            ],
            'lastSignedIn' => $user->getAttribute('last_login_at')?->toIso8601String(),
        ]);
    }

    /**
     * Sign out everywhere else. Used when a phone is lost or a password may be known to someone else.
     */
    public function signOutOthers(Request $request): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $data['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'That password is not right.']);
        }

        Auth::logoutOtherDevices((string) $data['password']);
        activity('security')->causedBy($user)->log('Signed out of all other devices');

        return back()->with('success', 'You are signed out everywhere else.');
    }
}
