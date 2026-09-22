<?php

declare(strict_types=1);

use App\Domains\Site\Http\Controllers\Api\SiteApiController;
use App\Domains\Site\Http\Controllers\Api\SiteOperationsApiController;
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

    // Site app sync endpoints (idempotent by clientId).
    Route::middleware('module:site')->prefix('site')->name('api.v1.site.')->group(function (): void {
        Route::get('projects', [SiteApiController::class, 'projects'])->name('projects');
        Route::get('suppliers', [SiteApiController::class, 'suppliers'])->name('suppliers');
        Route::post('diary-entries', [SiteApiController::class, 'diary'])->name('diary');
        Route::post('attendance', [SiteApiController::class, 'attendance'])->name('attendance');
        Route::post('photos', [SiteApiController::class, 'photo'])->name('photos');
        Route::post('deliveries', [SiteApiController::class, 'delivery'])->name('deliveries');
        Route::post('incidents', [SiteApiController::class, 'incident'])->middleware('module:safety')->name('incidents');
        Route::get('employees', [SiteOperationsApiController::class, 'employees'])->middleware('module:workforce')->name('employees');
        Route::post('crew-attendance', [SiteOperationsApiController::class, 'crewAttendance'])->middleware('module:workforce')->name('crew-attendance');
        Route::get('orders', [SiteOperationsApiController::class, 'orders'])->middleware('module:procurement')->name('orders');
        Route::post('receipts', [SiteOperationsApiController::class, 'receive'])->middleware('module:procurement')->name('receipts');
        Route::post('snags', [SiteOperationsApiController::class, 'snag'])->name('snags');
        Route::post('inspections', [SiteOperationsApiController::class, 'inspection'])->name('inspections');
        Route::post('instructions', [SiteOperationsApiController::class, 'instruction'])->name('instructions');
    });
});
