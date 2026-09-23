<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Services;

use App\Domains\MasterData\Models\CostCode;
use App\Domains\MasterData\Models\Unit;
use App\Support\Cache\CompanyCache;
use Illuminate\Support\Facades\DB;

/**
 * Gives each company a starting cost code library and list of units the first time they are needed.
 */
final class MasterDataService
{
    public function __construct(private readonly CompanyCache $cache) {}

    public function ensureDefaults(): void
    {
        DB::transaction(function (): void {
            if (! CostCode::query()->exists()) {
                /** @var list<array{0: string, 1: string, 2: string}> $codes */
                $codes = config('master_data.cost_codes');
                foreach ($codes as [$code, $description, $category]) {
                    CostCode::query()->create(['code' => $code, 'description' => $description, 'category' => $category, 'active' => true]);
                }
            }
            if (! Unit::query()->exists()) {
                /** @var list<array{0: string, 1: string}> $units */
                $units = config('master_data.units');
                foreach ($units as [$code, $name]) {
                    Unit::query()->create(['code' => $code, 'name' => $name]);
                }
            }
        });
    }

    /**
     * @return list<array{key: string, label: string}>
     */
    public function unitOptions(): array
    {
        // The unit list changes rarely and is read on every requisition and budget screen.
        return $this->cache->remember('master-data', 'units', 3600, function (): array {
            $this->ensureDefaults();

            return array_values(Unit::query()->orderBy('code')->get()->map(static fn (Unit $u): array => ['key' => $u->code, 'label' => "{$u->code} ({$u->name})"])->all());
        });
    }

    public function forget(): void
    {
        $this->cache->flush('master-data');
    }
}
