<?php

declare(strict_types=1);

use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    $this->user = User::factory()->forCompany(Company::factory()->create())->create();
    $this->user->notify(new SystemMessage('Supplier document expiring', 'COIDA letter for Ndlovu Plant Hire expires in 7 days.', level: 'warning'));
    $this->user->notify(new SystemMessage('Welcome', 'Your account is ready.'));
});

it('shares the unread count with every page', function (): void {
    $this->actingAs($this->user)->get('/my-day')->assertInertia(fn (Assert $page) => $page->where('notifications.unread', 2));
});

it('lists notifications newest first', function (): void {
    $this->actingAs($this->user)->get('/notifications')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('notifications/index')->has('notifications.data', 2));
});

it('marks one or all notifications as read', function (): void {
    $first = $this->user->notifications()->firstOrFail();

    $this->actingAs($this->user)->post("/notifications/{$first->id}/read");
    expect($this->user->unreadNotifications()->count())->toBe(1);

    $this->actingAs($this->user)->post('/notifications/read-all');
    expect($this->user->unreadNotifications()->count())->toBe(0);
});

it('cannot read someone else\'s notification', function (): void {
    $other = User::factory()->forCompany(Company::factory()->create())->create();
    $theirs = $this->user->notifications()->firstOrFail();

    $this->actingAs($other)->post("/notifications/{$theirs->id}/read")->assertNotFound();
});
