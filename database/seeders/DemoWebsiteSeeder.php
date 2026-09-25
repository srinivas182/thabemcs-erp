<?php

declare(strict_types=1);

namespace Database\Seeders;

use App\Domains\Cms\Models\CmsCategory;
use App\Domains\Cms\Models\CmsForm;
use App\Domains\Cms\Models\CmsMenu;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Cms\Models\CmsPost;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

/**
 * A complete website to start from: five pages, menus, two forms and two articles, written for a South
 * African property developer. Every word is meant to be replaced by the client's own - the point is that
 * the site looks finished on day one and marketing edits rather than stares at an empty page.
 *
 *   php artisan db:seed --class=DemoWebsiteSeeder
 */
class DemoWebsiteSeeder extends Seeder
{
    public function run(): void
    {
        $author = User::query()->orderBy('id')->firstOrFail();

        if (CmsPage::query()->exists()) {
            $this->command?->warn('The website already has pages. Nothing was changed.');

            return;
        }

        DB::transaction(function () use ($author): void {
            $enquiry = CmsForm::query()->create([
                'name' => 'Enquire about a home', 'slug' => 'enquire', 'creates' => 'buyer', 'active' => true,
                'success_message' => 'Thank you. One of our sales team will call you within one working day.',
                'fields' => [
                    ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => true],
                    ['name' => 'development', 'label' => 'Which development interests you', 'type' => 'text', 'required' => false],
                    ['name' => 'budget', 'label' => 'Your budget', 'type' => 'select', 'required' => false,
                        'options' => ['Under R1 million', 'R1m to R2m', 'R2m to R3.5m', 'Above R3.5m']],
                    ['name' => 'message', 'label' => 'Anything else we should know', 'type' => 'textarea', 'required' => false],
                ],
                'created_by' => $author->id,
            ]);

            CmsForm::query()->create([
                'name' => 'General contact', 'slug' => 'contact', 'creates' => 'none', 'active' => true,
                'success_message' => 'Thank you. We will come back to you shortly.',
                'fields' => [
                    ['name' => 'name', 'label' => 'Your name', 'type' => 'text', 'required' => true],
                    ['name' => 'email', 'label' => 'Email', 'type' => 'email', 'required' => true],
                    ['name' => 'phone', 'label' => 'Phone', 'type' => 'phone', 'required' => false],
                    ['name' => 'reason', 'label' => 'What is this about', 'type' => 'select', 'required' => true,
                        'options' => ['Buying a home', 'Renting', 'Selling us land', 'Working with us', 'Something else']],
                    ['name' => 'message', 'label' => 'Your message', 'type' => 'textarea', 'required' => true],
                ],
                'created_by' => $author->id,
            ]);

            $this->home($author);
            $this->about($author);
            $this->services($author);
            $this->landowners($author, $enquiry);
            $this->contact($author);

            CmsMenu::query()->create(['location' => 'primary', 'items' => [
                ['label' => 'Developments', 'link' => '/developments'],
                ['label' => 'What we do', 'link' => '/what-we-do'],
                ['label' => 'About us', 'link' => '/about-us'],
                ['label' => 'Landowners', 'link' => '/landowners'],
                ['label' => 'Contact', 'link' => '/contact'],
            ]]);
            CmsMenu::query()->create(['location' => 'footer', 'items' => [
                ['label' => 'Developments', 'link' => '/developments'],
                ['label' => 'About us', 'link' => '/about-us'],
                ['label' => 'News and insight', 'link' => '/news'],
                ['label' => 'Contact', 'link' => '/contact'],
            ]]);

            $insight = CmsCategory::query()->create(['name' => 'Insight', 'slug' => 'insight']);
            CmsCategory::query()->create(['name' => 'Developments', 'slug' => 'developments']);

            CmsPost::query()->create([
                'cms_category_id' => $insight->id, 'title' => 'What to ask before you buy off plan',
                'slug' => 'what-to-ask-before-you-buy-off-plan',
                'excerpt' => 'Buying a home before it is built is normal in South Africa, and safe when you ask the right questions.',
                'blocks' => [['type' => 'intro', 'data' => ['body' => "Buying off plan means signing for a home that does not exist yet. Done properly it is the best price you will get on a new home, because you are buying before the development is finished and the show house is busy.\n\nAsk three things before you sign. First, is the home enrolled with the NHBRC? Every new home must be, and it is your protection if something is wrong with the workmanship. Second, where is your deposit held? It should sit in an attorney's trust account, in your name, earning interest, until transfer. Third, what happens if the bond is declined? A properly drawn agreement makes the sale conditional on your bond being approved, and gives you your deposit back if it is not.\n\nThe answers should be in the agreement, not in a conversation. Any developer worth buying from will put them in writing without being asked."]]],
                'author_name' => 'Sales team', 'status' => 'published', 'published_at' => now()->subWeeks(2), 'created_by' => $author->id,
            ]);
            CmsPost::query()->create([
                'cms_category_id' => $insight->id, 'title' => 'Why we build in KwaZulu-Natal',
                'slug' => 'why-we-build-in-kwazulu-natal',
                'excerpt' => 'The north coast has grown for a decade, and the reasons behind it have not changed.',
                'blocks' => [['type' => 'intro', 'data' => ['body' => "The corridor between Umhlanga and Ballito has absorbed more residential development than anywhere else in the province, and the demand behind it is not speculative. People are moving for schools, for the airport, and for work that no longer needs to be done in a city centre.\n\nWhat has changed is what buyers want. Ten years ago the market wanted the largest house the plot allowed. Today it wants a well-built, well-insulated home with fibre, solar readiness and a garden somebody can actually maintain. We design for that, because it is what sells and what keeps its value.\n\nWe build where services already exist. A development is only as good as the bulk water, sewer and power that reach it, and that is the first thing we check before we buy a single square metre."]]],
                'author_name' => 'Development team', 'status' => 'published', 'published_at' => now()->subWeeks(6), 'created_by' => $author->id,
            ]);
        });

