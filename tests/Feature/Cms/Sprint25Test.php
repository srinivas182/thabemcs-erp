<?php

declare(strict_types=1);

use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsMedia;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Models\Company;
use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\Buyer;
use App\Domains\Sales\Models\SaleUnit;
use Database\Seeders\DemoWebsiteSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function (): void {
    Storage::fake('public');
    $this->seed(RoleSeeder::class);
    $this->company = Company::factory()->create(['name' => 'Thabekhulu Developments', 'status' => 'active']);
    $this->marketing = userWithRole($this->company, Role::Marketing);
    config(['cms.company' => $this->company->ulid]);
    inCompany($this->company, fn () => $this->seed(DemoWebsiteSeeder::class));
});

it('serves the landing page to anybody, with no sign-in', function (): void {
    $response = $this->get('/');

    $response->assertOk()
        ->assertSee('Homes and places that hold their value', false)
        ->assertSee('Thabekhulu Developments', false)
        // The staff sign-in is available but is not what the page is about.
        ->assertSee('Staff sign in', false);

    // The back office is still behind the login.
    $this->get('/my-day')->assertRedirect('/login');
});

it('shows the pages, articles and sitemap the content management system holds', function (): void {
    $this->get('/about-us')->assertOk()->assertSee('Our story', false);
    $this->get('/what-we-do')->assertOk()->assertSee('Are your homes NHBRC enrolled?', false);
    $this->get('/news')->assertOk()->assertSee('What to ask before you buy off plan', false);
    $this->get('/news/what-to-ask-before-you-buy-off-plan')->assertOk()->assertSee('attorney', false);

    $this->get('/sitemap.xml')->assertOk()->assertSee('/about-us', false);
});

it('does not show a draft page to the public', function (): void {
    $page = inCompany($this->company, fn () => CmsPage::query()->where('slug', 'about-us')->sole());
    $this->actingAs($this->marketing)->post("/website/pages/{$page->ulid}/unpublish")->assertSessionHas('success');

    $this->get('/about-us')->assertNotFound();
    $this->get('/no-such-page')->assertNotFound();
});

it('shows what is actually available, straight from the stock schedule', function (): void {
    inCompany($this->company, function (): void {
        $project = Project::factory()->create(['code' => 'BH-01', 'name' => 'Ballito Heights', 'town' => 'Ballito', 'status' => 'active']);
        foreach ([['A1', 'available', 1_495_000], ['A2', 'available', 1_650_000], ['A3', 'sold', 1_600_000]] as [$reference, $status, $price]) {
            SaleUnit::query()->create([
                'project_id' => $project->id, 'reference' => $reference, 'type' => 'house', 'list_price' => $price,
                'tenure' => 'sale', 'status' => $status, 'bedrooms' => 3,
            ]);
        }
    });

    $this->get('/developments')->assertOk()
        ->assertSee('Ballito Heights', false)
        ->assertSee('2 available', false)
        ->assertSee('from R1 495 000', false);

    $this->get('/developments/BH-01')->assertOk()
        ->assertSee('2 units still available', false)
        ->assertSee('A1', false)
        ->assertDontSee('A3', false);   // sold units are not advertised

    // Prices can be kept private without touching the pages.
    config(['cms.show_prices' => false]);
    $this->get('/developments/BH-01')->assertOk()->assertDontSee('R1 495 000', false);
});

it('turns a website enquiry into a buyer, and ignores robots', function (): void {
    $form = inCompany($this->company, fn () => CmsForm::query()->where('slug', 'enquire')->sole());

    $this->post("/forms/{$form->slug}", [
        'answers' => ['name' => 'Nomsa Dlamini', 'email' => 'nomsa@example.co.za', 'phone' => '0824567890', 'development' => 'Ballito Heights'],
        'consented' => '1', 'page' => 'https://example.co.za/developments',
    ])->assertSessionHas('sent');

    $buyer = inCompany($this->company, fn () => Buyer::query()->sole());
    expect($buyer->name)->toBe('Nomsa Dlamini')->and($buyer->source)->toBe('Website')->and($buyer->status)->toBe('enquiry');

    // Without consent, nothing is recorded.
    $this->post("/forms/{$form->slug}", [
        'answers' => ['name' => 'No Consent', 'email' => 'x@example.co.za', 'phone' => '0821111111'],
    ])->assertSessionHasErrors('consented');

    // The hidden field only a robot fills in is quietly ignored.
    $this->post("/forms/{$form->slug}", [
        'answers' => ['name' => 'Spam Bot', 'email' => 'bot@example.co.za', 'phone' => '0820000000'],
        'consented' => '1', 'website_url' => 'http://spam.example',
    ])->assertSessionHas('sent');

    expect(inCompany($this->company, fn () => Buyer::query()->count()))->toBe(1);
});

