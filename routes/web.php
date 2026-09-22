<?php

declare(strict_types=1);

use App\Domains\Platform\Http\Controllers\ActingCompanyController;
use App\Domains\Platform\Http\Controllers\CompanyController;
use App\Domains\Platform\Http\Controllers\MyDayController;
use App\Domains\Platform\Http\Controllers\Settings\CompanyUserController;
use Illuminate\Support\Facades\Route;

/*
| Management web app (React + Inertia). Authentication routes are registered by Fortify.
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/', MyDayController::class)->name('my-day');

    // Super Admin: platform administration.
    Route::middleware('super-admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);
        Route::patch('companies/{company}/status', [CompanyController::class, 'updateStatus'])->name('companies.status');

        Route::post('acting-company/{company}', [ActingCompanyController::class, 'store'])->name('acting-company.store');
        Route::delete('acting-company', [ActingCompanyController::class, 'destroy'])->name('acting-company.destroy');
    });

    // Company settings (Company Admin).
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('users', [CompanyUserController::class, 'index'])->name('users.index');
        Route::post('users', [CompanyUserController::class, 'store'])->name('users.store');
        Route::patch('users/{user:ulid}', [CompanyUserController::class, 'update'])->name('users.update');
    });
});
