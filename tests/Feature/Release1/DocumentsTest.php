<?php

declare(strict_types=1);

use App\Domains\Documents\Models\Document;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['max_storage_mb' => 1]);
    $this->pm = userWithRole($this->company, Role::ProjectManager);
    $this->project = inCompany($this->company, fn () => Project::factory()->create());
});

function uploadDrawing($test, array $overrides = []): void
{
    $test->actingAs($test->pm)->post('/documents', [
        'file' => UploadedFile::fake()->create('A-101.pdf', 200, 'application/pdf'),
        'project' => $test->project->ulid, 'folder' => 'Drawings/Architectural', 'category' => 'drawing',
        'drawing_number' => 'A-101', 'drawing_discipline' => 'Architectural', 'revision' => 'A', 'title' => 'Ground floor plan',
        ...$overrides,
    ])->assertSessionHas('success');
}

it('uploads a drawing and adds a new revision as version 2', function (): void {
    uploadDrawing($this);
    $doc = inCompany($this->company, fn () => Document::query()->firstOrFail());

    $this->actingAs($this->pm)->post("/documents/{$doc->ulid}/versions", [
        'file' => UploadedFile::fake()->create('A-101-B.pdf', 150, 'application/pdf'), 'revision' => 'B', 'notes' => 'Stair moved',
    ])->assertSessionHas('success', 'Version 2 uploaded.');

    $this->actingAs($this->pm)->get("/documents?project={$this->project->ulid}&category=drawing")
        ->assertInertia(fn (Assert $page) => $page->component('documents/index')
            ->has('documents', 1)
            ->where('documents.0.versions.0.revision', 'B')
            ->where('folders.Drawings/Architectural', 1));
});

it('streams downloads only after a permission check and logs them', function (): void {
    uploadDrawing($this, ['restricted_to_roles' => ['finance']]);
    $doc = inCompany($this->company, fn () => Document::query()->with('latestVersion')->firstOrFail());
    $url = "/documents/{$doc->ulid}/versions/{$doc->latestVersion->id}/download";

    $this->actingAs(userWithRole($this->company, Role::Finance))->get($url)->assertOk()->assertDownload('A-101.pdf');
    $this->actingAs(userWithRole($this->company, Role::SiteManager))->get($url)->assertNotFound();
    $this->actingAs(userWithRole(Company::factory()->create(), Role::CompanyAdmin))->get($url)->assertNotFound();
});

it('enforces the company storage limit', function (): void {
    uploadDrawing($this);

    $this->actingAs($this->pm)->post('/documents', [
        'file' => UploadedFile::fake()->create('big.pdf', 1000, 'application/pdf'),
        'project' => $this->project->ulid, 'folder' => 'Reports', 'category' => 'report',
    ])->assertSessionHas('error');
});

it('rejects unsafe file types and bad folder names', function (): void {
    $this->actingAs($this->pm)->post('/documents', [
        'file' => UploadedFile::fake()->create('run.exe', 10, 'application/x-msdownload'),
        'folder' => '../../etc', 'category' => 'other',
    ])->assertSessionHasErrors(['file', 'folder']);
});
