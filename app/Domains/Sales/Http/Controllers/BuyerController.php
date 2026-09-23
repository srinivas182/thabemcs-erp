<?php

declare(strict_types=1);

namespace App\Domains\Sales\Http\Controllers;

use App\Domains\Sales\Models\Buyer;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Buyers, from first enquiry to registration. ID and registration numbers are encrypted and only shown
 * to people who may manage sales.
 */
final class BuyerController
{
    public function index(Request $request): Response
    {
        Gate::authorize('view-sales');
        $status = $request->string('status')->toString();
        $search = $request->string('q')->toString();

        return Inertia::render('sales/buyers', [
            'buyers' => Buyer::query()->with(['owner:id,name', 'agent:id,name'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->when($search !== '', fn ($q) => $q->where(fn ($w) => $w->where('name', 'like', '%'.addcslashes($search, '%_\\').'%')->orWhere('email', 'like', '%'.addcslashes($search, '%_\\').'%')))
                ->orderBy('name')->paginate(25)->withQueryString()
                ->through(static fn (Buyer $b): array => [
                    'id' => $b->ulid, 'name' => $b->name, 'entityType' => $b->entity_type, 'email' => $b->email, 'phone' => $b->phone,
                    'status' => $b->status, 'source' => $b->source, 'owner' => $b->owner?->name, 'agent' => $b->agent?->name,
                    'fica' => $b->fica_verified, 'ficaOn' => $b->fica_verified_on?->toDateString(), 'notes' => $b->notes,
                ]),
            'filters' => ['status' => $status ?: null, 'q' => $search ?: null],
            'canManage' => Gate::allows('manage-sales'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $buyer = Buyer::query()->create($this->validated($request));

        return back()->with('success', "{$buyer->name} added.");
    }

    public function update(Request $request, Buyer $buyer): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $buyer->update($this->validated($request));

        return back()->with('success', 'Buyer updated.');
    }

    public function verifyFica(Request $request, Buyer $buyer): RedirectResponse
    {
        Gate::authorize('manage-sales');
        $data = $request->validate(['verified' => ['required', 'boolean']]);
        $buyer->update([
            'fica_verified' => (bool) $data['verified'],
            'fica_verified_on' => $data['verified'] ? now()->toDateString() : null,
        ]);
        activity('sales')->causedBy($request->user())->performedOn($buyer)->log($data['verified'] ? 'FICA verified' : 'FICA verification withdrawn');

        return back()->with('success', $data['verified'] ? 'FICA recorded as verified.' : 'FICA verification removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:190'],
            'entity_type' => ['required', 'in:individual,company,trust'],
            'id_number' => ['nullable', 'string', 'max:40'],
            'email' => ['nullable', 'email', 'max:190'],
            'phone' => ['nullable', 'string', 'max:30'],
            'address' => ['nullable', 'string', 'max:500'],
            'status' => ['required', 'in:enquiry,qualified,reserved,purchaser,lost'],
            'source' => ['nullable', 'string', 'max:30'],
            'owner' => ['nullable', 'string', Rule::exists('users', 'ulid')],
            'agent_supplier' => ['nullable', 'string', Rule::exists('suppliers', 'ulid')],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        return [
            ...Arr::except($data, ['owner', 'agent_supplier']),
            'owner_id' => isset($data['owner']) ? User::query()->where('ulid', $data['owner'])->value('id') : null,
            'agent_supplier_id' => isset($data['agent_supplier']) ? Supplier::query()->where('ulid', $data['agent_supplier'])->value('id') : null,
        ];
    }
}
