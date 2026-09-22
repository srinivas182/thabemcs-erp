<?php

declare(strict_types=1);

namespace App\Domains\Platform\Http\Controllers;

use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

/**
 * The signed-in user's notification centre.
 */
final class NotificationController
{
    public function index(Request $request): Response
    {
        /** @var User $user */
        $user = $request->user();

        $notifications = $user->notifications()->latest()->paginate(30)
            ->through(static fn (DatabaseNotification $n): array => [
                'id' => $n->id,
                'data' => $n->data,
                'read' => $n->read_at !== null,
                'createdAt' => $n->created_at?->toIso8601String(),
            ]);

        return Inertia::render('notifications/index', ['notifications' => $notifications]);
    }

    public function markRead(Request $request, string $id): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->notifications()->whereKey($id)->firstOrFail()->markAsRead();

        return back();
    }

    public function markAllRead(Request $request): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();
        $user->unreadNotifications()->update(['read_at' => now()]);

        return back()->with('success', 'All notifications marked as read.');
    }
}
