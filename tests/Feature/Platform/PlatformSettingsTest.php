<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Models\PlatformSetting;
use App\Models\User;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->superAdmin = User::factory()->superAdmin()->create();
    $this->director = userWithRole($this->company, Role::Director);
});

it('leaves two-factor optional until somebody turns it on', function (): void {
    expect(PlatformSetting::requiresTwoFactor())->toBeFalse();

    // Nobody is challenged, whatever their role.
    $this->director->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $this->actingAs($this->director->fresh())->get('/projects')->assertOk();
});

it('lets a super admin require two-factor, which then applies to everyone including themselves', function (): void {
    $this->actingAs($this->superAdmin)->get('/platform/settings')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('platform/settings')
            ->where('settings.twoFactorRequired', false)
            ->has('people.total'));

    $this->actingAs($this->superAdmin)->put('/platform/settings', ['two_factor_required' => true])
        ->assertSessionHas('success');

    expect(PlatformSetting::requiresTwoFactor())->toBeTrue();

    // Everyone who has not set it up is sent to their profile to do so.
    $this->director->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $this->actingAs($this->director->fresh())->get('/projects')->assertRedirect('/settings/profile');

    // Including the Super Admin who switched it on.
    $this->superAdmin->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $this->actingAs($this->superAdmin->fresh())->get('/platform/companies')->assertRedirect('/settings/profile');

    // And once set up, work carries on as normal.
    $this->director->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();
    $this->actingAs($this->director->fresh())->get('/projects')->assertOk();
});

it('can be switched back off', function (): void {
    PlatformSetting::current()->update(['two_factor_required' => true]);

    $this->actingAs($this->superAdmin)->put('/platform/settings', ['two_factor_required' => false])
        ->assertSessionHas('success');

    $this->director->forceFill(['two_factor_secret' => null, 'two_factor_confirmed_at' => null])->save();
    $this->actingAs($this->director->fresh())->get('/projects')->assertOk();
});

it('keeps the setting away from everyone who is not a super admin', function (): void {
    $this->actingAs($this->director)->get('/platform/settings')->assertForbidden();
    $this->actingAs($this->director)->put('/platform/settings', ['two_factor_required' => false])->assertForbidden();

    $admin = userWithRole($this->company, Role::CompanyAdmin);
    $this->actingAs($admin)->get('/platform/settings')->assertForbidden();
});

it('records who changed it', function (): void {
    $this->actingAs($this->superAdmin)->put('/platform/settings', ['two_factor_required' => true]);

    expect(PlatformSetting::current()->updated_by)->toBe($this->superAdmin->id)
        ->and(DB::table('activity_log')->where('log_name', 'platform')->count())->toBe(1);
});

it('turns two-factor off for one person in a single request, with their password', function (): void {
    $this->director->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    // The wrong password changes nothing.
    $this->actingAs($this->director)->post('/settings/profile/two-factor/disable', ['password' => 'not-the-password'])
        ->assertSessionHasErrors('password');
    expect($this->director->fresh()->two_factor_confirmed_at)->not->toBeNull();

    $this->actingAs($this->director)->post('/settings/profile/two-factor/disable', ['password' => 'password'])
        ->assertSessionHas('success');
    expect($this->director->fresh()->two_factor_secret)->toBeNull()
        ->and($this->director->fresh()->two_factor_confirmed_at)->toBeNull();
});

it('refuses to turn two-factor off while the instance requires it', function (): void {
    PlatformSetting::current()->update(['two_factor_required' => true]);
    $this->director->forceFill(['two_factor_secret' => encrypt('s'), 'two_factor_confirmed_at' => now()])->save();

    $this->actingAs($this->director)->post('/settings/profile/two-factor/disable', ['password' => 'password'])
        ->assertSessionHas('error');

    expect($this->director->fresh()->two_factor_confirmed_at)->not->toBeNull();
});