it('keeps the website out of the way of the application', function (): void {
    // A page can never take an address the application uses.
    $this->actingAs($this->marketing)->post('/website/pages', ['title' => 'Dashboard', 'template' => 'page']);
    $slug = inCompany($this->company, fn () => CmsPage::query()->where('title', 'Dashboard')->sole()->slug);
    expect($slug)->toBe('dashboard-page');

    // Signed-in staff still reach the back office; the public pages stay public.
    $this->actingAs($this->marketing)->get('/website/pages')->assertOk();
    $this->get('/')->assertOk();
});

it('fills the website with demonstration developments and their stock', function (): void {
    config(['cms.company' => $this->company->ulid]);

    $this->artisan('demo:developments')->assertSuccessful();

    inCompany($this->company, function (): void {
        expect(Project::query()->count())->toBe(4)
            ->and(SaleUnit::query()->count())->toBe(146)
            ->and(SaleUnit::query()->where('status', 'available')->count())->toBeGreaterThan(0)
            ->and(CmsMedia::query()->count())->toBe(4);
    });

    // And the public pages now show them, with what is actually still for sale.
    $this->get('/developments')->assertOk()->assertSee('Ballito Heights', false)->assertSee('available', false);
    $this->get('/')->assertOk()->assertSee('Ballito Heights', false);

    // Running it again changes nothing.
    $this->artisan('demo:developments')->assertSuccessful();
    inCompany($this->company, fn () => expect(Project::query()->count())->toBe(4));
});

it('sends someone who signs out to the public website', function (): void {
    $this->actingAs($this->marketing)->post('/logout')->assertRedirect('/');

    // And that address is a real page, not a sign-in redirect.
    $this->get('/')->assertOk()->assertSee('Staff sign in', false);
});

it('does not leave any app link pointing at the public site', function (): void {
    // The ERP moved off "/" when the website took it. A sidebar or button still pointing there would
    // fetch a plain page into the app, which Inertia cannot render.
    $layout = (string) file_get_contents(resource_path('js/layouts/app-layout.tsx'));

    expect($layout)->not->toContain('href="/"')
        ->and($layout)->toContain('href="/my-day"');
});

it('draws a cover illustration for each development, and can redraw them', function (): void {
    config(['cms.company' => $this->company->ulid]);
    $this->artisan('demo:developments')->assertSuccessful();

    $covers = inCompany($this->company, fn () => CmsMedia::query()->get());
    expect($covers)->toHaveCount(4);

    foreach ($covers as $cover) {
        expect($cover->width)->toBe(1600)->and($cover->height)->toBe(900)
            ->and($cover->mime_type)->toBe('image/jpeg')
            ->and($cover->bytes)->toBeGreaterThan(10_000);
        Storage::disk('public')->assertExists($cover->path);
    }

    // Redrawing adds new artwork without creating the developments again.
    $this->artisan('demo:developments', ['--images' => true])->assertSuccessful();
    inCompany($this->company, function (): void {
        expect(Project::query()->count())->toBe(4)
            ->and(CmsMedia::query()->count())->toBe(8);
    });
});

it('shows a chosen photograph for a development, and the illustration when there is none', function (): void {
    config(['cms.company' => $this->company->ulid]);
    $this->artisan('demo:developments')->assertSuccessful();

    $project = inCompany($this->company, fn () => Project::query()->where('name', 'Ballito Heights')->sole());
    $image = inCompany($this->company, fn () => CmsMedia::query()->latest('id')->first());

    // Nothing chosen yet: the page works and shows no photograph.
    $this->get('/developments/'.$project->code)->assertOk()->assertDontSee($image->url(), false);

    $this->actingAs($this->marketing)->put("/website/developments/{$project->ulid}", ['media' => $image->ulid])
        ->assertSessionHas('success');

    $this->get('/developments/'.$project->code)->assertOk()->assertSee($image->url(), false);
    $this->get('/developments')->assertOk()->assertSee($image->url(), false);

    // And it can be taken off again.
    $this->actingAs($this->marketing)->put("/website/developments/{$project->ulid}", ['media' => null])
        ->assertSessionHas('success');
    $this->get('/developments/'.$project->code)->assertOk()->assertDontSee($image->url(), false);
});

it('keeps development photographs to people who manage content', function (): void {
    config(['cms.company' => $this->company->ulid]);
    $this->artisan('demo:developments')->assertSuccessful();
    $project = inCompany($this->company, fn () => Project::query()->first());

    $siteManager = userWithRole($this->company, Role::SiteManager);
    $this->actingAs($siteManager)->get('/website/developments')->assertForbidden();
    $this->actingAs($siteManager)->put("/website/developments/{$project->ulid}", ['media' => null])->assertForbidden();
});
