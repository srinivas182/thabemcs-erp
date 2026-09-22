<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Feasibility\Enums\LineCategory;
use App\Domains\Feasibility\Models\Feasibility;
use App\Domains\Feasibility\Models\FeasibilityLine;
use App\Domains\Feasibility\Services\FeasibilityCalculator;
use App\Domains\Finance\Exceptions\FinanceException;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Finance\Models\VariationOrder;
use App\Domains\Platform\Enums\Role;
use App\Domains\Platform\Notifications\SystemMessage;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Projects\Models\Project;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;

/**
 * Project budgets by cost code: original budget, approved variations, commitments (approved purchase orders),
 * direct invoices and what is left. All figures excl. VAT.
 */
final class BudgetService
{
    /** Cost code prefixes follow the feasibility headings. */
    private const array PREFIX = [
        'land' => '01', 'acquisition' => '02', 'professional_fees' => '03', 'municipal' => '04', 'construction' => '05',
        'contingency' => '06', 'marketing' => '07', 'finance' => '08', 'other' => '09',
    ];

    /** Purchase order statuses that count as committed spend. */
    public const array COMMITTED = ['approved', 'issued', 'partially_received', 'received'];

    public function __construct(private readonly FeasibilityCalculator $calculator, private readonly ApprovalEngine $approvals) {}

    /**
     * @throws FinanceException
     */
    public function fromFeasibility(Project $project): int
    {
        if (BudgetLine::query()->where('project_id', $project->id)->exists()) {
            throw new FinanceException('This project already has a budget.');
        }

        $baseline = Feasibility::query()->with('lines')->where('project_id', $project->id)->where('is_baseline', true)->first()
            ?? throw new FinanceException('Approve a feasibility baseline first; the budget is created from it.');

        $lines = $baseline->lines->map(static fn (FeasibilityLine $l): array => [
            'category' => $l->category, 'basis' => $l->basis, 'amount' => $l->amount === null ? null : (float) $l->amount,
            'rate' => $l->rate === null ? null : (float) $l->rate, 'start_month' => $l->start_month, 'end_month' => $l->end_month,
        ])->values()->all();
        $values = $this->calculator->lineValues(array_values($lines));

        return DB::transaction(function () use ($baseline, $values, $project): int {
            $counters = [];
            $created = 0;
            foreach ($baseline->lines->values() as $i => $line) {
                if ($line->category === LineCategory::Revenue) {
                    continue;
                }
                $prefix = self::PREFIX[$line->category->value] ?? '09';
                $counters[$prefix] = ($counters[$prefix] ?? 0) + 1;

                BudgetLine::query()->create([
                    'project_id' => $project->id,
                    'code' => sprintf('%s.%02d', $prefix, $counters[$prefix]),
                    'description' => $line->description,
                    'category' => $line->category->value,
                    'original_amount' => $values[$i] ?? 0,
                    'sort' => $created,
                ]);
                $created++;
            }

            return $created;
        });
    }

    /**
     * Import or update cost codes from a CSV (e.g. a bill of quantities saved as CSV from Excel).
     * Columns: code, description, and either amount or quantity + rate.
     *
     * @return array{imported: int, errors: list<string>}
     */
    public function importCsv(Project $project, UploadedFile $file): array
    {
        $handle = fopen($file->getRealPath(), 'r');
        if ($handle === false) {
            throw new FinanceException('The file could not be read.');
        }

        $header = array_map(static fn ($h): string => strtolower(trim((string) $h, " \t\n\r\0\x0B\xEF\xBB\xBF")), fgetcsv($handle) ?: []);
        $col = array_flip($header);
        if (! isset($col['code'], $col['description']) || (! isset($col['amount']) && ! isset($col['quantity'], $col['rate']))) {
            fclose($handle);
            throw new FinanceException('The first row must name the columns: code, description, and amount (or quantity and rate).');
        }

        $imported = 0;
        $errors = [];
        $row = 1;
        DB::transaction(function () use ($handle, $col, $project, &$imported, &$errors, &$row): void {
            $sort = (int) BudgetLine::query()->where('project_id', $project->id)->max('sort');
            while (($data = fgetcsv($handle)) !== false) {
                $row++;
                $code = trim((string) ($data[$col['code']] ?? ''));
                $description = trim((string) ($data[$col['description']] ?? ''));
                if ($code === '' && $description === '') {
                    continue;
                }
                $amount = isset($col['amount'])
                    ? $this->number((string) ($data[$col['amount']] ?? ''))
                    : $this->number((string) ($data[$col['quantity']] ?? '')) * $this->number((string) ($data[$col['rate']] ?? ''));

                if ($code === '' || $description === '' || $amount < 0) {
                    $errors[] = "Row {$row}: needs a code, a description and an amount of zero or more.";

                    continue;
                }

                BudgetLine::query()->updateOrCreate(
                    ['project_id' => $project->id, 'code' => mb_substr($code, 0, 20)],
                    ['description' => mb_substr($description, 0, 255), 'original_amount' => round($amount, 2), 'sort' => ++$sort],
                );
                $imported++;
            }
        });
        fclose($handle);

        return ['imported' => $imported, 'errors' => $errors];
    }

