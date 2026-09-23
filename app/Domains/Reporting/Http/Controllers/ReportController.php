<?php

declare(strict_types=1);

namespace App\Domains\Reporting\Http\Controllers;

use App\Domains\Platform\Models\ReportPreset;
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
            'canSchedule' => $user->can('manage-report-schedules'),
        ]);
    }

    public function show(Request $request, string $key): InertiaResponse
    {
        $report = $this->authorized($request, $key);
        $filters = ReportFilters::fromArray($request->only(['project', 'from', 'to', 'as_at']));

        $presets = ReportPreset::query()->where('report_key', $key)->orderBy('name')->get();
        $preset = $request->string('preset')->toString() !== ''
            ? $presets->firstWhere('id', (int) $request->string('preset')->toString())
            : $presets->firstWhere('is_default', true);

        $result = $report->build($filters)->toArray();
        if ($preset !== null) {
            $result = $this->applyPreset($result, $preset->columns);
        }

        return Inertia::render('reports/show', [
            'letterhead' => $this->letterhead(),
            'presets' => $presets->map(static fn (ReportPreset $p): array => ['id' => $p->id, 'name' => $p->name, 'columns' => $p->columns, 'isDefault' => $p->is_default])->values(),
            'presetId' => $preset?->id,
            'report' => ['key' => $report->key(), 'title' => $report->title(), 'description' => $report->description(), 'filters' => $report->filters()],
            'result' => $result,
            'values' => $filters->toArray(),
        ]);
    }

    /**
     * Save the columns on show as a layout, so the same report can be produced the same way each time.
     */
    public function storePreset(Request $request, string $key): RedirectResponse
    {
        $report = $this->authorized($request, $key);
        $data = $request->validate([
            'name' => ['required', 'string', 'max:60'],
            'columns' => ['required', 'array', 'min:1'],
            'columns.*' => ['string'],
            'is_default' => ['boolean'],
        ]);
        /** @var User $user */
        $user = $request->user();

        if ($data['is_default'] ?? false) {
            ReportPreset::query()->where('report_key', $report->key())->update(['is_default' => false]);
        }
        ReportPreset::query()->create([
            'report_key' => $report->key(), 'name' => $data['name'], 'columns' => array_values($data['columns']),
            'is_default' => (bool) ($data['is_default'] ?? false), 'created_by' => $user->id,
        ]);

        return back()->with('success', 'Layout saved. Choose it whenever you open this report.');
    }

    public function destroyPreset(ReportPreset $preset): RedirectResponse
    {
        $preset->delete();

        return back()->with('success', 'Layout removed.');
    }

    /**
     * The company's letterhead, shown on screen and on every printed or exported report.
     *
     * @return array<string, string|null>
     */
    private function letterhead(): array
    {
        $company = $this->context->require();
        /** @var array<string, string> $settings */
        $settings = (array) ($company->settings['letterhead'] ?? []);

        return [
            'company' => $company->name,
            'registration' => $company->registration_number,
            'vat' => $company->vat_number,
            'address' => $settings['address'] ?? null,
            'contact' => $settings['contact'] ?? null,
            'logoUrl' => $settings['logo_url'] ?? (string) config('branding.logo') ?: null,
            'footer' => $settings['footer'] ?? null,
        ];
    }

    /**
     * Keep only the chosen columns, in the chosen order.
     *
     * @param  array<string, mixed>  $result
     * @param  list<string>  $columns
     * @return array<string, mixed>
     */
    private function applyPreset(array $result, array $columns): array
    {
        /** @var list<array{key: string, label: string, type: string}> $all */
        $all = $result['columns'];
        $keep = array_values(array_filter($columns, static fn (string $c): bool => in_array($c, array_column($all, 'key'), true)));
        if ($keep === []) {
            return $result;
        }

        $result['columns'] = array_values(array_map(
            static fn (string $key): array => $all[array_search($key, array_column($all, 'key'), true)],
            $keep,
        ));
        /** @var list<array<string, mixed>> $rows */
        $rows = $result['rows'];
        $result['rows'] = array_map(static fn (array $row): array => array_intersect_key($row, array_flip($keep)), $rows);

        return $result;
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
