<?php

declare(strict_types=1);

use App\Domains\Approvals\Http\Controllers\ApprovalController;
use App\Domains\Closeout\Http\Controllers\CloseoutController;
use App\Domains\Closeout\Http\Controllers\DistributionController;
use App\Domains\Cms\Http\Controllers\CmsController;
use App\Domains\Cms\Http\Controllers\PageController;
use App\Domains\Cms\Http\Controllers\WebsiteController;
use App\Domains\Compliance\Http\Controllers\PopiaController;
use App\Domains\Contracts\Http\Controllers\ContractController;
use App\Domains\Documents\Http\Controllers\DocumentController;
use App\Domains\Feasibility\Http\Controllers\FeasibilityController;
use App\Domains\Finance\Http\Controllers\BudgetController;
use App\Domains\Finance\Http\Controllers\InvoiceController;
use App\Domains\Finance\Http\Controllers\PaymentRunController;
use App\Domains\Finance\Http\Controllers\ReportsExportController;
use App\Domains\Forms\Http\Controllers\FormController;
use App\Domains\Funding\Http\Controllers\FundingController;
use App\Domains\Funding\Http\Controllers\InvestorController;
use App\Domains\Integrations\Http\Controllers\IntegrationController;
use App\Domains\Land\Http\Controllers\LandController;
use App\Domains\MasterData\Http\Controllers\MasterDataController;
use App\Domains\Meetings\Http\Controllers\MeetingController;
use App\Domains\Plant\Http\Controllers\PlantController;
use App\Domains\Platform\Http\Controllers\ActingCompanyController;
use App\Domains\Platform\Http\Controllers\CompanyController;
use App\Domains\Platform\Http\Controllers\HealthController;
use App\Domains\Platform\Http\Controllers\LookupController;
use App\Domains\Platform\Http\Controllers\MyDayController;
use App\Domains\Platform\Http\Controllers\NotificationController;
use App\Domains\Platform\Http\Controllers\PlatformSettingController;
use App\Domains\Platform\Http\Controllers\SearchController;
use App\Domains\Platform\Http\Controllers\Settings\ActivityLogController;
use App\Domains\Platform\Http\Controllers\Settings\CompanyUserController;
use App\Domains\Platform\Http\Controllers\Settings\ImportController;
use App\Domains\Platform\Http\Controllers\Settings\IntegrationApiController;
use App\Domains\Platform\Http\Controllers\Settings\NotificationPreferenceController;
use App\Domains\Platform\Http\Controllers\Settings\ProfileController;
use App\Domains\Procurement\Http\Controllers\PurchaseOrderController;
use App\Domains\Procurement\Http\Controllers\RequisitionController;
use App\Domains\Procurement\Http\Controllers\RfqController;
use App\Domains\Programme\Http\Controllers\PerformanceController;
use App\Domains\Programme\Http\Controllers\ProgrammeController;
use App\Domains\Projects\Http\Controllers\MilestoneController;
use App\Domains\Projects\Http\Controllers\ProjectController;
use App\Domains\Projects\Http\Controllers\RiskController;
use App\Domains\Projects\Http\Controllers\StageGateController;
use App\Domains\Projects\Http\Controllers\TaskController;
use App\Domains\Rentals\Http\Controllers\MaintenanceController;
use App\Domains\Rentals\Http\Controllers\RentalController;
use App\Domains\Rentals\Http\Controllers\TenantController;
use App\Domains\Reporting\Http\Controllers\MapController;
use App\Domains\Reporting\Http\Controllers\ReportController;
use App\Domains\Reporting\Http\Controllers\ReportDesignerController;
use App\Domains\Safety\Http\Controllers\SafetyComplianceController;
use App\Domains\Safety\Http\Controllers\SafetyController;
use App\Domains\Sales\Http\Controllers\BuyerController;
use App\Domains\Sales\Http\Controllers\SaleAgreementController;
use App\Domains\Sales\Http\Controllers\SalesController;
use App\Domains\Site\Http\Controllers\SiteController;
use App\Domains\Suppliers\Http\Controllers\SupplierController;
use App\Domains\Team\Http\Controllers\TeamController;
use App\Domains\Workflow\Http\Controllers\InboxController;
use App\Domains\Workforce\Http\Controllers\WorkforceController;
use App\Http\Middleware\ResolvePublicCompany;
use Illuminate\Support\Facades\Route;

