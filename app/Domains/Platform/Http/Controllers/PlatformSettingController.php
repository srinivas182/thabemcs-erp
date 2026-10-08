<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Domains\Platform\Models\PlatformSetting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Settings that apply to the whole instance. Super Admins only; the route group enforces that.
 */
final class PlatformSettingController
{
    public function edit(): Response
    {
        $people = User::query()->where('is_active', true)->count();
        $ready = User::query()->where('is_active', true)->whereNotNull('two_factor_confirmed_at')->count();

        return Inertia::render('platform/settings', [
            'settings' => ['twoFactorRequired' => PlatformSetting::requiresTwoFactor()],
            'people' => ['total' => $people, 'withTwoFactor' => $ready],
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate(['two_factor_required' => ['required', 'boolean']]);
        /** @var User $user */
        $user = $request->user();

        $setting = PlatformSetting::current();
        $setting->update(['two_factor_required' => $data['two_factor_required'], 'updated_by' => $user->id]);

        activity('platform')->causedBy($user)
            ->log($data['two_factor_required'] ? 'Two-factor authentication required for everyone' : 'Two-factor authentication made optional');

        return back()->with('success', $data['two_factor_required']
            ? 'Two-factor authentication is now required. Everyone will be asked to set it up, including you.'
            : 'Two-factor authentication is now optional.');
    }
}
