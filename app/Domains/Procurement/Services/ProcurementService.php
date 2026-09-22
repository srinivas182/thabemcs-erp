<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Services;

use App\Domains\Procurement\Exceptions\ProcurementException;
use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Procurement\Models\GoodsReceiptLine;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Domains\Procurement\Models\Requisition;
use App\Domains\Procurement\Models\RequisitionLine;
use App\Domains\Procurement\Models\RequisitionQuote;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Exceptions\SupplierNotCompliantException;
use App\Domains\Suppliers\Services\ComplianceService;
use App\Domains\Workflow\Services\ApprovalEngine;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Requisition -> quotes -> award -> purchase order -> approval -> issue -> goods received.
 */
final class ProcurementService
{
    public function __construct(
        private readonly ApprovalEngine $approvals,
        private readonly ComplianceService $compliance,
    ) {}

    /**
     * @param  list<array{description: string, quantity: float|string, unit: string, estimated_unit_price: float|string}>  $lines
     */
    public function createRequisition(Project $project, string $title, ?string $neededBy, ?string $notes, array $lines, User $by): Requisition
    {
        return DB::transaction(function () use ($project, $title, $neededBy, $notes, $lines, $by): Requisition {
            $requisition = Requisition::query()->create([
                'project_id' => $project->id,
                'number' => $this->next(Requisition::query()),
                'title' => $title,
                'needed_by' => $neededBy,
                'notes' => $notes,
                'status' => 'draft',
                'requested_by' => $by->id,
            ]);

            $total = 0.0;
            foreach ($lines as $i => $line) {
                RequisitionLine::query()->create([...$line, 'requisition_id' => $requisition->id, 'sort' => $i]);
                $total += (float) $line['quantity'] * (float) $line['estimated_unit_price'];
            }
            $requisition->update(['estimated_total' => round($total, 2)]);

            return $requisition;
        });
    }

    public function submitRequisition(Requisition $requisition, User $by): void
    {
        if ($requisition->status !== 'draft') {
            throw new ProcurementException('Only draft requisitions can be submitted.');
        }

        DB::transaction(function () use ($requisition, $by): void {
            $requisition->update(['status' => 'submitted']);
            $this->approvals->submit($requisition, 'requisition', (float) $requisition->estimated_total, $by);
        });
    }

    /**
     * Choose a quote and raise a draft purchase order.
     *
     * Rules: at least three quotes above the threshold unless a single-source reason is given,
     * and a written reason when the lowest quote is not chosen.
     */
    public function award(Requisition $requisition, RequisitionQuote $quote, ?string $reason, ?string $singleSourceReason, User $by): PurchaseOrder
    {
        if ($requisition->status !== 'approved') {
            throw new ProcurementException('The requisition must be approved before a quote is awarded.');
        }
        if ($quote->requisition_id !== $requisition->id) {
            throw new ProcurementException('That quote belongs to a different requisition.');
        }

        $quotes = $requisition->quotes()->get();
        $threshold = (float) config('delegation_of_authority.three_quote_threshold');

        if ((float) $quote->amount > $threshold && $quotes->count() < 3 && ! $singleSourceReason) {
            throw new ProcurementException(sprintf('Three quotes are required above R%s. Get more quotes or record why only one supplier can do this work.', number_format($threshold, 0, '.', ' ')));
        }

        $lowest = (float) $quotes->min('amount');
        if ((float) $quote->amount > $lowest && ! $reason) {
            throw new ProcurementException('This is not the lowest quote. Record why it was chosen.');
        }

        $supplier = $quote->supplier;
        $this->compliance->ensureCanTransact($supplier, (float) $quote->amount * (1 + (float) config('delegation_of_authority.vat_rate')));

        return DB::transaction(function () use ($requisition, $quote, $reason, $singleSourceReason, $by, $supplier): PurchaseOrder {
            $requisition->forceFill([
                'status' => 'awarded', 'awarded_quote_id' => $quote->id,
                'award_reason' => $reason, 'single_source_reason' => $singleSourceReason,
            ])->save();

            $order = PurchaseOrder::query()->create([
                'project_id' => $requisition->project_id,
                'supplier_id' => $supplier->id,
                'requisition_id' => $requisition->id,
                'number' => $this->next(PurchaseOrder::query()),
                'status' => 'draft',
                'vat_applies' => $supplier->vat_number !== null,
                'created_by' => $by->id,
            ]);

            $this->copyLinesAtQuotedPrice($requisition, $order, (float) $quote->amount);
            $order->recalculate();

            return $order;
        });
    }

