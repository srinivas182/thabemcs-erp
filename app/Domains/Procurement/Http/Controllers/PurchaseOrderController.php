<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Http\Controllers;

use App\Domains\Platform\Enums\Role;
use App\Domains\Procurement\Exceptions\ProcurementException;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Procurement\Services\ProcurementService;
use App\Domains\Site\Models\Delivery;
use App\Domains\Suppliers\Exceptions\SupplierNotCompliantException;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Domains\Workflow\Exceptions\ApprovalException;
use App\Domains\Workflow\Models\ApprovalStep;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

final class PurchaseOrderController
{
    public function __construct(private readonly ProcurementService $procurement, private readonly ComplianceService $compliance) {}

    public function index(Request $request): Response
    {
        $status = $request->string('status')->toString();

        return Inertia::render('procurement/orders', [
            'orders' => PurchaseOrder::query()->with(['project:id,name', 'supplier:id,name'])
                ->when($status !== '', fn ($q) => $q->where('status', $status))
                ->latest('id')->paginate(25)->withQueryString()
                ->through(static fn (PurchaseOrder $o): array => [
                    'id' => $o->ulid, 'reference' => $o->reference(), 'project' => $o->project->name, 'supplier' => $o->supplier->name,
                    'status' => $o->status, 'total' => (float) $o->total, 'expected' => $o->expected_delivery?->toDateString(),
                ]),
            'filter' => $status,
        ]);
    }

    public function show(Request $request, PurchaseOrder $order): Response
    {
        $order->load(['project:id,ulid,name', 'supplier', 'requisition:id,ulid,number', 'lines', 'receipts.receiver:id,name']);
        $approval = $order->approvals()->with('steps.decider:id,name')->first();

        return Inertia::render('procurement/order', [
            'order' => [
                'id' => $order->ulid, 'reference' => $order->reference(), 'status' => $order->status,
                'project' => ['id' => $order->project->ulid, 'name' => $order->project->name],
                'supplier' => ['id' => $order->supplier->ulid, 'name' => $order->supplier->name, 'vat' => $order->supplier->vat_number],
                'requisition' => $order->requisition ? ['id' => $order->requisition->ulid, 'reference' => $order->requisition->reference()] : null,
                'subtotal' => (float) $order->subtotal, 'vat' => (float) $order->vat, 'total' => (float) $order->total, 'vatApplies' => $order->vat_applies,
                'expectedDelivery' => $order->expected_delivery?->toDateString(), 'instructions' => $order->delivery_instructions,
                'approvedAt' => $order->approved_at?->toIso8601String(), 'issuedAt' => $order->issued_at?->toIso8601String(),
            ],
            'lines' => $order->lines->map(static fn (PurchaseOrderLine $l): array => [
                'id' => $l->id, 'description' => $l->description, 'quantity' => (float) $l->quantity, 'unit' => $l->unit,
                'unit_price' => (float) $l->unit_price, 'received' => (float) $l->received_quantity, 'outstanding' => $l->outstanding(),
            ]),
            'receipts' => $order->receipts->map(static fn (GoodsReceipt $r): array => [
                'id' => $r->ulid, 'reference' => sprintf('GRN-%04d', $r->number), 'on' => $r->received_on->toDateString(), 'by' => $r->receiver->name, 'notes' => $r->notes,
            ]),
            'approval' => $approval ? [
                'status' => $approval->status,
                'steps' => $approval->steps->map(static fn (ApprovalStep $s): array => [
                    'sequence' => $s->sequence, 'role' => Role::tryFrom($s->role)?->label() ?? $s->role, 'decision' => $s->decision,
                    'by' => $s->decider?->name, 'at' => $s->decided_at?->toIso8601String(), 'comment' => $s->comment,
                    'current' => $approval->status === 'pending' && $approval->current_step === $s->sequence,
                ])->values(),
            ] : null,
            'blockers' => $this->compliance->blockers($order->supplier, (float) $order->total),
            'deliveries' => in_array($order->status, ['issued', 'partially_received'], true)
                ? Delivery::query()->where('project_id', $order->project_id)->whereDoesntHave('goodsReceipt')->latest('received_at')->limit(20)->get()
                    ->map(static fn (Delivery $d): array => ['key' => $d->ulid, 'label' => trim(($d->supplier_name ?? '').' '.($d->delivery_note_number ? "DN {$d->delivery_note_number}" : '').', '.$d->received_at->format('j M'))])->values()
                : [],
            'can' => [
                'procure' => $request->user()?->can('manage-procurement') ?? false,
                'receive' => ($request->user()?->can('manage-procurement') || $request->user()?->can('manage-site')) ?? false,
            ],
        ]);
    }

