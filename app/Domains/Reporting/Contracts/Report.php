<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Contracts;

use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportResult;

/**
 * A standard report: a titled table that can be viewed, printed, exported and scheduled.
 */
interface Report
{
    public function key(): string;

    public function title(): string;

    public function description(): string;

    /** Gate the user must pass to run this report. */
    public function gate(): string;

    /**
     * Filters the report accepts.
     *
     * @return list<'project'|'period'|'as_at'>
     */
    public function filters(): array;

    public function build(ReportFilters $filters): ReportResult;
}
