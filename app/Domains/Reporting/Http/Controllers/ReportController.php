<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Projects\Models\Project;
use App\Domains\Reporting\Contracts\Report;
use App\Domains\Reporting\Models\ReportSchedule;
use App\Domains\Reporting\Services\PortfolioService;
use App\Domains\Reporting\Services\ReportFilters;
use App\Domains\Reporting\Services\ReportRegistry;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

final class ReportController
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly PortfolioService $portfolio,
        private readonly CurrentCompany $context,
    ) {}

    public function dashboard(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        // Super Admin with no company selected: the group view.
        if ($this->context->get() === null) {
            abort_unless($user->is_super_admin, 403);

            return Inertia::render('reports/dashboard', ['group' => $this->portfolio->forGroup(), 'portfolio' => null]);
        }

        Gate::authorize('view-financial-reports');

        return Inertia::render('reports/dashboard', ['group' => null, 'portfolio' => $this->portfolio->forCurrentCompany()]);
    }

    public function index(Request $request): InertiaResponse
    {
        /** @var User $user */
        $user = $request->user();

        return Inertia::render('reports/index', [
            'reports' => array_values(array_map(static fn (Report $r): array => ['key' => $r->key(), 'title' => $r->title(), 'description' => $r->description()],
                array_filter($this->registry->all(), static fn (Report $r): bool => $user->can($r->gate())))),
            'schedules' => ReportSchedule::query()->with('creator:id,name')->orderBy('report')->get()
                ->filter(fn (ReportSchedule $s): bool => ($r = $this->registry->find($s->report)) !== null && $user->can($r->gate()))
                ->map(fn (ReportSchedule $s): array => [
                    'id' => $s->id, 'report' => $this->registry->find($s->report)?->title(), 'frequency' => $s->frequency, 'day' => $s->day,
                    'format' => $s->format, 'recipients' => User::query()->whereIn('id', $s->recipients)->pluck('name')->all(),
                    'lastSent' => $s->last_sent_at?->toIso8601String(), 'by' => $s->creator->name,
                ])->values(),
            'people' => User::query()->where('company_id', $this->context->id())->where('is_active', true)->orderBy('name')->get(['ulid', 'name'])
                ->map(static fn (User $u): array => ['key' => $u->ulid, 'label' => $u->name])->values(),
            'canSchedule' => $user->can('manage-report-schedules'),
        ]);
    }

    public function show(Request $request, string $key): InertiaResponse
    {
        $report = $this->authorized($request, $key);
        $filters = ReportFilters::fromArray($request->only(['project', 'from', 'to', 'as_at']));

        return Inertia::render('reports/show', [
            'report' => ['key' => $report->key(), 'title' => $report->title(), 'description' => $report->description(), 'filters' => $report->filters()],
            'result' => $report->build($filters)->toArray(),
            'values' => $filters->toArray(),
            'projects' => Project::query()->orderBy('code')->get(['ulid', 'code', 'name'])->map(static fn (Project $p): array => ['key' => $p->ulid, 'label' => "{$p->code} {$p->name}"])->values(),
        ]);
    }

    public function download(Request $request, string $key, string $format): Response
    {
        $report = $this->authorized($request, $key);
        abort_unless(in_array($format, ['xlsx', 'csv'], true), 404);

        $filters = ReportFilters::fromArray($request->only(['project', 'from', 'to', 'as_at']));
        $file = $this->registry->export($report->build($filters), $format);

        activity('reports')->causedBy($request->user())->withProperties(['report' => $key, 'format' => $format, ...$filters->toArray()])->log('Report downloaded');

        return response($file['content'], 200, [
            'Content-Type' => $file['mime'],
            'Content-Disposition' => sprintf('attachment; filename="%s-%s.%s"', $key, now()->format('Ymd'), $file['extension']),
        ]);
    }

    public function schedule(Request $request): RedirectResponse
    {
        Gate::authorize('manage-report-schedules');
        $keys = array_map(static fn (Report $r): string => $r->key(), $this->registry->all());
        $data = $request->validate([
            'report' => ['required', Rule::in($keys)],
            'frequency' => ['required', 'in:weekly,monthly'],
            'day' => ['required', 'integer', $request->input('frequency') === 'weekly' ? 'between:1,7' : 'between:1,28'],
            'format' => ['required', 'in:xlsx,csv'],
            'project' => ['nullable', 'string', Rule::exists('projects', 'ulid')->where('company_id', $this->context->id())],
            'recipients' => ['required', 'array', 'min:1', 'max:30'],
            'recipients.*' => ['string', Rule::exists('users', 'ulid')->where('company_id', $this->context->id())],
        ]);

        /** @var User $user */
        $user = $request->user();
        ReportSchedule::query()->create([
            'report' => $data['report'], 'frequency' => $data['frequency'], 'day' => (int) $data['day'], 'format' => $data['format'],
            'filters' => ['project' => $data['project'] ?? null],
            'recipients' => User::query()->whereIn('ulid', $data['recipients'])->pluck('id')->map(static fn ($id): int => (int) $id)->all(),
            'created_by' => $user->id,
        ]);

        return back()->with('success', 'Report scheduled.');
    }

    public function unschedule(ReportSchedule $schedule): RedirectResponse
    {
        Gate::authorize('manage-report-schedules');
        $schedule->delete();

        return back()->with('success', 'Schedule removed.');
    }

    private function authorized(Request $request, string $key): Report
    {
        $report = $this->registry->find($key);
        abort_if($report === null, 404);
        abort_unless($request->user()?->can($report->gate()) === true, 403);

        return $report;
    }
}
