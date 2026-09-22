<?php

declare(strict_types=1);

use App\Domains\Feasibility\Http\Controllers\FeasibilityController;
use App\Domains\Funding\Http\Controllers\FundingController;
use App\Domains\Funding\Http\Controllers\InvestorController;
use App\Domains\Platform\Http\Controllers\ActingCompanyController;
use App\Domains\Platform\Http\Controllers\CompanyController;
use App\Domains\Platform\Http\Controllers\MyDayController;
use App\Domains\Platform\Http\Controllers\NotificationController;
use App\Domains\Platform\Http\Controllers\SearchController;
use App\Domains\Platform\Http\Controllers\Settings\ActivityLogController;
use App\Domains\Platform\Http\Controllers\Settings\CompanyUserController;
use App\Domains\Platform\Http\Controllers\Settings\ProfileController;
use App\Domains\Projects\Http\Controllers\MilestoneController;
use App\Domains\Projects\Http\Controllers\ProjectController;
use App\Domains\Projects\Http\Controllers\RiskController;
use App\Domains\Projects\Http\Controllers\StageGateController;
use App\Domains\Projects\Http\Controllers\TaskController;
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

    // Projects module: register, stage gates, milestones, tasks, risks and issues.
    Route::middleware('module:projects')->group(function (): void {
        Route::resource('projects', ProjectController::class)->except(['destroy']);
        Route::post('projects/{project}/gate-items/{item}', [StageGateController::class, 'toggle'])->name('projects.gate-items.toggle');
        Route::post('projects/{project}/advance', [StageGateController::class, 'advance'])->name('projects.advance');

        Route::post('projects/{project}/milestones', [MilestoneController::class, 'store'])->name('projects.milestones.store');
        Route::patch('milestones/{milestone}', [MilestoneController::class, 'update'])->name('milestones.update');
        Route::delete('milestones/{milestone}', [MilestoneController::class, 'destroy'])->name('milestones.destroy');

        Route::post('projects/{project}/tasks', [TaskController::class, 'store'])->name('projects.tasks.store');
        Route::patch('tasks/{task}', [TaskController::class, 'update'])->name('tasks.update');
        Route::delete('tasks/{task}', [TaskController::class, 'destroy'])->name('tasks.destroy');

        Route::post('projects/{project}/risks', [RiskController::class, 'store'])->name('projects.risks.store');
        Route::patch('risks/{risk}', [RiskController::class, 'update'])->name('risks.update');
        Route::delete('risks/{risk}', [RiskController::class, 'destroy'])->name('risks.destroy');
    });

    // Feasibility: scenarios, appraisal and baseline approval.
    Route::middleware(['module:projects', 'module:feasibility'])->group(function (): void {
        Route::get('projects/{project}/feasibility', [FeasibilityController::class, 'show'])->name('projects.feasibility');
        Route::post('projects/{project}/feasibility', [FeasibilityController::class, 'store'])->name('projects.feasibility.store');
        Route::put('feasibilities/{feasibility}', [FeasibilityController::class, 'update'])->name('feasibilities.update');
        Route::post('feasibilities/{feasibility}/approve', [FeasibilityController::class, 'approve'])->name('feasibilities.approve');
    });

    // Funding: sources, capital movements, project bank account, investor register.
    Route::middleware('module:funding')->group(function (): void {
        Route::get('projects/{project}/funding', [FundingController::class, 'show'])->middleware('module:projects')->name('projects.funding');
        Route::post('projects/{project}/funding-sources', [FundingController::class, 'storeSource'])->name('projects.funding-sources.store');
        Route::patch('funding-sources/{source}', [FundingController::class, 'updateSource'])->name('funding-sources.update');
        Route::post('funding-sources/{source}/movements', [FundingController::class, 'storeMovement'])->name('funding-sources.movements.store');
        Route::put('projects/{project}/bank-account', [FundingController::class, 'saveAccount'])->name('projects.bank-account');

        Route::get('investors', [InvestorController::class, 'index'])->name('investors.index');
        Route::post('investors', [InvestorController::class, 'store'])->name('investors.store');
        Route::post('investors/{investor}/verify', [InvestorController::class, 'verify'])->name('investors.verify');
        Route::delete('investors/{investor}', [InvestorController::class, 'destroy'])->name('investors.destroy');
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
