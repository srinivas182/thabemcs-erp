<?php

declare(strict_types=1);

namespace App\Domains\MasterData\Http\Controllers;

use App\Domains\Feasibility\Enums\LineCategory;
use App\Domains\MasterData\Models\CostCode;
use App\Domains\MasterData\Models\Unit;
use App\Domains\MasterData\Services\MasterDataService;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

final class MasterDataController
{
    public function __construct(private readonly MasterDataService $masterData, private readonly CurrentCompany $context) {}

    public function index(): Response
    {
        Gate::authorize('manage-master-data');
        $this->masterData->ensureDefaults();

        return Inertia::render('settings/master-data', [
            'costCodes' => CostCode::query()->orderBy('code')->get()->map(static fn (CostCode $c): array => ['id' => $c->id, 'code' => $c->code, 'description' => $c->description, 'category' => $c->category, 'active' => $c->active]),
            'units' => Unit::query()->orderBy('code')->get()->map(static fn (Unit $u): array => ['id' => $u->id, 'code' => $u->code, 'name' => $u->name]),
            'categories' => array_values(array_map(static fn (LineCategory $c): array => ['key' => $c->value, 'label' => $c->label()], array_filter(LineCategory::cases(), static fn (LineCategory $c): bool => $c !== LineCategory::Revenue))),
        ]);
    }

    public function storeCostCode(Request $request): RedirectResponse
    {
        Gate::authorize('manage-master-data');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:20', Rule::unique('cost_codes')->where('company_id', $this->context->id())],
            'description' => ['required', 'string', 'max:255'],
            'category' => ['nullable', Rule::enum(LineCategory::class)],
        ]);
        CostCode::query()->create([...$data, 'active' => true]);

        $this->masterData->forget();

        return back()->with('success', "Cost code {$data['code']} added.");
    }

    public function toggleCostCode(CostCode $costCode): RedirectResponse
    {
        Gate::authorize('manage-master-data');
        $costCode->update(['active' => ! $costCode->active]);

        return back();
    }

    public function storeUnit(Request $request): RedirectResponse
    {
        Gate::authorize('manage-master-data');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:16', Rule::unique('units')->where('company_id', $this->context->id())],
            'name' => ['required', 'string', 'max:60'],
        ]);
        Unit::query()->create($data);

        $this->masterData->forget();

        return back()->with('success', "Unit {$data['code']} added.");
    }

    public function destroyUnit(Unit $unit): RedirectResponse
    {
        Gate::authorize('manage-master-data');
        $unit->delete();

        return back();
    }
}
