<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Services;

use App\Domains\Closeout\Models\Distribution;
use App\Domains\Closeout\Models\DistributionLine;
use App\Domains\Closeout\Models\Reinvestment;
use App\Domains\Funding\Enums\FundingStatus;
use App\Domains\Funding\Enums\FundingType;
use App\Domains\Funding\Models\FundingMovement;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use RuntimeException;

/**
 * Paying investors what they are owed.
 *
 * Cash is applied in the usual order: capital back first, then the preferred return each investor's
 * agreement provides, then the remaining profit. Everything is worked out per funding source, because
 * one investor may have put money into a project more than once on different terms.
 */
final class DistributionService
{
    /**
     * What each investor has put in, been paid and is still owed at a given date.
     *
     * @return list<array<string, mixed>>
     */
    public function positions(Project $project, ?Carbon $asAt = null): array
    {
        $asAt ??= Carbon::today('Africa/Johannesburg');

        $sources = FundingSource::query()->with('investor:id,ulid,name')
            ->where('project_id', $project->id)
            ->whereIn('type', [FundingType::Equity, FundingType::Investor])
            ->whereIn('status', [FundingStatus::Committed, FundingStatus::Active, FundingStatus::Closed])
            ->orderBy('id')->get();

        $paidLines = DistributionLine::query()
            ->whereIn('funding_source_id', $sources->modelKeys())
            ->whereIn('distribution_id', Distribution::query()->where('project_id', $project->id)->where('status', 'paid')->select('id'))
            ->get()->groupBy('funding_source_id');

        $positions = [];
        foreach ($sources as $source) {
            $movements = FundingMovement::query()->where('funding_source_id', $source->id)->where('direction', 'in')
                ->whereDate('occurred_on', '<=', $asAt->toDateString())->orderBy('occurred_on')->get();
            $contributed = (float) $movements->sum(static fn (FundingMovement $m): float => (float) $m->amount);
            if ($contributed <= 0) {
                continue;
            }

            $lines = $paidLines->get($source->id);
            $capitalPaid = (float) ($lines?->sum(static fn (DistributionLine $l): float => (float) $l->capital) ?? 0);
            $preferredPaid = (float) ($lines?->sum(static fn (DistributionLine $l): float => (float) $l->preferred) ?? 0);
            $profitPaid = (float) ($lines?->sum(static fn (DistributionLine $l): float => (float) $l->profit) ?? 0);

            // The preferred return runs on each contribution from the day it was paid in.
            $rate = (float) ($source->preferred_return_percent ?? config('distributions.preferred_return_percent', 0)) / 100;
            $earned = 0.0;
            foreach ($movements as $movement) {
                $days = (int) $movement->occurred_on->diffInDays($asAt);
                $earned += (float) $movement->amount * $rate * $days / 365;
            }

            $positions[] = [
                'sourceId' => $source->id,
                'source' => $source->name,
                'investorId' => $source->investor_id,
                'investor' => $source->investor_id !== null ? $source->investor->name : $source->name,
                'contributed' => round($contributed, 2),
                'capitalOutstanding' => round(max(0, $contributed - $capitalPaid), 2),
                'preferredRate' => round($rate * 100, 2),
                'preferredOutstanding' => round(max(0, $earned - $preferredPaid), 2),
                'profitPaid' => round($profitPaid, 2),
                'profitSharePercent' => $source->profit_share_percent === null ? null : (float) $source->profit_share_percent,
                'paidToDate' => round($capitalPaid + $preferredPaid + $profitPaid, 2),
            ];
        }

        return $positions;
    }

    /**
     * Work out how an amount would be split, without saving anything.
     *
     * @return array{lines: list<array<string, mixed>>, capital: float, preferred: float, profit: float, unallocated: float}
     */
    public function preview(Project $project, float $amount, ?Carbon $asAt = null): array
    {
        $positions = $this->positions($project, $asAt);
        $lines = array_map(static fn (array $p): array => [...$p, 'capital' => 0.0, 'preferred' => 0.0, 'profit' => 0.0, 'total' => 0.0], $positions);
        $left = round($amount, 2);

        // 1. Capital back, pro rata to what is still outstanding.
        $left = $this->allocate($lines, 'capital', 'capitalOutstanding', $left);

        // 2. The preferred return each investor has earned.
        $left = $this->allocate($lines, 'preferred', 'preferredOutstanding', $left);

        // 3. Whatever is left is profit, shared by the agreed percentages or pro rata to capital.
        if ($left > 0.005) {
            $stated = array_sum(array_map(static fn (array $l): float => (float) ($l['profitSharePercent'] ?? 0), $lines));
            $basis = $stated > 0
                ? array_map(static fn (array $l): float => (float) ($l['profitSharePercent'] ?? 0), $lines)
                : array_map(static fn (array $l): float => (float) $l['contributed'], $lines);
            $total = array_sum($basis);
            if ($total > 0) {
                foreach ($lines as $i => $line) {
                    $share = round($left * $basis[$i] / $total, 2);
                    $lines[$i]['profit'] = $share;
                }
                // Rounding differences go to the largest share, so the totals always add up.
                $allocated = array_sum(array_map(static fn (array $l): float => (float) $l['profit'], $lines));
                $difference = round($left - $allocated, 2);
                if (abs($difference) >= 0.01) {
                    $largest = 0;
                    foreach ($basis as $i => $value) {
                        if ($value > $basis[$largest]) {
                            $largest = $i;
                        }
                    }
                    $lines[$largest]['profit'] = round((float) $lines[$largest]['profit'] + $difference, 2);
                }
                $left = 0.0;
            }
        }

        foreach ($lines as $i => $line) {
            $lines[$i]['total'] = round((float) $line['capital'] + (float) $line['preferred'] + (float) $line['profit'], 2);
        }

        return [
            'lines' => $lines,
            'capital' => round(array_sum(array_map(static fn (array $l): float => (float) $l['capital'], $lines)), 2),
            'preferred' => round(array_sum(array_map(static fn (array $l): float => (float) $l['preferred'], $lines)), 2),
            'profit' => round(array_sum(array_map(static fn (array $l): float => (float) $l['profit'], $lines)), 2),
            'unallocated' => round($left, 2),
        ];
    }

