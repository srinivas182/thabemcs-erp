<?php

declare(strict_types=1);

namespace App\Domains\Platform\Console;

use App\Domains\Platform\Models\Company;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Builds a database the size the platform is meant to carry, so performance can be measured against
 * real volume rather than a guess.
 *
 * Writes with bulk inserts and no model events, which is why it is a separate command and never part of
 * the normal seeders. Safe to stop and run again: it adds to what is there.
 *
 *   php artisan scale:seed --projects=25000 --users=100000
 */
final class SeedScaleCommand extends Command
{
    protected $signature = 'scale:seed
        {--companies=8 : Operating companies to spread the work across}
        {--projects=25000 : Projects in total}
        {--users=100000 : Users in total}
        {--chunk=500 : Rows per insert}';

    protected $description = 'Create a full-size test database for performance testing';

    public function handle(CurrentCompany $context): int
    {
        if (app()->environment('production')) {
            $this->error('This is test data. It must never run in production.');

            return self::FAILURE;
        }

        $companies = (int) $this->option('companies');
        $projects = (int) $this->option('projects');
        $users = (int) $this->option('users');
        $chunk = (int) $this->option('chunk');
        $started = microtime(true);

        $this->info("Building {$companies} companies, {$projects} projects and {$users} users.");

        $companyIds = $this->companies($companies);
        $this->users($companyIds, $users, $chunk);
        $counts = $this->projects($context, $companyIds, $projects, $chunk);

        $this->newLine();
        $this->table(['What', 'Rows'], collect($counts)->map(static fn (int $n, string $k): array => [$k, number_format($n)])->values()->all());
        $this->info('Done in '.round((microtime(true) - $started) / 60, 1).' minutes.');
        $this->warn('Now refresh the stored figures: php artisan metrics:refresh (and run the queue workers).');

        return self::SUCCESS;
    }

    /**
     * @return list<int>
     */
    private function companies(int $wanted): array
    {
        $existing = Company::query()->pluck('id')->all();
        for ($i = count($existing); $i < $wanted; $i++) {
            $existing[] = (int) Company::query()->create([
                'name' => 'Thabekhulu Operating '.($i + 1),
                'legal_name' => 'Thabekhulu Operating '.($i + 1).' (Pty) Ltd',
                'registration_number' => '2020/'.random_int(100000, 999999).'/07',
                'status' => 'active',
            ])->id;
        }

        /** @var list<int> $ids */
        $ids = array_slice(array_map('intval', $existing), 0, $wanted);

        return $ids;
    }

    private function users(array $companyIds, int $wanted, int $chunk): void
    {
        $have = DB::table('users')->count();
        $toMake = max(0, $wanted - $have);
        if ($toMake === 0) {
            return;
        }

        $bar = $this->output->createProgressBar($toMake);
        $password = bcrypt('scale-test-password');

        for ($made = 0; $made < $toMake; $made += $chunk) {
            $rows = [];
            for ($i = 0; $i < min($chunk, $toMake - $made); $i++) {
                $n = $have + $made + $i;
                $rows[] = [
                    'ulid' => (string) Str::ulid(), 'company_id' => $companyIds[$n % count($companyIds)],
                    'name' => "Test Person {$n}", 'email' => "person{$n}@scale.test", 'password' => $password,
                    'is_active' => true, 'is_super_admin' => false, 'created_at' => now(), 'updated_at' => now(),
                ];
            }
            DB::table('users')->insert($rows);
            $bar->advance(count($rows));
        }
        $bar->finish();
        $this->newLine();
    }

