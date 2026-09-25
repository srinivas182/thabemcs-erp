<?php

declare(strict_types=1);

use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsFormSubmission;
use App\Domains\Cms\Models\CmsMedia;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Cms\Models\CmsPageVersion;
use App\Domains\Cms\Services\FormSubmissionService;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Sales\Models\Buyer;
use Database\Seeders\RoleSeeder;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Testing\AssertableInertia as Assert;

beforeEach(function (): void {
    Storage::fake('public');
    Storage::fake('documents');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create();
    $this->marketing = userWithRole($this->company, Role::Marketing);
    $this->admin = userWithRole($this->company, Role::CompanyAdmin);
    $this->siteManager = userWithRole($this->company, Role::SiteManager);
});

function page($test, string $title = 'About us'): CmsPage
{
    $test->actingAs($test->marketing)->post('/website/pages', ['title' => $title, 'template' => 'page']);

    return inCompany($test->company, fn () => CmsPage::query()->where('title', $title)->sole());
}

it('creates a page as a draft, and only shows it publicly once published', function (): void {
    $page = page($this);
    expect($page->status)->toBe('draft')->and($page->slug)->toBe('about-us')->and($page->isLive())->toBeFalse();

    // A page with no content cannot be published.
    $this->actingAs($this->marketing)->post("/website/pages/{$page->ulid}/publish")
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'content'));

    $this->actingAs($this->marketing)->put("/website/pages/{$page->ulid}", [
        'title' => 'About us', 'slug' => 'about-us', 'template' => 'page', 'show_in_search' => true,
        'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'Building KwaZulu-Natal', 'subheading' => 'Since 2009']],
            ['type' => 'statistics', 'data' => ['items' => [['value' => '1 400', 'label' => 'Homes delivered']]]],
        ],
    ])->assertSessionHas('success');

    $this->actingAs($this->marketing)->post("/website/pages/{$page->ulid}/publish")->assertSessionHas('success');
    expect($page->fresh()->isLive())->toBeTrue();
});

it('throws away block types and fields it does not know, so nothing unexpected reaches the website', function (): void {
    $page = page($this);

    $this->actingAs($this->marketing)->put("/website/pages/{$page->ulid}", [
        'title' => 'About us', 'slug' => 'about-us', 'template' => 'page', 'blocks' => [
            ['type' => 'hero', 'data' => ['heading' => 'Real heading', 'onclick' => '<script>alert(1)</script>']],
            ['type' => 'not_a_real_block', 'data' => ['anything' => 'goes']],
        ],
    ]);

    $blocks = $page->fresh()->blocks;
    expect($blocks)->toHaveCount(1)
        ->and($blocks[0]['type'])->toBe('hero')
        ->and($blocks[0]['data'])->toHaveKey('heading')
        ->and($blocks[0]['data'])->not->toHaveKey('onclick');
});

it('keeps every version of a page so a change can be undone', function (): void {
    $page = page($this);
    $this->actingAs($this->marketing)->put("/website/pages/{$page->ulid}", [
        'title' => 'About us', 'slug' => 'about-us', 'template' => 'page',
        'blocks' => [['type' => 'intro', 'data' => ['body' => 'The words we want back']]],
    ]);
    $this->actingAs($this->marketing)->put("/website/pages/{$page->ulid}", [
        'title' => 'About us', 'slug' => 'about-us', 'template' => 'page',
        'blocks' => [['type' => 'intro', 'data' => ['body' => 'Someone replaced everything']]],
    ]);
    expect($page->fresh()->blocks[0]['data']['body'])->toBe('Someone replaced everything');

    $version = inCompany($this->company, fn () => CmsPageVersion::query()->where('cms_page_id', $page->id)
        ->get()->first(fn (CmsPageVersion $v): bool => ($v->blocks[0]['data']['body'] ?? '') === 'The words we want back'));

    $this->actingAs($this->marketing)->post("/website/pages/{$page->ulid}/restore/{$version->id}")->assertSessionHas('success');
    expect($page->fresh()->blocks[0]['data']['body'])->toBe('The words we want back');
});

