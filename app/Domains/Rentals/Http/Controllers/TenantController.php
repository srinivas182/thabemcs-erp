<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Http\Controllers;

use App\Domains\Rentals\Models\Tenant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class TenantController
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-rentals');
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();
        $like = '%'.addcslashes($search, '%_\\').'%';

        return Inertia::render('rentals/tenants', [
            'tenants' => Tenant::query()
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', $like)->orWhere('email', 'like', $like)))
                ->orderBy('name')->paginate(25)->withQueryString()
                ->through(static fn (Tenant $t): array => [
                    'id' => $t->ulid, 'name' => $t->name, 'entityType' => $t->entity_type, 'email' => $t->email, 'phone' => $t->phone,
                    'employer' => $t->employer, 'status' => $t->status, 'fica' => $t->fica_verified, 'credit' => $t->credit_checked,
                    'screened' => $t->screened_on?->toDateString(), 'notes' => $t->notes,
                ]),
            'filters' => ['status' => $status ?: null, 'q' => $search ?: null],
            'canManage' => Gate::allows('manage-rentals'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $tenant = Tenant::query()->create($this->validated($request));

        return back()->with('success', "{$tenant->name} added.");
    }

    public function update(Request $request, Tenant $tenant): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $tenant->update($this->validated($request));

        return back()->with('success', 'Tenant updated.');
    }

    /** Record that screening was done: FICA and a credit check before approving an applicant. */
    public function screen(Request $request, Tenant $tenant): RedirectResponse
    {
        Gate::authorize('manage-rentals');
        $data = $request->validate([
            'fica_verified' => ['boolean'],
            'credit_checked' => ['boolean'],
            'status' => ['required', 'in:applicant,approved,current,former,declined'],
        ]);
        $tenant->update([...$data, 'screened_on' => now()->toDateString()]);
        activity('rentals')->causedBy($request->user())->performedOn($tenant)->log('Tenant screening updated');

        return back()->with('success', 'Screening recorded.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'entity_type' => ['required', 'in:individual,company,trust'],
            'id_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'employer' => ['nullable', 'string', 'max:190'],
            'status' => ['required', 'in:applicant,approved,current,former,declined'],
            'accounting_ref' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);
    }
}
