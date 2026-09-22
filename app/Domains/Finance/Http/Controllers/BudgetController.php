<?php

declare(strict_types=1);

namespace App\Domains\Finance\Http\Controllers;

use App\Domains\Finance\Exceptions\FinanceException;
use App\Domains\Finance\Models\BudgetLine;
use App\Domains\Finance\Models\VariationOrder;
use App\Domains\Finance\Services\BudgetService;
use App\Domains\MasterData\Models\CostCode;
use App\Domains\MasterData\Services\MasterDataService;
use App\Domains\Projects\Models\Project;
use App\Domains\Site\Models\SiteInstruction;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class BudgetController
{
    public function __construct(private readonly BudgetService $budgets) {}

    public function show(Request $request, Project $project): Response
    {
        $lines = $this->budgets->summary($project);
        $totals = ['original' => 0.0, 'variations' => 0.0, 'revised' => 0.0, 'committed' => 0.0, 'direct' => 0.0, 'available' => 0.0];
        foreach ($lines as $line) {
            foreach ($totals as $key => $value) {
                $totals[$key] = $value + $line[$key];
            }
        }

        return Inertia::render('projects/budget', [
            'project' => ['id' => $project->ulid, 'name' => $project->name, 'code' => $project->code],
            'lines' => $lines,
            'totals' => array_map(static fn (float $v): float => round($v, 2), $totals),
            'variations' => VariationOrder::query()->with(['budgetLine:id,code', 'requester:id,name'])->where('project_id', $project->id)->orderByDesc('number')->get()
                ->map(static fn (VariationOrder $v): array => [
                    'id' => $v->ulid, 'reference' => $v->reference(), 'title' => $v->title, 'code' => $v->budgetLine->code, 'reason' => $v->reason,
                    'amount' => (float) $v->amount, 'days' => $v->time_impact_days, 'status' => $v->status, 'by' => $v->requester->name,
                ]),
            'instructions' => SiteInstruction::query()->where('project_id', $project->id)->where('cost_implication', true)->orderByDesc('number')->get(['id', 'number', 'subject'])
                ->map(static fn (SiteInstruction $i): array => ['key' => (string) $i->id, 'label' => "SI-{$i->number} {$i->subject}"])->values(),
            'library' => (function () use ($lines): array {
                app(MasterDataService::class)->ensureDefaults();
                $used = array_column($lines, 'code');

                return array_values(CostCode::query()->where('active', true)->orderBy('code')->get()
                    ->reject(static fn ($c): bool => in_array($c->code, $used, true))
                    ->map(static fn ($c): array => ['key' => (string) $c->id, 'label' => "{$c->code} {$c->description}"])->all());
            })(),
            'can' => [
                'manage' => $request->user()?->can('manage-budget') ?? false,
                'vary' => $request->user()?->can('raise-variations') ?? false,
            ],
        ]);
    }

    public function fromFeasibility(Project $project): RedirectResponse
    {
        Gate::authorize('manage-budget');

        try {
            $count = $this->budgets->fromFeasibility($project);
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "Budget created with {$count} cost codes from the approved feasibility.");
    }

    public function import(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-budget');
        $request->validate(['file' => ['required', 'file', 'mimes:csv,txt', 'max:5120']]);

        try {
            $result = $this->budgets->importCsv($project, $request->file('file'));
        } catch (FinanceException $e) {
            return back()->with('error', $e->getMessage());
        }

        $message = "{$result['imported']} cost codes imported.";

        return $result['errors']
            ? back()->with('error', $message.' Some rows were skipped: '.implode(' ', array_slice($result['errors'], 0, 5)))
            : back()->with('success', $message);
    }

    public function storeLine(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('manage-budget');
        // Either pick from the company cost code library or type a project-specific code.
        if ($request->filled('cost_code_id')) {
            $library = CostCode::query()->findOrFail((int) $request->input('cost_code_id'));
            $request->merge(['code' => $library->code, 'description' => $library->description, 'category' => $library->category]);
        }
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('budget_lines')->where('project_id', $project->id)],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:24'],
            'original_amount' => ['required', 'numeric', 'min:0'],
        ], ['code.unique' => 'That cost code is already on this budget.']);

        BudgetLine::query()->create([...$data, 'project_id' => $project->id, 'sort' => (int) BudgetLine::query()->where('project_id', $project->id)->max('sort') + 1]);

        return back()->with('success', 'Cost code added.');
    }

    public function raiseVariation(Request $request, Project $project): RedirectResponse
    {
        Gate::authorize('raise-variations');
        $data = $request->validate([
            'budget_line_id' => ['required', 'integer', Rule::exists('budget_lines', 'id')->where('project_id', $project->id)],
            'title' => ['required', 'string', 'max:200'],
            'description' => ['nullable', 'string', 'max:5000'],
            'reason' => ['required', 'in:client_request,design_change,unforeseen,error,regulatory,other'],
            'amount' => ['required', 'numeric', 'not_in:0'],
            'time_impact_days' => ['nullable', 'integer', 'between:-365,365'],
            'site_instruction_id' => ['nullable', 'integer', Rule::exists('site_instructions', 'id')->where('project_id', $project->id)],
        ]);

        /** @var User $user */
        $user = $request->user();

        try {
            $variation = $this->budgets->raiseVariation($project, [
                'budget_line_id' => (int) $data['budget_line_id'], 'title' => (string) $data['title'], 'description' => $data['description'] ?? null,
                'reason' => (string) $data['reason'], 'amount' => (float) $data['amount'], 'time_impact_days' => (int) ($data['time_impact_days'] ?? 0),
                'site_instruction_id' => isset($data['site_instruction_id']) ? (int) $data['site_instruction_id'] : null,
            ], $user);
        } catch (ApprovalException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$variation->reference()} submitted for approval.");
    }
}
