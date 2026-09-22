<?php

declare(strict_types=1);

namespace App\Domains\Programme\Services;

use App\Domains\Programme\Models\ActivityDependency;
use App\Domains\Programme\Models\ProgrammeActivity;
use App\Domains\Projects\Models\Project;
use App\Domains\Workforce\Services\PublicHolidays;
use Illuminate\Support\Carbon;
use InvalidArgumentException;

/**
 * Critical path method on working days (Monday to Friday, excluding SA public holidays and the
 * building industry's December shutdown, see config/programme.php).
 *
 * Each activity starts no earlier than its planned start and no earlier than its predecessors
 * finish (plus lag). The backward pass gives total float; activities with zero float are critical.
 */
final class ScheduleService
{
    public function __construct(private readonly PublicHolidays $holidays) {}

    /**
     * @return array{finish: string|null, activities: array<int, array{earlyStart: string, earlyFinish: string, float: int, critical: bool, behind: bool}>}
     */
    public function calculate(Project $project): array
    {
        $activities = ProgrammeActivity::query()->where('project_id', $project->id)->get()->keyBy('id');
        if ($activities->isEmpty()) {
            return ['finish' => null, 'activities' => []];
        }
        /** @var array<int, ProgrammeActivity> $byId */
        $byId = $activities->all();

        $deps = ActivityDependency::query()->whereIn('successor_id', $activities->keys())->get();
        $preds = [];
        $succs = [];
        foreach ($deps as $d) {
            $preds[$d->successor_id][] = $d;
            $succs[$d->predecessor_id][] = $d;
        }

        $order = $this->topologicalOrder(array_map('intval', array_keys($byId)), array_values($deps->all()));

        // Working-day calendar from the earliest planned start.
        $base = $activities->min(static fn (ProgrammeActivity $a): string => $a->planned_start->toDateString());
        $span = (int) $activities->sum('duration_days') + (int) $deps->sum(static fn (ActivityDependency $d): int => max(0, $d->lag_days)) + 400;
        [$calendar, $index] = $this->calendar(Carbon::parse((string) $base), $span + (int) Carbon::parse((string) $base)->diffInDays($activities->max('planned_start')));

        $es = [];
        $ef = [];
        foreach ($order as $id) {
            $a = $byId[$id];
            $start = $this->indexOf($index, $calendar, $a->planned_start);
            foreach ($preds[$id] ?? [] as $d) {
                $start = max($start, $ef[$d->predecessor_id] + $d->lag_days);
            }
            $es[$id] = $start;
            $ef[$id] = $start + $a->duration_days;
        }

        $projectFinish = $ef === [] ? 0 : max($ef);
        $ls = [];
        $lf = [];
        foreach (array_reverse($order) as $id) {
            $finish = $projectFinish;
            foreach ($succs[$id] ?? [] as $d) {
                $finish = min($finish, $ls[$d->successor_id] - $d->lag_days);
            }
            $lf[$id] = $finish;
            $ls[$id] = $finish - $byId[$id]->duration_days;
        }

        $today = Carbon::today('Africa/Johannesburg');
        $result = [];
        foreach ($order as $id) {
            $a = $byId[$id];
            $finishIdx = max($es[$id], $ef[$id] - 1); // last working day of the activity (milestones: the start day)
            $earlyFinish = $calendar[$finishIdx] ?? $calendar[count($calendar) - 1];
            $float = $ls[$id] - $es[$id];
            $result[$id] = [
                'earlyStart' => $calendar[$es[$id]] ?? $calendar[count($calendar) - 1],
                'earlyFinish' => $earlyFinish,
                'float' => $float,
                'critical' => $float <= 0,
                'behind' => $a->percent_complete < 100 && $today->toDateString() > $earlyFinish,
            ];
        }

        $finishes = array_column($result, 'earlyFinish');

        return ['finish' => $finishes === [] ? null : max($finishes), 'activities' => $result];
    }