    public function updateLines(Request $request, PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('manage-procurement');
        if ($order->status !== 'draft') {
            return back()->with('error', 'Only draft purchase orders can be changed.');
        }

        $data = $request->validate([
            'expected_delivery' => ['nullable', 'date'],
            'delivery_instructions' => ['nullable', 'string', 'max:2000'],
            'lines' => ['required', 'array', 'min:1', 'max:200'],
            'lines.*.description' => ['required', 'string', 'max:255'],
            'lines.*.quantity' => ['required', 'numeric', 'gt:0'],
            'lines.*.unit' => ['required', 'string', 'max:16'],
            'lines.*.unit_price' => ['required', 'numeric', 'min:0'],
        ]);

        DB::transaction(function () use ($order, $data): void {
            $order->update(['expected_delivery' => $data['expected_delivery'] ?? null, 'delivery_instructions' => $data['delivery_instructions'] ?? null]);
            $order->lines()->delete();
            foreach (array_values($data['lines']) as $i => $line) {
                PurchaseOrderLine::query()->create([...$line, 'purchase_order_id' => $order->id, 'sort' => $i]);
            }
            $order->recalculate();
        });

        return back()->with('success', 'Purchase order saved.');
    }

    public function submit(Request $request, PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('manage-procurement');
        /** @var User $user */
        $user = $request->user();

        try {
            $this->procurement->submitOrder($order, $user);
        } catch (ProcurementException|ApprovalException|SupplierNotCompliantException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', 'Submitted for approval.');
    }

    public function issue(PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('manage-procurement');

        try {
            $this->procurement->issue($order);
        } catch (ProcurementException|SupplierNotCompliantException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', "{$order->reference()} issued to {$order->supplier->name}.");
    }

    public function receive(Request $request, PurchaseOrder $order): RedirectResponse
    {
        abort_unless($request->user()?->can('manage-procurement') || $request->user()?->can('manage-site'), 403);
        $data = $request->validate([
            'received_on' => ['required', 'date', 'before_or_equal:today'],
            'delivery' => ['nullable', 'string'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'quantities' => ['required', 'array'],
            'quantities.*' => ['nullable', 'numeric', 'min:0'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $deliveryId = isset($data['delivery']) ? Delivery::query()->where('ulid', $data['delivery'])->where('project_id', $order->project_id)->value('id') : null;
        $quantities = [];
        foreach ((array) $data['quantities'] as $lineId => $qty) {
            $quantities[(int) $lineId] = (float) $qty;
        }

        try {
            $receipt = $this->procurement->receive($order, $quantities, (string) $data['received_on'], $deliveryId, $data['notes'] ?? null, $user);
        } catch (ProcurementException $e) {
            return back()->with('error', $e->getMessage());
        }

        return back()->with('success', sprintf('Goods received note GRN-%04d recorded.', $receipt->number));
    }

    public function cancel(PurchaseOrder $order): RedirectResponse
    {
        Gate::authorize('manage-procurement');
        if (! in_array($order->status, ['draft', 'approved'], true)) {
            return back()->with('error', 'Only draft or approved (not yet issued) purchase orders can be cancelled.');
        }
        $order->forceFill(['status' => 'cancelled'])->save();

        return back()->with('success', "{$order->reference()} cancelled.");
    }
}