        $this->command?->info('A starter website has been created: five pages, two forms, two articles and the menus.');
        $this->command?->warn('Everything is placeholder copy. Replace it in the back office under Website.');
    }

    private function home(User $author): void
    {
        $this->page($author, 'Home', 'home', 'home', [
            ['type' => 'hero', 'data' => [
                'heading' => 'Homes and places that hold their value',
                'subheading' => 'We develop residential and mixed-use property on the KwaZulu-Natal north coast, from land assembly through to the day the keys change hands.',
                'button_label' => 'See what is available', 'button_link' => '/developments', 'overlay' => true,
            ]],
            ['type' => 'statistics', 'data' => ['items' => [
                ['value' => '1 400+', 'label' => 'Homes delivered'],
                ['value' => '15', 'label' => 'Years developing'],
                ['value' => '9', 'label' => 'Developments completed'],
                ['value' => '100%', 'label' => 'NHBRC enrolled'],
            ]]],
            ['type' => 'intro', 'data' => [
                'heading' => 'We do the whole job, not part of it',
                'body' => "Most developers hand you over to somebody else at every stage. We do not. The team that finds the land gets the approvals, appoints the contractor, watches the build and hands you the keys.\n\nThat is why our sites finish when we say they will, and why the people who buy from us come back.",
            ]],
            ['type' => 'developments', 'data' => ['heading' => 'Available now', 'show' => 'selling', 'limit' => 6, 'show_availability' => true]],
            ['type' => 'services', 'data' => ['heading' => 'What we do', 'items' => [
                ['title' => 'Land assembly', 'body' => 'We find and secure land where bulk services already exist, and we do the due diligence before we commit.'],
                ['title' => 'Town planning and approvals', 'body' => 'Rezoning, subdivision and building plans, taken through council by people who have done it before.'],
                ['title' => 'Development management', 'body' => 'Budget, programme and quality held in one place, so nothing is somebody else\'s problem.'],
                ['title' => 'Construction', 'body' => 'Main contractors appointed on merit and compliance, and watched weekly on site.'],
                ['title' => 'Sales and transfer', 'body' => 'From reservation to registration in the Deeds Office, with one person answering your calls.'],
                ['title' => 'Rentals and management', 'body' => 'Letting and managing completed units for owners who bought to invest.'],
            ]]],
            ['type' => 'testimonials', 'data' => ['items' => [
                ['quote' => 'They gave us a date and they met it. After two previous builds, that alone was worth the money.', 'name' => 'Nomsa D.', 'role' => 'Bought in 2026'],
                ['quote' => 'The snag list was done in a fortnight, without an argument. That tells you who you are dealing with.', 'name' => 'Rajesh P.', 'role' => 'Homeowner'],
                ['quote' => 'We sold them our family land. They did what they said they would do with it.', 'name' => 'The Mkhize family', 'role' => 'Landowners'],
            ]]],
            ['type' => 'call_to_action', 'data' => [
                'heading' => 'Looking for a home, or have land to sell?',
                'body' => 'Tell us what you need. Somebody who can actually answer will come back to you.',
                'button_label' => 'Talk to us', 'button_link' => '/contact',
            ]],
        ], true);
    }

    private function about(User $author): void
    {
        $this->page($author, 'About us', 'about-us', 'page', [
            ['type' => 'hero', 'data' => ['heading' => 'Built here, for people who live here', 'subheading' => 'A family-held development group working the KwaZulu-Natal coast since 2009.']],
            ['type' => 'text_image', 'data' => [
                'heading' => 'Our story', 'image_side' => 'right',
                'body' => "We started with one subdivision outside Ballito and a view that the north coast would keep growing. Fifteen years later we have delivered more than a thousand homes, and we still take the same approach: buy well, plan properly, build once.\n\nWe are small enough that the people who make decisions are on site, and large enough to carry a development from raw land to registration without handing it to anybody else.",
            ]],
            ['type' => 'process', 'data' => ['heading' => 'How a development works with us', 'steps' => [
                ['title' => 'We find and check the land', 'body' => 'Title, zoning, servitudes, services, geotech and environmental, before we commit a rand.'],
                ['title' => 'We test the numbers', 'body' => 'A full feasibility. If it does not work on paper it will not work on site.'],
                ['title' => 'We get the approvals', 'body' => 'Rezoning, subdivision and building plans through council, with the professional team appointed.'],
                ['title' => 'We build', 'body' => 'Contractors appointed on merit and compliance, progress measured weekly, quality signed off at every stage.'],
                ['title' => 'We hand over', 'body' => 'Snags closed, certificates issued, and transfer registered in your name.'],
            ]]],
            ['type' => 'team', 'data' => ['heading' => 'Who you will deal with', 'people' => [
                ['name' => 'Add a name', 'role' => 'Managing director', 'bio' => 'Replace this with a short biography. Two or three lines is plenty.'],
                ['name' => 'Add a name', 'role' => 'Development manager', 'bio' => 'Replace this with a short biography.'],
                ['name' => 'Add a name', 'role' => 'Head of sales', 'bio' => 'Replace this with a short biography.'],
            ]]],
            ['type' => 'call_to_action', 'data' => ['heading' => 'Come and see what we are building', 'button_label' => 'Our developments', 'button_link' => '/developments']],
        ]);
    }

    private function services(User $author): void
    {
        $this->page($author, 'What we do', 'what-we-do', 'page', [
            ['type' => 'hero', 'data' => ['heading' => 'From raw land to registered home', 'subheading' => 'Every stage of a development, handled by one team.']],
            ['type' => 'services', 'data' => ['heading' => 'Our services', 'items' => [
                ['title' => 'Land assembly and due diligence', 'body' => 'Thirteen checks before we buy: title deed, zoning, servitudes, rates clearance, environmental, geotech, bulk services, heritage and more.'],
                ['title' => 'Feasibility and funding', 'body' => 'A full appraisal and a funding structure that works for investors as well as for the development.'],
                ['title' => 'Statutory approvals', 'body' => 'Rezoning, subdivision, building plans, water use and environmental authorisations.'],
                ['title' => 'Professional team', 'body' => 'Architects, engineers, quantity surveyors and safety practitioners, each registered with their council.'],
                ['title' => 'Construction management', 'body' => 'Programme, budget, safety and quality, measured weekly and reported monthly.'],
                ['title' => 'Handover and aftercare', 'body' => 'Snags, certificates, NHBRC enrolment and the defects period, seen through properly.'],
            ]]],
            ['type' => 'faq', 'data' => ['heading' => 'Questions we are asked', 'items' => [
                ['question' => 'Are your homes NHBRC enrolled?', 'answer' => 'Yes. Every new home we build is enrolled with the National Home Builders Registration Council, which protects you against defects in workmanship.'],
                ['question' => 'How is my deposit held?', 'answer' => 'In the conveyancing attorney\'s trust account, in an interest-bearing account, until transfer is registered in your name.'],
                ['question' => 'What if my bond is declined?', 'answer' => 'Our agreements are conditional on bond approval within an agreed period. If the bond is declined, the sale falls away and your deposit is returned.'],
                ['question' => 'Can I make changes to a home bought off plan?', 'answer' => 'Usually yes, up to a point in the build. Changes are priced and agreed in writing before work starts.'],
                ['question' => 'Do you manage units after transfer?', 'answer' => 'We do. Many of our buyers are investors, and we let and manage their units for them.'],
            ]]],
        ]);
    }

    private function landowners(User $author, CmsForm $enquiry): void
    {
        $this->page($author, 'Landowners', 'landowners', 'page', [
            ['type' => 'hero', 'data' => ['heading' => 'Have land on the north coast?', 'subheading' => 'We buy, we joint venture, and we say no quickly when it will not work.']],
            ['type' => 'intro', 'data' => [
                'heading' => 'A straight answer, usually within a week',
                'body' => "If you own land between Umhlanga and Richards Bay, we are interested. We buy outright, and we partner with landowners who would rather share in what gets built than sell today.\n\nWe will tell you quickly if it does not work. Nobody benefits from a year of maybe.",
            ]],
            ['type' => 'process', 'data' => ['heading' => 'What happens next', 'steps' => [
                ['title' => 'You tell us about the land', 'body' => 'Erf number or farm portion, roughly how big, and what you would like to happen with it.'],
                ['title' => 'We check it', 'body' => 'Zoning, services, access and the obvious constraints. A few days, not months.'],
                ['title' => 'We come back with a number, or a no', 'body' => 'Either an offer and a structure, or the reasons it does not work for us.'],
            ]]],
            ['type' => 'form', 'data' => ['heading' => 'Tell us about your land', 'body' => 'A few details are enough to start.', 'form' => $enquiry->slug]],
        ]);
    }

    private function contact(User $author): void
    {
        $this->page($author, 'Contact', 'contact', 'contact', [
            ['type' => 'intro', 'data' => ['heading' => 'Talk to us', 'body' => 'Office hours are Monday to Friday, 08:00 to 17:00. Site visits by appointment.']],
            ['type' => 'form', 'data' => ['heading' => 'Send us a message', 'form' => 'contact']],
        ]);
    }

    /**
     * @param  list<array{type: string, data: array<string, mixed>}>  $blocks
     */
    private function page(User $author, string $title, string $slug, string $template, array $blocks, bool $isHome = false): void
    {
        CmsPage::query()->create([
            'title' => $title, 'slug' => $slug, 'template' => $template, 'blocks' => $blocks,
            'seo' => ['title' => $title, 'description' => null],
            'status' => 'published', 'published_at' => now(), 'is_home' => $isHome, 'show_in_search' => true,
            'version' => 1, 'created_by' => $author->id,
        ]);
    }
}