    /**
     * @throws SupplierNotCompliantException
     */
    public function submitOrder(PurchaseOrder $order, User $by): void
    {
        if (! in_array($order->status, ['draft'], true)) {
            throw new ProcurementException('Only draft purchase orders can be submitted for approval.');
        }
        if ((float) $order->subtotal <= 0) {
            throw new ProcurementException('Add at least one line with a price.');
        }

        $this->compliance->ensureCanTransact($order->supplier, (float) $order->total);

        DB::transaction(function () use ($order, $by): void {
            $order->forceFill(['status' => 'pending_approval'])->save();
            $this->approvals->submit($order, 'purchase_order', (float) $order->subtotal, $by);
        });
    }

    public function issue(PurchaseOrder $order): void
    {
        if ($order->status !== 'approved') {
            throw new ProcurementException('Only approved purchase orders can be issued.');
        }
        // Compliance is checked again at issue: documents may have expired since approval.
        $this->compliance->ensureCanTransact($order->supplier, (float) $order->total);
        $order->forceFill(['status' => 'issued', 'issued_at' => now()])->save();
    }

    /**
     * @param  array<int, float>  $quantities  purchase_order_line_id => quantity received now
     */
    public function receive(PurchaseOrder $order, array $quantities, string $receivedOn, ?int $deliveryId, ?string $notes, User $by): GoodsReceipt
    {
        if (! in_array($order->status, ['issued', 'partially_received'], true)) {
            throw new ProcurementException('Goods can only be received against an issued purchase order.');
        }

        return DB::transaction(function () use ($order, $quantities, $receivedOn, $deliveryId, $notes, $by): GoodsReceipt {
            $lines = PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->lockForUpdate()->get()->keyBy('id');
            $receipt = GoodsReceipt::query()->create([
                'purchase_order_id' => $order->id, 'delivery_id' => $deliveryId, 'number' => $this->next(GoodsReceipt::query()),
                'received_on' => $receivedOn, 'notes' => $notes, 'received_by' => $by->id,
            ]);

            $any = false;
            foreach ($quantities as $lineId => $qty) {
                $line = $lines->get($lineId);
                if ($line === null || $qty <= 0) {
                    continue;
                }
                if ($qty > $line->outstanding() + 0.0005) {
                    throw new ProcurementException("More received than ordered for \"{$line->description}\" ({$line->outstanding()} outstanding).");
                }
                GoodsReceiptLine::query()->create(['goods_receipt_id' => $receipt->id, 'purchase_order_line_id' => $line->id, 'quantity' => $qty]);
                $line->forceFill(['received_quantity' => (float) $line->received_quantity + $qty])->save();
                $any = true;
            }

            if (! $any) {
                throw new ProcurementException('Enter the quantity received for at least one line.');
            }

            $complete = $lines->every(static fn (PurchaseOrderLine $l): bool => $l->fresh()?->outstanding() <= 0.0005);
            $order->forceFill(['status' => $complete ? 'received' : 'partially_received'])->save();

            return $receipt;
        });
    }

    /**
     * Requisition lines are copied onto the PO with prices scaled so the PO subtotal equals the quote.
     * Buyers can then adjust individual line prices to match the supplier's quotation.
     */
    private function copyLinesAtQuotedPrice(Requisition $requisition, PurchaseOrder $order, float $quoted): void
    {
        $lines = $requisition->lines()->get();
        $estimate = (float) $requisition->estimated_total;

        if ($lines->isEmpty() || $estimate <= 0) {
            PurchaseOrderLine::query()->create([
                'purchase_order_id' => $order->id, 'description' => $requisition->title, 'quantity' => 1, 'unit' => 'item', 'unit_price' => $quoted, 'sort' => 0,
            ]);

            return;
        }

        $factor = $quoted / $estimate;
        $running = 0.0;
        foreach ($lines as $i => $line) {
            $isLast = $i === $lines->count() - 1;
            $qty = (float) $line->quantity;
            $price = $isLast && $qty > 0
                ? round(($quoted - $running) / $qty, 2)
                : round((float) $line->estimated_unit_price * $factor, 2);
            $running += $price * $qty;

            PurchaseOrderLine::query()->create([
                'purchase_order_id' => $order->id, 'description' => $line->description, 'quantity' => $qty,
                'unit' => $line->unit, 'unit_price' => $price, 'sort' => $i,
            ]);
        }
    }

    /**
     * Next sequential number in the current company.
     *
     * @param  Builder<covariant Model>  $query
     */
    private function next(Builder $query): int
    {
        return (int) $query->lockForUpdate()->max('number') + 1;
    }
}