    /**
     * Projects with the things that hang off them: budgets, orders, invoices, programme activities,
     * risks and site records, in roughly the proportions a real portfolio has.
     *
     * @param  list<int>  $companyIds
     * @return array<string, int>
     */
    private function projects(CurrentCompany $context, array $companyIds, int $wanted, int $chunk): array
    {
        $have = DB::table('projects')->count();
        $toMake = max(0, $wanted - $have);
        $counts = ['projects' => 0, 'budget_lines' => 0, 'purchase_orders' => 0, 'supplier_invoices' => 0, 'programme_activities' => 0, 'risks' => 0];
        if ($toMake === 0) {
            return $counts;
        }

        $bar = $this->output->createProgressBar($toMake);
        $suppliers = $this->suppliers($companyIds);

        for ($made = 0; $made < $toMake; $made += $chunk) {
            $batch = min($chunk, $toMake - $made);
            DB::transaction(function () use ($batch, $companyIds, $suppliers, $have, $made, &$counts): void {
                $projects = [];
                for ($i = 0; $i < $batch; $i++) {
                    $n = $have + $made + $i;
                    $company = $companyIds[$n % count($companyIds)];
                    $start = Carbon::today()->subDays(random_int(30, 1200));
                    $projects[] = [
                        'ulid' => (string) Str::ulid(), 'company_id' => $company, 'code' => 'SC-'.str_pad((string) $n, 6, '0', STR_PAD_LEFT),
                        'name' => 'Scale Project '.$n, 'stage' => 'construction', 'status' => $n % 9 === 0 ? 'on_hold' : 'active',
                        'town' => ['Ballito', 'Umhlanga', 'Richards Bay', 'Pietermaritzburg', 'Durban'][$n % 5],
                        'latitude' => -29.5 - ($n % 100) / 100, 'longitude' => 31.0 + ($n % 100) / 100,
                        'planned_start_date' => $start->toDateString(), 'planned_completion_date' => $start->copy()->addDays(random_int(200, 900))->toDateString(),
                        'created_at' => now(), 'updated_at' => now(),
                    ];
                }
                DB::table('projects')->insert($projects);
                $counts['projects'] += count($projects);

                $ids = DB::table('projects')->orderByDesc('id')->limit(count($projects))->pluck('company_id', 'id')->all();
                $lines = $orders = $invoices = $activities = $risks = [];

                foreach ($ids as $projectId => $companyId) {
                    foreach (['02.01' => 'Land', '05.01' => 'Building works', '05.03' => 'Concrete', '07.01' => 'Professional fees'] as $code => $description) {
                        $lines[] = ['company_id' => $companyId, 'project_id' => $projectId, 'code' => $code, 'description' => $description,
                            'original_amount' => random_int(500_000, 20_000_000), 'created_at' => now(), 'updated_at' => now()];
                    }
                    for ($o = 0; $o < 6; $o++) {
                        $subtotal = random_int(20_000, 900_000);
                        $orders[] = ['company_id' => $companyId, 'project_id' => $projectId, 'supplier_id' => $suppliers[$companyId][array_rand($suppliers[$companyId])],
                            'number' => $o + 1, 'status' => 'approved', 'vat_applies' => true, 'subtotal' => $subtotal, 'vat' => $subtotal * 0.15,
                            'total' => $subtotal * 1.15, 'created_by' => 1, 'created_at' => now(), 'updated_at' => now()];
                    }
                    for ($v = 0; $v < 8; $v++) {
                        $subtotal = random_int(5_000, 400_000);
                        $invoices[] = ['company_id' => $companyId, 'project_id' => $projectId, 'supplier_id' => $suppliers[$companyId][array_rand($suppliers[$companyId])],
                            'invoice_number' => 'SC-'.$projectId.'-'.$v, 'invoice_date' => Carbon::today()->subDays(random_int(1, 400))->toDateString(),
                            'due_date' => Carbon::today()->addDays(random_int(1, 30))->toDateString(), 'subtotal' => $subtotal, 'vat' => $subtotal * 0.15,
                            'total' => $subtotal * 1.15, 'status' => ['approved', 'paid', 'scheduled'][$v % 3], 'captured_by' => 1, 'created_at' => now(), 'updated_at' => now()];
                    }
                    for ($a = 0; $a < 20; $a++) {
                        $activities[] = ['company_id' => $companyId, 'project_id' => $projectId, 'name' => 'Activity '.($a + 1),
                            'planned_start' => Carbon::today()->subDays(300 - $a * 10)->toDateString(), 'duration_days' => random_int(3, 25),
                            'percent_complete' => min(100, $a * 5), 'sort' => $a, 'created_at' => now(), 'updated_at' => now()];
                    }
                    for ($r = 0; $r < 3; $r++) {
                        $risks[] = ['company_id' => $companyId, 'project_id' => $projectId, 'title' => 'Risk '.($r + 1), 'kind' => 'risk',
                            'likelihood' => random_int(1, 5), 'impact' => random_int(1, 5), 'status' => 'open', 'created_at' => now(), 'updated_at' => now()];
                    }
                }

                foreach ([['budget_lines', $lines], ['purchase_orders', $orders], ['supplier_invoices', $invoices], ['programme_activities', $activities], ['risks', $risks]] as [$table, $rows]) {
                    foreach (array_chunk($rows, 1000) as $slice) {
                        DB::table($table)->insert($slice);
                    }
                    $counts[$table] += count($rows);
                }
            });
            $bar->advance($batch);
        }
        $bar->finish();

        return $counts;
    }

    /**
     * @param  list<int>  $companyIds
     * @return array<int, list<int>>
     */
    private function suppliers(array $companyIds): array
    {
        $byCompany = [];
        foreach ($companyIds as $companyId) {
            $existing = DB::table('suppliers')->where('company_id', $companyId)->pluck('id')->all();
            for ($i = count($existing); $i < 40; $i++) {
                $existing[] = DB::table('suppliers')->insertGetId([
                    'ulid' => (string) Str::ulid(), 'company_id' => $companyId, 'name' => "Scale Supplier {$companyId}-{$i}",
                    'type' => ['supplier', 'contractor', 'subcontractor'][$i % 3], 'status' => 'active', 'created_at' => now(), 'updated_at' => now(),
                ]);
            }
            $byCompany[$companyId] = array_map('intval', $existing);
        }

        return $byCompany;
    }
}
