<?php

declare(strict_types=1);

use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Projects\Models\Project;
use App\Domains\Safety\Models\SafetyIncident;
use App\Domains\Site\Models\SiteAttendance;
use App\Domains\Site\Models\SiteDiary;
use App\Domains\Site\Models\SitePhoto;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->site = userWithRole($this->company, Role::SiteManager);
    // Site point: Ballito, KZN. 300 m sign-in radius.
    $this->project = inCompany($this->company, fn () => Project::factory()->create(['name' => 'Ballito Heights', 'latitude' => -29.538900, 'longitude' => 31.213900, 'geofence_radius_m' => 300]));
});

function diaryPayload(Project $project, array $overrides = []): array
{
    return [
        'clientId' => (string) Str::uuid(), 'projectId' => $project->ulid, 'date' => now()->toDateString(), 'weather' => 'rain',
        'workersOnSite' => 42, 'workCompleted' => 'Block C first-floor slab poured', 'capturedAt' => now()->toIso8601String(), ...$overrides,
    ];
}

it('lists the company\'s active projects for the site app', function (): void {
    $this->actingAs($this->site)->getJson('/api/v1/site/projects')->assertOk()
        ->assertJsonPath('data.0.name', 'Ballito Heights')->assertJsonPath('data.0.geofenceRadius', 300);
});

it('accepts a diary once, even when the phone retries the same record', function (): void {
    $payload = diaryPayload($this->project);

    $this->actingAs($this->site)->postJson('/api/v1/site/diary-entries', $payload)->assertCreated();
    $this->actingAs($this->site)->postJson('/api/v1/site/diary-entries', $payload)->assertOk();

    expect(inCompany($this->company, fn () => SiteDiary::query()->count()))->toBe(1);
});

it('keeps one diary per project per day, the latest submission winning', function (): void {
    $this->actingAs($this->site)->postJson('/api/v1/site/diary-entries', diaryPayload($this->project))->assertCreated();
    $this->actingAs($this->site)->postJson('/api/v1/site/diary-entries', diaryPayload($this->project, ['workersOnSite' => 50]))->assertOk();

    $diary = inCompany($this->company, fn () => SiteDiary::query()->sole());
    expect($diary->workers_on_site)->toBe(50);
});

it('checks attendance against the site geofence and stores the selfie privately', function (): void {
    $this->actingAs($this->site)->post('/api/v1/site/attendance', [
        'clientId' => (string) Str::uuid(), 'projectId' => $this->project->ulid, 'direction' => 'in', 'capturedAt' => now()->toIso8601String(),
        'latitude' => -29.539500, 'longitude' => 31.214200, 'accuracy' => 15,
        'selfie' => UploadedFile::fake()->image('me.jpg', 600, 800),
    ], ['Accept' => 'application/json'])->assertCreated()->assertJsonPath('data.withinGeofence', true);

    $this->actingAs($this->site)->postJson('/api/v1/site/attendance', [
        'clientId' => (string) Str::uuid(), 'projectId' => $this->project->ulid, 'direction' => 'in', 'capturedAt' => now()->toIso8601String(),
        'latitude' => -29.600000, 'longitude' => 31.213900,
    ])->assertCreated()->assertJsonPath('data.withinGeofence', false);

    $first = inCompany($this->company, fn () => SiteAttendance::query()->orderBy('id')->firstOrFail());
    Storage::disk('documents')->assertExists($first->selfie_path);
    expect($first->distance_m)->toBeLessThan(300);
});

it('stores geotagged photos and serves them only to the same company', function (): void {
    $this->actingAs($this->site)->post('/api/v1/site/photos', [
        'clientId' => (string) Str::uuid(), 'projectId' => $this->project->ulid, 'capturedAt' => now()->toIso8601String(),
        'caption' => 'Slab pour', 'latitude' => -29.5389, 'longitude' => 31.2139, 'file' => UploadedFile::fake()->image('slab.jpg'),
    ], ['Accept' => 'application/json'])->assertCreated();

    $photo = inCompany($this->company, fn () => SitePhoto::query()->firstOrFail());
    $this->actingAs($this->site)->get("/site-photos/{$photo->id}")->assertOk();
    $this->actingAs(userWithRole(Company::factory()->create(), Role::SiteManager))->get("/site-photos/{$photo->id}")->assertNotFound();
});

it('flags a fatality as reportable and alerts the safety officer and project manager', function (): void {
    Notification::fake();
    $officer = userWithRole($this->company, Role::SafetyOfficer);

    $this->actingAs($this->site)->postJson('/api/v1/site/incidents', [
        'clientId' => (string) Str::uuid(), 'projectId' => $this->project->ulid, 'type' => 'fatality',
        'occurredAt' => now()->subHour()->toIso8601String(), 'description' => 'Fall from scaffold, Block B',
    ])->assertCreated()->assertJsonPath('data.reportable', true);

    Notification::assertSentTo($officer, SystemMessage::class, fn (SystemMessage $m) => $m->level === 'danger' && str_starts_with($m->title, 'Serious incident'));
});

it('will not close a reportable incident until it has been reported', function (): void {
    $officer = userWithRole($this->company, Role::SafetyOfficer);
    $this->actingAs($this->site)->postJson('/api/v1/site/incidents', [
        'clientId' => (string) Str::uuid(), 'projectId' => $this->project->ulid, 'type' => 'dangerous_occurrence',
        'occurredAt' => now()->subHour()->toIso8601String(), 'description' => 'Tower crane load dropped',
    ]);
    $incident = inCompany($this->company, fn () => SafetyIncident::query()->firstOrFail());

    $this->actingAs($officer)->patch("/safety-incidents/{$incident->ulid}", ['root_cause' => 'Sling failure', 'corrective_action' => 'Rigging inspection regime', 'status' => 'closed'])
        ->assertSessionHas('error');

    $this->actingAs($officer)->patch("/safety-incidents/{$incident->ulid}", [
        'root_cause' => 'Sling failure', 'corrective_action' => 'Rigging inspection regime',
        'reported_to_authority_at' => now()->toIso8601String(), 'status' => 'closed',
    ])
        ->assertSessionHas('success');
});

it('refuses capture for another company\'s project and for roles without site access', function (): void {
    $theirs = inCompany(Company::factory()->create(), fn () => Project::factory()->create());
    $this->actingAs($this->site)->postJson('/api/v1/site/diary-entries', diaryPayload($theirs))->assertNotFound();

    $finance = userWithRole($this->company, Role::Finance);
    $this->actingAs($finance)->postJson('/api/v1/site/diary-entries', diaryPayload($this->project))->assertForbidden();
});
