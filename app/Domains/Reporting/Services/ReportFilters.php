<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Services;

use App\Domains\Projects\Models\Project;
use Illuminate\Support\Carbon;

/**
 * Validated report filters. Period defaults to last month; "as at" defaults to today.
 */
final class ReportFilters
{
    public function __construct(
        public readonly ?Project $project,
        public readonly Carbon $from,
        public readonly Carbon $to,
        public readonly Carbon $asAt,
    ) {}

    /**
     * @param  array<string, mixed>  $input
     */
    public static function fromArray(array $input): self
    {
        $project = isset($input['project']) && $input['project'] !== '' ? Project::query()->where('ulid', (string) $input['project'])->first() : null;
        $from = isset($input['from']) && $input['from'] !== '' ? Carbon::parse((string) $input['from']) : Carbon::today()->subMonthNoOverflow()->startOfMonth();
        $to = isset($input['to']) && $input['to'] !== '' ? Carbon::parse((string) $input['to']) : Carbon::today()->subMonthNoOverflow()->endOfMonth();
        $asAt = isset($input['as_at']) && $input['as_at'] !== '' ? Carbon::parse((string) $input['as_at']) : Carbon::today();

        return new self($project, $from->startOfDay(), $to->endOfDay(), $asAt->endOfDay());
    }

    /**
     * @return array{project: string|null, from: string, to: string, as_at: string}
     */
    public function toArray(): array
    {
        return ['project' => $this->project?->ulid, 'from' => $this->from->toDateString(), 'to' => $this->to->toDateString(), 'as_at' => $this->asAt->toDateString()];
    }

    public function describe(bool $period, bool $asAt): string
    {
        $parts = [$this->project ? "{$this->project->code} {$this->project->name}" : 'All projects'];
        if ($period) {
            $parts[] = $this->from->format('j M Y').' to '.$this->to->format('j M Y');
        }
        if ($asAt) {
            $parts[] = 'as at '.$this->asAt->format('j M Y');
        }

        return implode(', ', $parts);
    }
}