    /**
     * @return list<array{id: int, code: string, description: string, original: float, variations: float, revised: float, committed: float, direct: float, available: float, used: float}>
     */
    public function summary(Project $project): array
    {
        $lines = BudgetLine::query()->where('project_id', $project->id)->orderBy('code')->get();

        return array_values($lines->map(fn (BudgetLine $line): array => $this->figures($line))->all());
    }

    /**
     * @return array{id: int, code: string, description: string, original: float, variations: float, revised: float, committed: float, direct: float, available: float, used: float}
     */
    public function figures(BudgetLine $line): array
    {
        $variations = (float) VariationOrder::query()->where('budget_line_id', $line->id)->where('status', 'approved')->sum('amount');
        $committed = (float) PurchaseOrder::query()->where('budget_line_id', $line->id)->whereIn('status', self::COMMITTED)->sum('subtotal');
        $direct = (float) SupplierInvoice::query()->where('budget_line_id', $line->id)->whereNull('purchase_order_id')
            ->whereIn('status', ['approved', 'scheduled', 'paid'])->sum('subtotal');
        $revised = (float) $line->original_amount + $variations;
        $spent = $committed + $direct;

        return [
            'id' => $line->id, 'code' => $line->code, 'description' => $line->description,
            'original' => (float) $line->original_amount, 'variations' => round($variations, 2), 'revised' => round($revised, 2),
            'committed' => round($committed, 2), 'direct' => round($direct, 2), 'available' => round($revised - $spent, 2),
            'used' => $revised > 0 ? round($spent / $revised * 100, 1) : ($spent > 0 ? 100.0 : 0.0),
        ];
    }

    /**
     * Warn the project manager and Finance when spend crosses 80%, 90% and 100% of a cost code (each once).
     */
    public function checkThresholds(BudgetLine $line): void
    {
        $used = $this->figures($line)['used'];
        /** @var list<int> $levels */
        $levels = config('delegation_of_authority.budget_alerts', [80, 90, 100]);
        $crossed = null;
        foreach ($levels as $level) {
            if ($used >= $level) {
                $crossed = $level;
            }
        }

        if ($crossed === null || (int) $line->alert_level >= $crossed) {
            return;
        }

        $line->forceFill(['alert_level' => (string) $crossed])->save();
        $project = $line->project;

        setPermissionsTeamId($project->company_id);
        $recipients = User::query()->where('company_id', $project->company_id)->where('is_active', true)
            ->where(fn ($q) => $q->whereKey($project->getAttribute('project_manager_id'))->orWhereHas('roles', fn ($r) => $r->where('name', Role::Finance->value)))
            ->get();

        $message = new SystemMessage(
            "Budget {$crossed}% used: {$line->code} {$line->description}",
            "{$project->name}: {$used}% of the revised budget is committed or spent.",
            route('projects.budget', $project),
            $crossed >= 100 ? 'danger' : 'warning',
        );
        $recipients->each(fn (User $u) => $u->notify($message));
    }

    /**
     * @param  array{budget_line_id: int, title: string, description?: string|null, reason: string, amount: float, time_impact_days?: int, site_instruction_id?: int|null}  $data
     *
     * @throws ApprovalException
     */
    public function raiseVariation(Project $project, array $data, User $by): VariationOrder
    {
        return DB::transaction(function () use ($project, $data, $by): VariationOrder {
            $variation = VariationOrder::query()->create([
                ...$data,
                'project_id' => $project->id,
                'number' => (int) VariationOrder::query()->where('project_id', $project->id)->lockForUpdate()->max('number') + 1,
                'status' => 'pending_approval',
                'requested_by' => $by->id,
            ]);
            $this->approvals->submit($variation, 'variation', abs((float) $data['amount']), $by);

            return $variation;
        });
    }

    private function number(string $value): float
    {
        // Accept "1 250 000,50", "R1,250,000.50" and "1250000.5".
        $clean = str_replace(['R', ' ', "\u{00A0}"], '', trim($value));
        if (preg_match('/^-?\d{1,3}(\.\d{3})*(,\d+)?$/', $clean) === 1) {
            $clean = str_replace(['.', ','], ['', '.'], $clean);
        } else {
            $clean = str_replace(',', '', $clean);
        }

        return is_numeric($clean) ? (float) $clean : -1.0;
    }
}