/*
| Management web app (React + Inertia). Authentication routes are registered by Fortify.
*/

// Readiness for the load balancer and monitoring (liveness is Laravel's /up).
Route::get('/health', HealthController::class)->middleware('throttle:60,1')->name('health');

Route::middleware(['auth'])->group(function (): void {
    Route::get('/my-day', MyDayController::class)->name('my-day');
    Route::get('/search', SearchController::class)->middleware('throttle:60,1')->name('search');

    Route::get('/notifications', [NotificationController::class, 'index'])->name('notifications.index');
    Route::post('/notifications/read-all', [NotificationController::class, 'markAllRead'])->name('notifications.read-all');
    Route::post('/notifications/{id}/read', [NotificationController::class, 'markRead'])->name('notifications.read');

    // Super Admin: platform administration.
    Route::middleware('super-admin')->prefix('platform')->name('platform.')->group(function (): void {
        Route::resource('companies', CompanyController::class)->except(['show', 'destroy']);
        Route::patch('companies/{company}/status', [CompanyController::class, 'updateStatus'])->name('companies.status');

        Route::get('settings', [PlatformSettingController::class, 'edit'])->name('settings');
        Route::put('settings', [PlatformSettingController::class, 'update'])->name('settings.update');

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

        // Programme (Gantt) with dependencies and critical path.
        Route::get('projects/{project}/programme', [ProgrammeController::class, 'show'])->name('projects.programme');
        Route::post('projects/{project}/programme', [ProgrammeController::class, 'store'])->name('projects.programme.store');
        Route::patch('programme-activities/{activity}', [ProgrammeController::class, 'update'])->name('programme.update');
        Route::delete('programme-activities/{activity}', [ProgrammeController::class, 'destroy'])->name('programme.destroy');
        Route::post('programme-activities/{activity}/links', [ProgrammeController::class, 'link'])->name('programme.link');
        Route::delete('programme-links/{dependency}', [ProgrammeController::class, 'unlink'])->name('programme.unlink');

        // Meetings and minutes; action items become tasks.
        Route::get('projects/{project}/meetings', [MeetingController::class, 'index'])->name('projects.meetings');
        Route::post('projects/{project}/meetings', [MeetingController::class, 'store'])->name('projects.meetings.store');
        Route::get('projects/{project}/meetings/{meeting}', [MeetingController::class, 'show'])->name('projects.meetings.show');
        Route::put('projects/{project}/meetings/{meeting}', [MeetingController::class, 'update'])->name('projects.meetings.update');
        Route::post('projects/{project}/meetings/{meeting}/actions', [MeetingController::class, 'action'])->name('projects.meetings.actions');
        Route::post('projects/{project}/meetings/{meeting}/issue', [MeetingController::class, 'issue'])->name('projects.meetings.issue');
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
        Route::post('projects/{project}/safety-appointments', [SafetyComplianceController::class, 'appoint'])->name('projects.safety-appointments.store');
        Route::post('safety-appointments/{appointment}/end', [SafetyComplianceController::class, 'endAppointment'])->name('safety-appointments.end');
        Route::post('projects/{project}/safety-file', [SafetyComplianceController::class, 'fileItem'])->name('projects.safety-file.update');
    });

    // Portfolio dashboard (group view for the Super Admin with no company selected).
    Route::get('dashboard/portfolio', [ReportController::class, 'dashboard'])->name('dashboard.portfolio');
    Route::get('dashboard/map', [MapController::class, 'show'])->name('dashboard.map');

    // Standard reports: view, print, Excel/CSV download, schedule by email.
    Route::middleware('module:reporting')->group(function (): void {
        Route::get('reports', [ReportController::class, 'index'])->name('reports.index');
        Route::post('reports/schedules', [ReportController::class, 'schedule'])->name('reports.schedules.store');
        Route::post('reports/{key}/presets', [ReportController::class, 'storePreset'])->name('reports.presets.store');
        Route::delete('report-presets/{preset}', [ReportController::class, 'destroyPreset'])->name('reports.presets.destroy');
        Route::get('reports/designer', [ReportDesignerController::class, 'edit'])->name('reports.designer');
        Route::post('reports/designer', [ReportDesignerController::class, 'store'])->name('reports.designer.store');
        Route::get('reports/designer/{report}', [ReportDesignerController::class, 'edit'])->name('reports.designer.edit');
        Route::put('reports/designer/{report}', [ReportDesignerController::class, 'update'])->name('reports.designer.update');
        Route::delete('reports/designer/{report}', [ReportDesignerController::class, 'destroy'])->name('reports.designer.destroy');
        Route::delete('reports/schedules/{schedule}', [ReportController::class, 'unschedule'])->name('reports.schedules.destroy');
        Route::get('reports/{key}', [ReportController::class, 'show'])->name('reports.show');
        Route::get('reports/{key}/download/{format}', [ReportController::class, 'download'])->name('reports.download');
    });

    // Sales: stock schedule, buyers, agreements, transfer and commission.
    Route::middleware('module:projects')->group(function (): void {
        Route::get('projects/{project}/sales', [SalesController::class, 'units'])->name('sales.units');
        Route::post('projects/{project}/sales/units', [SalesController::class, 'storeUnit'])->name('sales.units.store');
        Route::post('sales/units/{unit}/price', [SalesController::class, 'updatePrice'])->name('sales.units.price');
        Route::post('sales/units/{unit}/withdraw', [SalesController::class, 'withdraw'])->name('sales.units.withdraw');
        Route::post('sales/units/{unit}/reserve', [SalesController::class, 'reserve'])->name('sales.units.reserve');
        Route::post('sales/units/{unit}/sign', [SalesController::class, 'sign'])->name('sales.units.sign');

        Route::get('sales/buyers', [BuyerController::class, 'index'])->name('sales.buyers');
        Route::post('sales/buyers', [BuyerController::class, 'store'])->name('sales.buyers.store');
        Route::patch('sales/buyers/{buyer}', [BuyerController::class, 'update'])->name('sales.buyers.update');
        Route::post('sales/buyers/{buyer}/fica', [BuyerController::class, 'verifyFica'])->name('sales.buyers.fica');

        Route::get('sales/agreements', [SaleAgreementController::class, 'index'])->name('sales.agreements');
        Route::get('sales/agreements/{agreement}', [SaleAgreementController::class, 'show'])->name('sales.agreement');
        Route::patch('sales/agreements/{agreement}/deposit', [SaleAgreementController::class, 'updateDeposit'])->name('sales.agreements.deposit');
        Route::post('sales/agreements/{agreement}/commission', [SaleAgreementController::class, 'approveCommission'])->name('sales.agreements.commission');
        Route::patch('sales/conditions/{condition}', [SaleAgreementController::class, 'resolveCondition'])->name('sales.conditions.update');
        Route::patch('sales/transfer-steps/{step}', [SaleAgreementController::class, 'completeStep'])->name('sales.steps.update');
    });

    // Closing out: checklist, final account, investor distributions and reinvestment.
    Route::middleware('module:projects')->group(function (): void {
        Route::get('projects/{project}/closeout', [CloseoutController::class, 'show'])->name('projects.closeout');
        Route::post('closeout-items/{item}/complete', [CloseoutController::class, 'complete'])->name('closeout.items.complete');
        Route::post('closeout-items/{item}/reopen', [CloseoutController::class, 'reopen'])->name('closeout.items.reopen');
        Route::post('projects/{project}/closeout/close', [CloseoutController::class, 'close'])->name('projects.closeout.close');

        Route::get('projects/{project}/distributions', [DistributionController::class, 'index'])->name('projects.distributions');
        Route::post('projects/{project}/distributions', [DistributionController::class, 'store'])->name('projects.distributions.store');
        Route::post('distributions/{distribution}/approve', [DistributionController::class, 'approve'])->name('distributions.approve');
        Route::post('distributions/{distribution}/pay', [DistributionController::class, 'pay'])->name('distributions.pay');
        Route::post('projects/{project}/reinvestments', [DistributionController::class, 'reinvest'])->name('projects.reinvestments');
    });

    // The website: pages, media, menus, articles, forms and what visitors send.
    Route::prefix('website')->name('cms.')->group(function (): void {
        Route::get('pages', [PageController::class, 'index'])->name('pages');
        Route::post('pages', [PageController::class, 'store'])->name('pages.store');
        Route::get('pages/{page}', [PageController::class, 'edit'])->name('pages.edit');
        Route::put('pages/{page}', [PageController::class, 'update'])->name('pages.update');
        Route::post('pages/{page}/publish', [PageController::class, 'publish'])->name('pages.publish');
        Route::post('pages/{page}/unpublish', [PageController::class, 'unpublish'])->name('pages.unpublish');
        Route::post('pages/{page}/home', [PageController::class, 'makeHome'])->name('pages.home');
        Route::post('pages/{page}/restore/{version}', [PageController::class, 'restore'])->name('pages.restore');
        Route::delete('pages/{page}', [PageController::class, 'destroy'])->name('pages.destroy');

        Route::get('media', [CmsController::class, 'mediaIndex'])->name('media');
        Route::post('media', [CmsController::class, 'mediaStore'])->name('media.store');
        Route::patch('media/{media}', [CmsController::class, 'mediaUpdate'])->name('media.update');
        Route::delete('media/{media}', [CmsController::class, 'mediaDestroy'])->name('media.destroy');

        Route::get('menus', [CmsController::class, 'menus'])->name('menus');
        Route::put('menus/{location}', [CmsController::class, 'saveMenu'])->name('menus.save');

        Route::get('articles', [CmsController::class, 'posts'])->name('posts');
        Route::post('articles', [CmsController::class, 'storePost'])->name('posts.store');
        Route::post('categories', [CmsController::class, 'storeCategory'])->name('categories.store');

        Route::get('forms', [CmsController::class, 'forms'])->name('forms');
        Route::post('forms', [CmsController::class, 'storeForm'])->name('forms.store');
        Route::get('enquiries', [CmsController::class, 'submissions'])->name('submissions');
        Route::patch('enquiries/{submission}', [CmsController::class, 'updateSubmission'])->name('submissions.update');
    });

    // Rentals: leases, tenants, billing, deposits, inspections and maintenance.
    Route::middleware('module:projects')->group(function (): void {
        Route::get('rentals', [RentalController::class, 'index'])->name('rentals.index');
        Route::get('rentals/leases/{lease}', [RentalController::class, 'show'])->name('rentals.lease');
        Route::post('rentals/units/{unit}/lease', [RentalController::class, 'store'])->name('rentals.leases.store');
        Route::post('rentals/leases/{lease}/activate', [RentalController::class, 'activate'])->name('rentals.leases.activate');
        Route::post('rentals/leases/{lease}/bill', [RentalController::class, 'bill'])->name('rentals.leases.bill');
        Route::post('rentals/leases/{lease}/charges', [RentalController::class, 'addCharge'])->name('rentals.leases.charges');
        Route::post('rentals/leases/{lease}/receipts', [RentalController::class, 'receipt'])->name('rentals.leases.receipts');
        Route::post('rentals/leases/{lease}/inspections', [RentalController::class, 'inspect'])->name('rentals.leases.inspections');
        Route::post('rentals/leases/{lease}/end', [RentalController::class, 'end'])->name('rentals.leases.end');

        Route::get('rentals/tenants', [TenantController::class, 'index'])->name('rentals.tenants');
        Route::post('rentals/tenants', [TenantController::class, 'store'])->name('rentals.tenants.store');
        Route::patch('rentals/tenants/{tenant}', [TenantController::class, 'update'])->name('rentals.tenants.update');
        Route::post('rentals/tenants/{tenant}/screen', [TenantController::class, 'screen'])->name('rentals.tenants.screen');

        Route::get('rentals/maintenance', [MaintenanceController::class, 'index'])->name('rentals.maintenance');
        Route::post('rentals/maintenance', [MaintenanceController::class, 'store'])->name('rentals.maintenance.store');
        Route::patch('rentals/maintenance/{maintenance}', [MaintenanceController::class, 'update'])->name('rentals.maintenance.update');
    });

    // Form builder: custom checklists for the site app.
    Route::get('forms', [FormController::class, 'index'])->name('forms.index');
    Route::get('forms/new', [FormController::class, 'edit'])->name('forms.create');
    Route::post('forms', [FormController::class, 'store'])->name('forms.store');
    Route::get('forms/{form}/edit', [FormController::class, 'edit'])->name('forms.edit');
    Route::put('forms/{form}', [FormController::class, 'update'])->name('forms.update');
    Route::get('projects/{project}/forms', [FormController::class, 'submissions'])->middleware('module:projects')->name('projects.forms');

    // Accounting and payroll integrations.
    Route::get('settings/integrations', [IntegrationController::class, 'index'])->name('integrations.index');
    Route::put('settings/integrations/{provider}', [IntegrationController::class, 'save'])->whereIn('provider', ['sage_za', 'simplepay'])->name('integrations.save');
    Route::post('settings/integrations/{provider}/test', [IntegrationController::class, 'test'])->whereIn('provider', ['sage_za', 'simplepay'])->name('integrations.test');
    Route::post('settings/integrations/{provider}/sync', [IntegrationController::class, 'sync'])->whereIn('provider', ['sage_za', 'simplepay'])->middleware('throttle:10,1')->name('integrations.sync');
    Route::patch('suppliers/{supplier}/accounting-ref', [IntegrationController::class, 'supplierRef'])->name('suppliers.accounting-ref');
    Route::patch('workforce/{employee}/payroll-ref', [IntegrationController::class, 'employeeRef'])->name('workforce.payroll-ref');

    // Company master data: cost code library and units.
    Route::get('settings/master-data', [MasterDataController::class, 'index'])->name('master-data.index');
    Route::post('settings/master-data/cost-codes', [MasterDataController::class, 'storeCostCode'])->name('master-data.cost-codes.store');
    Route::patch('settings/master-data/cost-codes/{costCode}', [MasterDataController::class, 'toggleCostCode'])->name('master-data.cost-codes.toggle');
    Route::post('settings/master-data/units', [MasterDataController::class, 'storeUnit'])->name('master-data.units.store');
    Route::delete('settings/master-data/units/{unit}', [MasterDataController::class, 'destroyUnit'])->name('master-data.units.destroy');

    // Search-as-you-type options for dropdowns (never whole tables).
    Route::get('lookup/{type}', LookupController::class)->middleware('throttle:120,1')->name('lookup');

    // Opening data imports, API access and webhooks, notification settings.
    Route::get('settings/import', [ImportController::class, 'index'])->name('import.index');
    Route::get('settings/import/{type}/template', [ImportController::class, 'template'])->name('import.template');
    Route::post('settings/import', [ImportController::class, 'upload'])->name('import.upload');

    Route::get('settings/api', [IntegrationApiController::class, 'index'])->name('api.index');
    Route::post('settings/api/tokens', [IntegrationApiController::class, 'createToken'])->name('api.tokens.store');
    Route::delete('settings/api/tokens/{token}', [IntegrationApiController::class, 'revokeToken'])->name('api.tokens.destroy');
    Route::post('settings/api/webhooks', [IntegrationApiController::class, 'storeWebhook'])->name('api.webhooks.store');
    Route::patch('settings/api/webhooks/{webhook}', [IntegrationApiController::class, 'updateWebhook'])->name('api.webhooks.update');
    Route::delete('settings/api/webhooks/{webhook}', [IntegrationApiController::class, 'destroyWebhook'])->name('api.webhooks.destroy');

    Route::patch('settings/notifications', [NotificationPreferenceController::class, 'update'])->name('notifications.preferences');

    // POPIA: register, retention clean-up, data subject requests.
    Route::get('settings/popia', [PopiaController::class, 'index'])->name('popia.index');
    Route::patch('settings/popia/retention/{record}', [PopiaController::class, 'updateRule'])->name('popia.retention.update');
    Route::post('settings/popia/retention/run', [PopiaController::class, 'runCleanup'])->name('popia.retention.run');
    Route::post('settings/popia/requests', [PopiaController::class, 'storeRequest'])->name('popia.requests.store');
    Route::patch('settings/popia/requests/{dataRequest}', [PopiaController::class, 'updateRequest'])->name('popia.requests.update');
    Route::get('settings/popia/requests/{dataRequest}/export', [PopiaController::class, 'export'])->name('popia.requests.export');

    // Approvals inbox and delegation while away.
    Route::get('inbox', [InboxController::class, 'index'])->name('inbox');
    Route::post('inbox/{approval}', [InboxController::class, 'decide'])->name('inbox.decide');
    Route::post('delegations', [InboxController::class, 'delegate'])->name('delegations.store');
    Route::delete('delegations/{delegation}', [InboxController::class, 'revoke'])->name('delegations.destroy');

    // Procurement: requisitions, quotes, purchase orders, goods received.
    Route::middleware(['module:procurement', 'module:projects'])->group(function (): void {
        Route::get('requisitions', [RequisitionController::class, 'index'])->name('requisitions.index');
        Route::post('requisitions', [RequisitionController::class, 'store'])->name('requisitions.store');
        Route::get('requisitions/{requisition}', [RequisitionController::class, 'show'])->name('requisitions.show');
        Route::post('requisitions/{requisition}/submit', [RequisitionController::class, 'submit'])->name('requisitions.submit');
        Route::post('requisitions/{requisition}/quotes', [RequisitionController::class, 'storeQuote'])->name('requisitions.quotes.store');
        Route::post('requisitions/{requisition}/award', [RequisitionController::class, 'award'])->name('requisitions.award');
        Route::post('requisitions/{requisition}/rfq', [RfqController::class, 'invite'])->name('requisitions.rfq');

        Route::get('purchase-orders', [PurchaseOrderController::class, 'index'])->name('purchase-orders.index');
        Route::get('purchase-orders/{order}', [PurchaseOrderController::class, 'show'])->name('purchase-orders.show');
        Route::put('purchase-orders/{order}', [PurchaseOrderController::class, 'updateLines'])->name('purchase-orders.update');
        Route::post('purchase-orders/{order}/submit', [PurchaseOrderController::class, 'submit'])->name('purchase-orders.submit');
        Route::post('purchase-orders/{order}/issue', [PurchaseOrderController::class, 'issue'])->name('purchase-orders.issue');
        Route::post('purchase-orders/{order}/receive', [PurchaseOrderController::class, 'receive'])->name('purchase-orders.receive');
        Route::post('purchase-orders/{order}/cancel', [PurchaseOrderController::class, 'cancel'])->name('purchase-orders.cancel');
    });

    // Finance: budgets and variations per project, supplier invoices, payment runs.
    Route::middleware(['module:finance', 'module:projects'])->group(function (): void {
        Route::get('projects/{project}/budget', [BudgetController::class, 'show'])->name('projects.budget');
        Route::post('projects/{project}/budget/from-feasibility', [BudgetController::class, 'fromFeasibility'])->name('projects.budget.from-feasibility');
        Route::post('projects/{project}/budget/import', [BudgetController::class, 'import'])->name('projects.budget.import');
        Route::post('projects/{project}/budget/lines', [BudgetController::class, 'storeLine'])->name('projects.budget.lines.store');
        Route::post('projects/{project}/variations', [BudgetController::class, 'raiseVariation'])->name('projects.variations.store');

        Route::get('invoices', [InvoiceController::class, 'index'])->name('invoices.index');
        Route::post('invoices', [InvoiceController::class, 'store'])->name('invoices.store');
        Route::post('invoices/{invoice}/approve', [InvoiceController::class, 'approve'])->name('invoices.approve');
        Route::post('invoices/{invoice}/reject', [InvoiceController::class, 'reject'])->name('invoices.reject');
        Route::post('invoices/{invoice}/rematch', [InvoiceController::class, 'rematch'])->name('invoices.rematch');

        Route::get('payment-runs', [PaymentRunController::class, 'index'])->name('payment-runs.index');
        Route::post('payment-runs', [PaymentRunController::class, 'store'])->name('payment-runs.store');
        Route::get('payment-runs/{run}', [PaymentRunController::class, 'show'])->name('payment-runs.show');
        Route::post('payment-runs/{run}/submit', [PaymentRunController::class, 'submit'])->name('payment-runs.submit');
        Route::post('payment-runs/{run}/paid', [PaymentRunController::class, 'paid'])->name('payment-runs.paid');
        Route::get('payment-runs/{run}/export', [PaymentRunController::class, 'export'])->name('payment-runs.export');

        Route::get('projects/{project}/contracts', [ContractController::class, 'show'])->name('projects.contracts');
        Route::post('projects/{project}/contracts', [ContractController::class, 'store'])->name('projects.contracts.store');
        Route::patch('contracts/{contract}/completion', [ContractController::class, 'completion'])->name('contracts.completion');
        Route::post('contracts/{contract}/certificates', [ContractController::class, 'prepare'])->name('contracts.certificates.store');
        Route::post('payment-certificates/{certificate}/submit', [ContractController::class, 'submit'])->name('payment-certificates.submit');

        Route::get('projects/{project}/cashflow', [ReportsExportController::class, 'cashflow'])->name('projects.cashflow');
        Route::get('projects/{project}/performance', [PerformanceController::class, 'show'])->name('projects.performance');
        Route::get('exports', [ReportsExportController::class, 'index'])->name('exports.index');
        Route::get('exports/{type}', [ReportsExportController::class, 'download'])->name('exports.download');
    });

    // Workforce: employees, site allocation, leave and overtime (BCEA).
    Route::middleware('module:workforce')->group(function (): void {
        Route::get('workforce', [WorkforceController::class, 'index'])->name('workforce.index');
        Route::post('workforce', [WorkforceController::class, 'store'])->name('workforce.store');
        Route::get('workforce/{employee}', [WorkforceController::class, 'show'])->name('workforce.show');
        Route::post('workforce/{employee}/allocations', [WorkforceController::class, 'allocate'])->name('workforce.allocate');
        Route::post('workforce/{employee}/leave', [WorkforceController::class, 'requestLeave'])->name('workforce.leave');
        Route::post('workforce/{employee}/overtime', [WorkforceController::class, 'overtime'])->name('workforce.overtime');
        Route::patch('leave/{leave}', [WorkforceController::class, 'decideLeave'])->name('leave.decide');
        Route::post('workforce/{employee}/allowances', [WorkforceController::class, 'allowance'])->name('workforce.allowances.store');
        Route::post('allowances/{allowance}/end', [WorkforceController::class, 'endAllowance'])->name('allowances.end');
        Route::post('workforce/{employee}/documents', [WorkforceController::class, 'document'])->name('workforce.documents.store');
    });

    // Plant and equipment.
    Route::middleware('module:plant')->group(function (): void {
        Route::get('plant', [PlantController::class, 'index'])->name('plant.index');
        Route::post('plant', [PlantController::class, 'store'])->name('plant.store');
        Route::post('plant/{item}/events', [PlantController::class, 'event'])->name('plant.events.store');
    });

    // Company settings (Company Admin).
    Route::prefix('settings')->name('settings.')->group(function (): void {
        Route::get('profile', [ProfileController::class, 'show'])->name('profile');
        Route::post('profile/sign-out-others', [ProfileController::class, 'signOutOthers'])->name('profile.sign-out-others');
        Route::get('activity', [ActivityLogController::class, 'index'])->name('activity');
        Route::get('users', [CompanyUserController::class, 'index'])->name('users.index');
        Route::post('users', [CompanyUserController::class, 'store'])->name('users.store');
        Route::patch('users/{user:ulid}', [CompanyUserController::class, 'update'])->name('users.update');
    });
});

