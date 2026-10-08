<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Domains\Platform\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response;
use Laravel\Fortify\Actions\DisableTwoFactorAuthentication;

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
                'required' => PlatformSetting::requiresTwoFactor(),
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

    /**
     * Turn two-factor authentication off, confirming the password in the same request.
     *
     * Fortify's own route asks for a password confirmation first, which redirects the browser and loses
     * the original request, so the control appeared to do nothing. This does both steps at once.
     */
    public function disableTwoFactor(Request $request, DisableTwoFactorAuthentication $disable): RedirectResponse
    {
        $data = $request->validate(['password' => ['required', 'string']]);
        /** @var User $user */
        $user = $request->user();

        if (! Hash::check((string) $data['password'], (string) $user->password)) {
            return back()->withErrors(['password' => 'That password is not right.']);
        }

        if (PlatformSetting::requiresTwoFactor()) {
            return back()->with('error', 'Two-factor authentication is required on this instance, so it cannot be turned off.');
        }

        $disable($user);
        activity('security')->causedBy($user)->log('Two-factor authentication turned off');

        return back()->with('success', 'Two-factor authentication is off.');
    }
}
