<?php

declare(strict_types=1);

use App\Domains\Platform\Http\Controllers\ActingCompanyController;
use App\Domains\Platform\Http\Controllers\MyDayController;
use Illuminate\Support\Facades\Route;

/*
| Management web app (React + Inertia). Authentication routes are registered by Fortify.
| Each domain adds its own route file under routes/domains/ as it is delivered.
*/

Route::middleware(['auth'])->group(function (): void {
    Route::get('/', MyDayController::class)->name('my-day');

    Route::post('/platform/acting-company/{company}', [ActingCompanyController::class, 'store'])
        ->name('platform.acting-company.store');
    Route::delete('/platform/acting-company', [ActingCompanyController::class, 'destroy'])
        ->name('platform.acting-company.destroy');
});
