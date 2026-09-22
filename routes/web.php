<?php

declare(strict_types=1);

use App\Domains\Approvals\Http\Controllers\ApprovalController;
use App\Domains\Documents\Http\Controllers\DocumentController;
use App\Domains\Feasibility\Http\Controllers\FeasibilityController;
use App\Domains\Funding\Http\Controllers\FundingController;
use App\Domains\Funding\Http\Controllers\InvestorController;
use App\Domains\Land\Http\Controllers\LandController;
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
use App\Domains\Safety\Http\Controllers\SafetyController;
use App\Domains\Site\Http\Controllers\SiteController;
use App\Domains\Suppliers\Http\Controllers\SupplierController;
use App\Domains\Team\Http\Controllers\TeamController;
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

    // Land pipeline and due diligence.
    Route::middleware('module:land')->group(function (): void {
        Route::get('land', [LandController::class, 'index'])->name('land.index');
        Route::post('land', [LandController::class, 'store'])->name('land.store');
        Route::get('land/{parcel}', [LandController::class, 'show'])->name('land.show');
        Route::put('land/{parcel}', [LandController::class, 'update'])->name('land.update');
        Route::patch('land/{parcel}/status', [LandController::class, 'status'])->name('land.status');
        Route::patch('land/{parcel}/checks/{check}', [LandController::class, 'check'])->name('land.checks.update');
    });

    // Statutory approvals register.
    Route::middleware(['module:approvals', 'module:projects'])->group(function (): void {
        Route::get('approvals', [ApprovalController::class, 'index'])->name('approvals.index');
        Route::post('approvals', [ApprovalController::class, 'store'])->name('approvals.store');
        Route::patch('approvals/{application}', [ApprovalController::class, 'update'])->name('approvals.update');
    });

    // Professional team and fee claims (part of the Projects module).
    Route::middleware('module:projects')->group(function (): void {
        Route::get('projects/{project}/team', [TeamController::class, 'show'])->name('projects.team');
        Route::post('projects/{project}/team', [TeamController::class, 'store'])->name('projects.team.store');
        Route::post('appointments/{appointment}/verify', [TeamController::class, 'verify'])->name('appointments.verify');
        Route::post('appointments/{appointment}/claims', [TeamController::class, 'storeClaim'])->name('appointments.claims.store');
        Route::patch('fee-claims/{claim}', [TeamController::class, 'updateClaim'])->name('fee-claims.update');
    });

    // Contractor and supplier registry with compliance.
    Route::middleware('module:suppliers')->group(function (): void {
        Route::get('suppliers', [SupplierController::class, 'index'])->name('suppliers.index');
        Route::post('suppliers', [SupplierController::class, 'store'])->name('suppliers.store');
        Route::get('suppliers/{supplier}', [SupplierController::class, 'show'])->name('suppliers.show');
        Route::put('suppliers/{supplier}', [SupplierController::class, 'update'])->name('suppliers.update');
        Route::patch('suppliers/{supplier}/status', [SupplierController::class, 'status'])->name('suppliers.status');
        Route::post('suppliers/{supplier}/documents', [SupplierController::class, 'storeDocument'])->name('suppliers.documents.store');
        Route::post('suppliers/{supplier}/documents/{document}/verify', [SupplierController::class, 'verifyDocument'])->name('suppliers.documents.verify');
        Route::post('suppliers/{supplier}/ratings', [SupplierController::class, 'rate'])->name('suppliers.ratings.store');
    });

    // Document management: private, versioned, permission-checked downloads.
    Route::middleware('module:documents')->group(function (): void {
        Route::get('documents', [DocumentController::class, 'index'])->name('documents.index');
        Route::post('documents', [DocumentController::class, 'store'])->middleware('throttle:60,1')->name('documents.store');
        Route::post('documents/{document}/versions', [DocumentController::class, 'addVersion'])->middleware('throttle:60,1')->name('documents.versions.store');
        Route::get('documents/{document}/versions/{version}/download', [DocumentController::class, 'download'])->name('documents.download');
        Route::delete('documents/{document}', [DocumentController::class, 'destroy'])->name('documents.destroy');
    });

    // Site management (web view of what the site app captures).
    Route::middleware(['module:projects', 'module:site'])->group(function (): void {
        Route::get('projects/{project}/site', [SiteController::class, 'show'])->name('projects.site');
        Route::post('projects/{project}/site-instructions', [SiteController::class, 'storeInstruction'])->name('projects.site-instructions.store');
        Route::patch('site-instructions/{instruction}', [SiteController::class, 'updateInstruction'])->name('site-instructions.update');
        Route::post('projects/{project}/inspections', [SiteController::class, 'storeInspection'])->name('projects.inspections.store');
        Route::post('projects/{project}/snags', [SiteController::class, 'storeSnag'])->name('projects.snags.store');
        Route::patch('snags/{snag}', [SiteController::class, 'updateSnag'])->name('snags.update');
        Route::get('site-photos/{photo}', [SiteController::class, 'photo'])->name('site-photos.show');
    });

    // Health and safety.
    Route::middleware(['module:projects', 'module:safety'])->group(function (): void {
        Route::get('projects/{project}/safety', [SafetyController::class, 'show'])->name('projects.safety');
        Route::patch('safety-incidents/{incident}', [SafetyController::class, 'updateIncident'])->name('safety-incidents.update');
        Route::post('projects/{project}/toolbox-talks', [SafetyController::class, 'storeTalk'])->name('projects.toolbox-talks.store');
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

/*
| Site app (PWA), built into public/site. Any /site/* path returns the app shell;
| the app's own router takes over in the browser.
*/
Route::get('/site/{path?}', function () {
    $shell = public_path('site/index.html');
    abort_unless(is_file($shell), 404, 'The site app has not been built. Run: npm run build --workspace site-app');

    return response()->file($shell, ['Cache-Control' => 'no-cache']);
})->where('path', '.*')->name('site-app');
