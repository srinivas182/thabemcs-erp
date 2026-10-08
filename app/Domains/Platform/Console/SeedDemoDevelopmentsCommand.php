<?php

declare(strict_types=1);

namespace App\Domains\Platform\Console;

use App\Domains\Cms\Models\CmsMedia;
use App\Domains\Cms\Models\CmsPage;
use App\Domains\Platform\Models\Company;
use App\Domains\Platform\Support\CoverArt;
use App\Domains\Projects\Models\Project;
use App\Domains\Sales\Models\SaleUnit;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Four believable developments with their stock, so the public website has something real to show, plus
 * placeholder images generated on the spot.
 *
 * The images are drawn illustrations, not photographs. Putting somebody else's buildings under this
 * developer's name would mislead buyers, so the demonstration site uses artwork until the client supplies
 * their own photography - replace them under Website - Media.
 *
 *   php artisan demo:developments
 */
final class SeedDemoDevelopmentsCommand extends Command
{
    protected $signature = 'demo:developments
        {--company= : Company ULID; defaults to the website company}
        {--images : Only redraw the cover illustrations for developments that already exist}';

    protected $description = 'Create demonstration developments, units and placeholder images';

    /** @var list<array{name: string, town: string, stage: string, status: string, blurb: string, units: int, sold: int, type: string, from: int, step: int, beds: int, size: int, palette: string}> */
    private const array DEVELOPMENTS = [
        ['name' => 'Ballito Heights', 'town' => 'Ballito', 'stage' => 'build', 'status' => 'active',
            'blurb' => 'Forty-eight sectional title homes on the ridge above Ballito, ten minutes from the beach and the schools. Solar ready, fibre to every unit, and a communal park at the centre of the estate.',
            'units' => 48, 'sold' => 31, 'type' => 'sectional_unit', 'from' => 1_495_000, 'step' => 55_000, 'beds' => 3, 'size' => 128, 'palette' => 'dawn'],
        ['name' => 'Umhlanga Ridge Terraces', 'town' => 'Umhlanga', 'stage' => 'sell_rent', 'status' => 'active',
            'blurb' => 'Twenty-two apartments within walking distance of the Umhlanga business district. Built for people who want to stop commuting, and for investors letting to the same.',
            'units' => 22, 'sold' => 9, 'type' => 'sectional_unit', 'from' => 2_150_000, 'step' => 90_000, 'beds' => 2, 'size' => 96, 'palette' => 'dusk'],
        ['name' => 'Salt Rock Erven', 'town' => 'Salt Rock', 'stage' => 'sell_rent', 'status' => 'active',
            'blurb' => 'Sixteen serviced erven for people who would rather build their own. Water, sewer, power and fibre to the boundary, with an approved architectural guideline.',
            'units' => 16, 'sold' => 11, 'type' => 'erf', 'from' => 895_000, 'step' => 40_000, 'beds' => 0, 'size' => 640, 'palette' => 'day'],
        ['name' => 'Tongaat Family Homes', 'town' => 'Tongaat', 'stage' => 'close', 'status' => 'completed',
            'blurb' => 'Sixty freestanding family homes delivered in 2025, all NHBRC enrolled and fully transferred. Our largest completed development to date.',
            'units' => 60, 'sold' => 60, 'type' => 'house', 'from' => 1_150_000, 'step' => 35_000, 'beds' => 3, 'size' => 112, 'palette' => 'evening'],
    ];