    /**
     * @throws InvalidArgumentException when the link would create a loop
     */
    public function assertNoCycle(ProgrammeActivity $predecessor, ProgrammeActivity $successor): void
    {
        if ($predecessor->id === $successor->id) {
            throw new InvalidArgumentException('An activity cannot depend on itself.');
        }

        // Walk forward from the successor; reaching the predecessor means a loop.
        $seen = [];
        $stack = [$successor->id];
        while ($stack !== []) {
            $id = array_pop($stack);
            if ($id === $predecessor->id) {
                throw new InvalidArgumentException("That link would create a loop: {$successor->name} already leads to {$predecessor->name}.");
            }
            if (isset($seen[$id])) {
                continue;
            }
            $seen[$id] = true;
            foreach (ActivityDependency::query()->where('predecessor_id', $id)->pluck('successor_id') as $next) {
                $stack[] = (int) $next;
            }
        }
    }

    /**
     * @param  list<int>  $ids
     * @param  list<ActivityDependency>  $deps
     * @return list<int>
     */
    private function topologicalOrder(array $ids, array $deps): array
    {
        $incoming = array_fill_keys($ids, 0);
        $out = [];
        foreach ($deps as $d) {
            $incoming[$d->successor_id] = ($incoming[$d->successor_id] ?? 0) + 1;
            $out[$d->predecessor_id][] = $d->successor_id;
        }

        $queue = array_keys(array_filter($incoming, static fn (int $n): bool => $n === 0));
        $order = [];
        while ($queue !== []) {
            $id = array_shift($queue);
            $order[] = $id;
            foreach ($out[$id] ?? [] as $next) {
                if (--$incoming[$next] === 0) {
                    $queue[] = $next;
                }
            }
        }

        if (count($order) !== count($ids)) {
            throw new InvalidArgumentException('The programme has a loop in its links.');
        }

        return $order;
    }

    /**
     * @return array{0: list<string>, 1: array<string, int>}
     */
    private function calendar(Carbon $from, int $days): array
    {
        $dates = [];
        $index = [];
        $holidays = [];
        for ($d = $from->copy(), $n = 0; count($dates) < max(1, $days); $d->addDay(), $n++) {
            $holidays[$d->year] ??= array_flip($this->holidays->forYear($d->year));
            if ($this->isWorkingDay($d, $holidays[$d->year])) {
                $index[$d->toDateString()] = count($dates);
                $dates[] = $d->toDateString();
            }
            if ($n > 20000) {
                break;
            }
        }

        return [$dates, $index];
    }

    /**
     * Working days from $from up to and including $to (0 if $to is before $from).
     */
    public function workingDaysBetween(Carbon $from, Carbon $to): int
    {
        $count = 0;
        $holidays = [];
        for ($d = $from->copy()->startOfDay(); $d->lessThanOrEqualTo($to) && $count < 5000; $d->addDay()) {
            $holidays[$d->year] ??= array_flip($this->holidays->forYear($d->year));
            if ($this->isWorkingDay($d, $holidays[$d->year])) {
                $count++;
            }
        }

        return $count;
    }

    /**
     * Monday to Friday, not a public holiday, and not in the building industry's December shutdown.
     *
     * @param  array<string, int>  $holidays
     */
    private function isWorkingDay(Carbon $d, array $holidays): bool
    {
        if (! $d->isWeekday() || isset($holidays[$d->toDateString()])) {
            return false;
        }

        $from = config('programme.shutdown.from');
        $to = config('programme.shutdown.to');
        if (! is_string($from) || $from === '' || ! is_string($to) || $to === '') {
            return true;
        }
        $md = $d->format('m-d');

        // The shutdown straddles the new year (e.g. 16 Dec to 9 Jan).
        return $from <= $to ? ! ($md >= $from && $md <= $to) : ! ($md >= $from || $md <= $to);
    }

    /**
     * Index of a date in the calendar; non-working days move to the next working day.
     *
     * @param  array<string, int>  $index
     * @param  list<string>  $calendar
     */
    private function indexOf(array $index, array $calendar, Carbon $date): int
    {
        for ($d = $date->copy(), $i = 0; $i < 40; $d->addDay(), $i++) {
            if (isset($index[$d->toDateString()])) {
                return $index[$d->toDateString()];
            }
        }

        return count($calendar) - 1;
    }
}
