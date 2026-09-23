<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers\Settings;

use App\Domains\Platform\Models\NotificationPreference;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

final class NotificationPreferenceController
{
    public function update(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'email_immediately' => ['required', 'boolean'],
            'daily_digest' => ['required', 'boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();
        NotificationPreference::query()->updateOrCreate(['user_id' => $user->id], $data);

        return back()->with('success', 'Notification settings saved.');
    }
}
