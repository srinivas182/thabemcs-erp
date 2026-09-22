<?php

declare(strict_types=1);

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
| Versioned REST API used by the Site App (PWA) and future integrations / native apps.
| Authentication: Laravel Sanctum (SPA cookie or bearer token).
*/

Route::prefix('v1')->middleware(['auth:sanctum', 'company', 'throttle:api'])->group(function (): void {
    Route::get('/me', function (Request $request): array {
        /** @var User $user */
        $user = $request->user();

        return [
            'data' => [
                'id' => $user->ulid,
                'name' => $user->name,
                'email' => $user->email,
                'company' => $user->company?->only(['ulid', 'name']),
                'roles' => $user->getRoleNames(),
            ],
        ];
    })->name('api.v1.me');
});