    public function declare(Project $project, float $amount, Carbon $declaredOn, ?string $notes, User $by): Distribution
    {
        if ($amount <= 0) {
            throw new RuntimeException('The amount to distribute must be more than nothing.');
        }
        $preview = $this->preview($project, $amount, $declaredOn);
        if ($preview['lines'] === []) {
            throw new RuntimeException('This project has no investors who have paid money in.');
        }

        return DB::transaction(function () use ($project, $amount, $declaredOn, $notes, $by, $preview): Distribution {
            $distribution = Distribution::query()->create([
                'number' => (int) Distribution::query()->lockForUpdate()->max('number') + 1,
                'project_id' => $project->id, 'declared_on' => $declaredOn->toDateString(), 'amount' => round($amount, 2),
                'status' => 'draft', 'notes' => $notes, 'created_by' => $by->id,
            ]);

            foreach ($preview['lines'] as $line) {
                DistributionLine::query()->create([
                    'distribution_id' => $distribution->id, 'funding_source_id' => $line['sourceId'], 'investor_id' => $line['investorId'],
                    'capital' => $line['capital'], 'preferred' => $line['preferred'], 'profit' => $line['profit'], 'total' => $line['total'],
                ]);
            }

            return $distribution;
        });
    }

    public function approve(Distribution $distribution, User $by): void
    {
        if ($distribution->status !== 'draft') {
            throw new RuntimeException('Only a draft distribution can be approved.');
        }
        $distribution->update(['status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now()]);
        activity('closeout')->causedBy($by)->performedOn($distribution)->log('Distribution approved');
    }

    /**
     * Mark the distribution paid. Each line is recorded against the funding source as money out, so the
     * funding position stays right.
     */
    public function pay(Distribution $distribution, Carbon $paidOn, User $by): void
    {
        if ($distribution->status !== 'approved') {
            throw new RuntimeException('The distribution must be approved before it is paid.');
        }

        DB::transaction(function () use ($distribution, $paidOn, $by): void {
            foreach ($distribution->lines as $line) {
                FundingMovement::query()->create([
                    'funding_source_id' => $line->funding_source_id, 'direction' => 'out', 'amount' => $line->total,
                    'occurred_on' => $paidOn->toDateString(), 'reference' => $distribution->reference(), 'recorded_by' => $by->id,
                ]);
            }
            $distribution->update(['status' => 'paid', 'paid_on' => $paidOn->toDateString()]);
            activity('closeout')->causedBy($by)->performedOn($distribution)->log('Distribution paid');
        });
    }

    /**
     * An investor leaves part of a distribution in the business, put into another project's funding.
     */
    public function reinvest(Investor $investor, FundingSource $target, float $amount, Carbon $on, ?Project $from, ?int $lineId, ?string $notes, User $by): Reinvestment
    {
        return DB::transaction(function () use ($investor, $target, $amount, $on, $from, $lineId, $notes, $by): Reinvestment {
            FundingMovement::query()->create([
                'funding_source_id' => $target->id, 'direction' => 'in', 'amount' => $amount,
                'occurred_on' => $on->toDateString(), 'reference' => 'Reinvested by '.$investor->name, 'recorded_by' => $by->id,
            ]);

            return Reinvestment::query()->create([
                'investor_id' => $investor->id, 'distribution_line_id' => $lineId, 'from_project_id' => $from?->id,
                'to_funding_source_id' => $target->id, 'amount' => $amount, 'occurred_on' => $on->toDateString(),
                'notes' => $notes, 'recorded_by' => $by->id,
            ]);
        });
    }

    /**
     * Share an amount across lines in proportion to what each is owed, and return what is left.
     *
     * @param  list<array<string, mixed>>  $lines
     */
    private function allocate(array &$lines, string $field, string $owedField, float $available): float
    {
        $owed = array_map(static fn (array $l): float => (float) $l[$owedField], $lines);
        $total = array_sum($owed);
        if ($total <= 0 || $available <= 0) {
            return $available;
        }

        if ($available >= $total) {
            foreach ($lines as $i => $line) {
                $lines[$i][$field] = round($owed[$i], 2);
            }

            return round($available - $total, 2);
        }

        // Not enough to go round: everyone gets the same share of what they are owed.
        $paid = 0.0;
        foreach ($lines as $i => $line) {
            $share = round($available * $owed[$i] / $total, 2);
            $lines[$i][$field] = $share;
            $paid += $share;
        }

        return round($available - $paid, 2) > 0.005 ? round($available - $paid, 2) : 0.0;
    }
}