/*
| The public website. No sign-in: these pages are what visitors see. The company they belong to comes
| from configuration, pages are cached, and forms are rate-limited.
*/
Route::middleware([ResolvePublicCompany::class, 'throttle:website'])->group(function (): void {
    Route::get('/', [WebsiteController::class, 'home'])->name('website.home');
    Route::get('/developments', [WebsiteController::class, 'developments'])->name('website.developments');
    Route::get('/developments/{code}', [WebsiteController::class, 'development'])->name('website.development');
    Route::get('/news', [WebsiteController::class, 'articles'])->name('website.articles');
    Route::get('/news/{slug}', [WebsiteController::class, 'article'])->name('website.article');
    Route::get('/sitemap.xml', [WebsiteController::class, 'sitemap'])->name('website.sitemap');
    Route::post('/forms/{slug}', [WebsiteController::class, 'submit'])->middleware('throttle:website-forms')->name('website.forms.submit');
});

/*
| The tenant's own page, reached from the link given with their lease. No sign-in: the random token is
| the key, requests are rate-limited, and the page is not indexed.
*/
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/tenant/{token}', [MaintenanceController::class, 'portal'])->where('token', '[A-Za-z0-9]{48}')->name('tenant.portal');
    Route::post('/tenant/{token}/requests', [MaintenanceController::class, 'portalRequest'])->where('token', '[A-Za-z0-9]{48}')->name('tenant.requests');
});

/*
| Supplier quote link from a request for quotation email. No sign-in: the random token is the key,
| requests are rate-limited, and the page is not indexed.
*/
Route::middleware('throttle:30,1')->group(function (): void {
    Route::get('/quote/{token}', [RfqController::class, 'show'])->where('token', '[A-Za-z0-9]{48}')->name('rfq.respond');
    Route::post('/quote/{token}', [RfqController::class, 'submit'])->where('token', '[A-Za-z0-9]{48}')->name('rfq.submit');
    Route::post('/quote/{token}/decline', [RfqController::class, 'decline'])->where('token', '[A-Za-z0-9]{48}')->name('rfq.decline');
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

/*
| Any other address is looked up as a page in the content management system. This must stay the last
| route in the file, so it can never take an address the application itself uses.
*/
Route::middleware([ResolvePublicCompany::class, 'throttle:website'])
    ->get('/{slug}', [WebsiteController::class, 'page'])
    ->where('slug', '[a-z0-9][a-z0-9-]{0,120}')
    ->name('website.page');