    public function handle(CurrentCompany $context): int
    {
        $company = $this->option('company') !== null
            ? Company::query()->where('ulid', $this->option('company'))->firstOrFail()
            : $this->websiteCompany();

        $this->info("Adding demonstration developments to {$company->name}.");

        $context->runFor($company, function (): void {
            $author = User::query()->orderBy('id')->firstOrFail();

            foreach (self::DEVELOPMENTS as $seed) {
                $existing = Project::query()->where('name', $seed['name'])->first();

                if ($existing !== null) {
                    if ($this->option('images')) {
                        $media = $this->placeholder($seed['name'], $seed['palette'], $author);
                        $this->line("  {$seed['name']}: new cover drawn ({$media->file_name}).");
                    } else {
                        $this->line("  {$seed['name']} is already there, skipping. Use --images to redraw its cover.");
                    }

                    continue;
                }

                DB::transaction(function () use ($seed, $author): void {
                    $project = Project::query()->create([
                        'code' => strtoupper(Str::substr(str_replace(' ', '', $seed['name']), 0, 3)).'-01',
                        'name' => $seed['name'], 'description' => $seed['blurb'], 'town' => $seed['town'],
                        'stage' => $seed['stage'], 'status' => $seed['status'],
                        'planned_start_date' => now()->subMonths(random_int(8, 30))->toDateString(),
                        'planned_completion_date' => now()->addMonths(random_int(2, 14))->toDateString(),
                    ]);

                    for ($i = 1; $i <= $seed['units']; $i++) {
                        SaleUnit::query()->create([
                            'project_id' => $project->id,
                            'reference' => $seed['type'] === 'erf' ? 'Erf '.(100 + $i) : sprintf('Unit %s%02d', chr(65 + intdiv($i - 1, 12)), (($i - 1) % 12) + 1),
                            'type' => $seed['type'],
                            'size_m2' => $seed['size'] + random_int(-8, 14),
                            'bedrooms' => $seed['beds'] > 0 ? $seed['beds'] : null,
                            'list_price' => $seed['from'] + ($i - 1) * $seed['step'],
                            'vat_applies' => true,
                            'tenure' => 'sale',
                            'status' => $i <= $seed['sold'] ? 'sold' : ($i === $seed['sold'] + 1 ? 'reserved' : 'available'),
                        ]);
                    }

                    $media = $this->placeholder($seed['name'], $seed['palette'], $author);
                    $this->line(sprintf('  %s: %d units, %d available. Image: %s',
                        $seed['name'], $seed['units'], max(0, $seed['units'] - $seed['sold'] - 1), $media->file_name));
                });
            }

            $this->hero($author, (bool) $this->option('images'));
        });

        $this->newLine();
        $this->warn('These are drawn illustrations, not photographs. Replace them with the client\'s own photography under Website - Media.');
        $this->line('Then refresh the stored figures: php artisan metrics:refresh');

        return self::SUCCESS;
    }

    private function websiteCompany(): Company
    {
        $wanted = (string) config('cms.company', '');

        $company = $wanted !== ''
            ? Company::query()->where('ulid', $wanted)->first()
            : null;

        if ($company !== null) {
            return $company;
        }

        // The company holding the website's home page, so this lands where the site actually reads from.
        $page = CmsPage::query()->withoutGlobalScopes()->where('is_home', true)->first();

        return $page !== null
            ? Company::query()->whereKey($page->getAttribute('company_id'))->firstOrFail()
            : Company::query()->where('status', 'active')->orderBy('id')->firstOrFail();
    }

    /**
     * A drawn cover illustration for the development, saved into the media library.
     */
    private function placeholder(string $name, string $palette, User $author): CmsMedia
    {
        $temp = (new CoverArt)->draw($palette, crc32($name));
        $path = 'website/'.now()->format('Y/m').'/'.Str::slug($name).'-cover-'.Str::lower(Str::random(5)).'.jpg';

        Storage::disk((string) config('cms.disk', 'public'))->put($path, (string) file_get_contents($temp));
        $size = (int) filesize($temp);
        @unlink($temp);

        return CmsMedia::query()->create([
            'path' => $path, 'file_name' => Str::slug($name).'-cover.jpg', 'mime_type' => 'image/jpeg',
            'bytes' => $size, 'width' => 1600, 'height' => 900,
            'alt' => 'Illustration of '.$name, 'uploaded_by' => $author->id,
        ]);
    }

    /** Puts the first cover behind the home page hero, so the landing page is not bare. */
    private function hero(User $author, bool $replace = false): void
    {
        $page = CmsPage::query()->where('is_home', true)->first();
        $media = CmsMedia::query()->latest('id')->first();

        if ($page === null || $media === null) {
            return;
        }

        $blocks = $page->blocks;
        foreach ($blocks as $i => $block) {
            if ($block['type'] === 'hero' && ($replace || empty($block['data']['image']))) {
                $blocks[$i]['data']['image'] = $media->url();
                $page->update(['blocks' => $blocks, 'updated_by' => $author->id]);
                $this->line('  Home page hero now uses a cover illustration.');

                return;
            }
        }
    }
}
