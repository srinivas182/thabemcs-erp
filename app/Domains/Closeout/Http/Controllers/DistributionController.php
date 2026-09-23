<?php

declare(strict_types=1);

namespace App\Domains\Closeout\Http\Controllers;

use App\Domains\Closeout\Models\Distribution;
use App\Domains\Closeout\Models\DistributionLine;
use App\Domains\Closeout\Models\Reinvestment;
use App\Domains\Closeout\Services\DistributionService;
use App\Domains\Funding\Models\FundingSource;
use App\Domains\Funding\Models\Investor;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;
use RuntimeException;

final class DistributionController
{
    public function __construct(private readonly DistributionService $distributions) {}

    public function index(Project $project, Request $request): Response
    {
        Gate::authorize('view-financial-reports');
        $preview = $request->float('preview');

        return Inertia::render('projects/distributions', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'positions' => $this->distributions->positions($project),
            'preview' => $preview > 0 ? $this->distributions->preview($project, $preview) : null,
            'previewAmount' => $preview > 0 ? $preview : null,
            'distributions' => Distribution::query()->with(['lines.investor:id,name', 'lines.source:id,name'])
                ->where('project_id', $project->id)->orderByDesc('declared_on')->get()
                ->map(static fn (Distribution $d): array => [
                    'id' => $d->ulid, 'reference' => $d->reference(), 'declared' => $d->declared_on->toDateString(),
                    'amount' => (float) $d->amount, 'status' => $d->status, 'paid' => $d->paid_on?->toDateString(), 'notes' => $d->notes,
                    'lines' => $d->lines->map(static fn (DistributionLine $l): array => [
                        'id' => $l->id, 'investor' => $l->investor?->name ?? $l->source->name, 'capital' => (float) $l->capital,
                        'preferred' => (float) $l->preferred, 'profit' => (float) $l->profit, 'total' => (float) $l->total,
                    ])->values(),
                ])->values(),
            'reinvestments' => Reinvestment::query()->with(['investor:id,name'])->where('from_project_id', $project->id)->orderByDesc('occurred_on')->get()
                ->map(static fn (Reinvestment $r): array => ['id' => $r->id, 'investor' => $r->investor->name, 'amount' => (float) $r->amount,
                    'on' => $r->occurred_on->toDateString(), 'notes' => $r->notes])->values(),
            'canManage' => Gate::allows('manage-funding'),
            'canApprove' => Gate::allows('close-projects'),
        ]);
    }

    public function store(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-funding');
        $data = $request->validate([
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'declared_on' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);
        /** @var User $user */
        $user = $request->user();

        try {
            $distribution = $this->distributions->declare($project, (float) $data['amount'], Carbon::parse((string) $data['declared_on']), $data['notes'] ?? null, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Distribution {$distribution->reference()} prepared. Check the split, then approve it.");
    }

    public function approve(Request $request, Distribution $distribution): RedirectResponse
    {
        Gate::authorize('close-projects');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->distributions->approve($distribution, $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Distribution approved.');
    }

    public function pay(Request $request, Distribution $distribution): RedirectResponse
    {
        Gate::authorize('manage-funding');
        $data = $request->validate(['paid_on' => ['required', 'date']]);
        /** @var User $user */
        $user = $request->user();

        try {
            $this->distributions->pay($distribution, Carbon::parse((string) $data['paid_on']), $user);
        } catch (RuntimeException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Distribution marked paid and recorded against each investor.');
    }

    public function reinvest(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-funding');
        $data = $request->validate([
            'investor' => ['required', 'string', Rule::exists('investors', 'ulid')],
            'target' => ['required', 'string', Rule::exists('funding_sources', 'ulid')],
            'amount' => ['required', 'numeric', 'gt:0', 'max:9999999999'],
            'occurred_on' => ['required', 'date'],
            'line_id' => ['nullable', 'integer'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);
        /** @var User $user */
        $user = $request->user();
        $investor = Investor::query()->where('ulid', $data['investor'])->firstOrFail();
        $target = FundingSource::query()->where('ulid', $data['target'])->firstOrFail();

        if ($target->project_id === $project->id) {
            return back()->with('error', 'Reinvestment moves money into another project.');
        }

        $this->distributions->reinvest($investor, $target, (float) $data['amount'], Carbon::parse((string) $data['occurred_on']),
            $project, isset($data['line_id']) ? (int) $data['line_id'] : null, $data['notes'] ?? null, $user);

        return back()->with('success', "{$investor->name}'s reinvestment recorded against {$target->name}.");
    }
}
