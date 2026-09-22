<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Module;
use App\Domains\Platform\Models\Company;
use App\Models\User;
use Illuminate\Support\Facades\Route;

beforeEach(function (): void {
    Route::middleware(['web', 'auth', 'module:finance'])
        ->get('/_test/finance', fn () => 'finance ok');
});

it('allows access to an enabled module', function (): void {
    $company = Company::factory()->withModules([Module::Projects, Module::Finance])->create();
    $user = User::factory()->forCompany($company)->create();

    $this->actingAs($user)->get('/_test/finance')->assertOk()->assertSee('finance ok');
});

it('blocks access to a module that is not enabled for the company', function (): void {
    $company = Company::factory()->withModules([Module::Projects])->create();
    $user = User::factory()->forCompany($company)->create();

    $this->actingAs($user)->get('/_test/finance')->assertForbidden();
});