it('will not take a web address the application already uses, or one already taken', function (): void {
    $this->actingAs($this->marketing)->post('/website/pages', ['title' => 'Login', 'template' => 'page']);
    $this->actingAs($this->marketing)->post('/website/pages', ['title' => 'Services', 'template' => 'page']);
    $this->actingAs($this->marketing)->post('/website/pages', ['title' => 'Services', 'template' => 'page']);

    $slugs = inCompany($this->company, fn () => CmsPage::query()->pluck('slug')->all());
    expect($slugs)->toContain('login-page')->toContain('services')->toContain('services-2')->not->toContain('login');
});

it('takes only images and PDFs into the media library', function (): void {
    $this->actingAs($this->marketing)->post('/website/media', [
        'file' => UploadedFile::fake()->image('development.jpg', 1600, 900), 'alt' => 'Ballito Heights from the north',
    ])->assertSessionHas('success');

    $media = inCompany($this->company, fn () => CmsMedia::query()->sole());
    expect($media->alt)->toBe('Ballito Heights from the north')->and($media->width)->toBe(1600);
    Storage::disk('public')->assertExists($media->path);

    $this->actingAs($this->marketing)->post('/website/media', ['file' => UploadedFile::fake()->create('macro.docx', 40)])
        ->assertSessionHas('error', fn (string $m) => str_contains($m, 'images and PDFs'));
});

it('turns a website enquiry into a buyer and tells the right people', function (): void {
    Notification::fake();
    $this->actingAs($this->marketing)->post('/website/forms', [
        'name' => 'Enquire about a home', 'creates' => 'buyer', 'recipients' => [$this->admin->ulid],
        'fields' => [
            ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
            ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
            ['name' => 'development', 'label' => 'Which development', 'type' => 'text', 'required' => false],
        ],
    ])->assertSessionHas('success');

    $form = inCompany($this->company, fn () => CmsForm::query()->sole());

    inCompany($this->company, fn () => app(FormSubmissionService::class)->submit($form, [
        'name' => 'Nomsa Dlamini', 'email' => 'nomsa@example.co.za', 'development' => 'Ballito Heights',
    ], true, '41.0.0.1', '/developments'));

    $buyer = inCompany($this->company, fn () => Buyer::query()->sole());
    expect($buyer->name)->toBe('Nomsa Dlamini')->and($buyer->status)->toBe('enquiry')->and($buyer->source)->toBe('Website')
        ->and(inCompany($this->company, fn () => CmsFormSubmission::query()->sole())->buyer_id)->toBe($buyer->id);
    Notification::assertSentTo($this->admin, SystemMessage::class);

    // Without consent, and without a required answer, nothing is recorded.
    inCompany($this->company, fn () => app(FormSubmissionService::class)->submit($form, ['name' => 'X', 'email' => 'x@example.co.za'], false, null, null));
})->throws(ValidationException::class);

it('lets marketing manage content but nothing else, and keeps others out', function (): void {
    $this->actingAs($this->marketing)->get('/website/pages')->assertOk()
        ->assertInertia(fn (Assert $page) => $page->component('cms/pages'));
    // Marketing can see the project register, which is what the website is about, but may not change
    // anything, and may not see money or people.
    $this->actingAs($this->marketing)->post('/projects', ['name' => 'Theirs', 'code' => 'X-1'])->assertForbidden();
    $this->actingAs($this->marketing)->get('/reports/cost-report')->assertForbidden();
    $this->actingAs($this->marketing)->get('/workforce')->assertForbidden();

    $this->actingAs($this->siteManager)->get('/website/pages')->assertForbidden();
    $this->actingAs($this->siteManager)->post('/website/pages', ['title' => 'Sneaky', 'template' => 'page'])->assertForbidden();
});
