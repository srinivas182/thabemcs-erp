<?php

declare(strict_types=1);

use App\Domains\Platform\Http\Controllers\ActingCompanyController;
use App\Domains\Platform\Http\Controllers\CompanyController;
use App\Domains\Platform\Http\Controllers\MyDayController;
use App\Domains\Platform\Http\Controllers\NotificationController;
use App\Domains\Platform\Http\Controllers\SearchController;
use App\Domains\Platform\Http\Controllers\Settings\ActivityLogController;
use App\Domains\Platform\Http\Controllers\Settings\CompanyUserController;
use App\Domains\Platform\Http\Controllers\Settings\ProfileController;
use Illuminate\Support\Facades\Route;

/*
| Management web app (React + Inertia). Authentication routes are registered by Fortify.
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/', MyDayController::class)->name('my-day');
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Super Admin: platform administration.
    Route::middleware('super-admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);
        Route::patch('companies/{company}/status', [CompanyController::class, 'updateStatus'])->name('companies.status');

        Route::post('acting-company/{company}', [ActingCompanyController::class, 'store'])->name('acting-company.store');
        Route::delete('acting-company', [ActingCompanyController::class, 'destroy'])->name('acting-company.destroy');
    });

    // Company settings (Company Admin).
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity');
        Route::get('users', [CompanyUserController::class, 'index'])->name('users.index');
        Route::post('users', [CompanyUserController::class, 'store'])->name('users.store');
        Route::patch('users/{user:ulid}', [CompanyUserController::class, 'update'])->name('users.update');
    });
});
