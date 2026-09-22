<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Services;

use Illuminate\Support\Carbon;

/**
 * South African public holidays (Public Holidays Act 36 of 1994). When a holiday falls on a Sunday,
 * the Monday is a public holiday. Once-off holidays declared by the President are not included.
 */
final class PublicHolidays
{
    /**
     * @return list<string> Y-m-d dates
     */
    public function forYear(int $year): array
    {
        $fixed = ['01-01', '03-21', '04-27', '05-01', '06-16', '08-09', '09-24', '12-16', '12-25', '12-26'];
        $dates = [];

        foreach ($fixed as $md) {
            $day = Carbon::parse("{$year}-{$md}");
            $dates[] = $day->toDateString();
            if ($day->isSunday()) {
                $dates[] = $day->copy()->addDay()->toDateString();
            }
        }

        $easter = $this->easterSunday($year);
        $dates[] = $easter->copy()->subDays(2)->toDateString(); // Good Friday
        $dates[] = $easter->copy()->addDay()->toDateString();   // Family Day

        return array_values(array_unique($dates));
    }

    public function isHoliday(Carbon $date): bool
    {
        return in_array($date->toDateString(), $this->forYear($date->year), true);
    }

    /**
     * Working days between two dates inclusive (Monday to Friday, or Monday to Saturday for a 6-day week), excluding public holidays.
     */
    public function workingDays(Carbon $from, Carbon $to, int $daysPerWeek = 5): int
    {
        $count = 0;
        for ($d = $from->copy()->startOfDay(); $d->lessThanOrEqualTo($to); $d->addDay()) {
            $workday = $daysPerWeek >= 6 ? ! $d->isSunday() : $d->isWeekday();
            if ($workday && ! $this->isHoliday($d)) {
                $count++;
            }
        }

        return $count;
    }

    /** Anonymous Gregorian algorithm (no calendar extension needed). */
    private function easterSunday(int $year): Carbon
    {
        $a = $year % 19;
        $b = intdiv($year, 100);
        $c = $year % 100;
        $d = intdiv($b, 4);
        $e = $b % 4;
        $f = intdiv($b + 8, 25);
        $g = intdiv($b - $f + 1, 3);
        $h = (19 * $a + $b - $d - $g + 15) % 30;
        $i = intdiv($c, 4);
        $k = $c % 4;
        $l = (32 + 2 * $e + 2 * $i - $h - $k) % 7;
        $m = intdiv($a + 11 * $h + 22 * $l, 451);
        $month = intdiv($h + $l - 7 * $m + 114, 31);
        $day = (($h + $l - 7 * $m + 114) % 31) + 1;

        return Carbon::parse(sprintf('%04d-%02d-%02d', $year, $month, $day));
    }
}
