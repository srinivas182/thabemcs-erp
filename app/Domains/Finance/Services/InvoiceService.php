<?php

declare(strict_types=1);

namespace App\Domains\Finance\Services;

use App\Domains\Finance\Exceptions\FinanceException;
use App\Domains\Finance\Models\SupplierInvoice;
use App\Domains\Platform\Services\WebhookDispatcher;
use App\Domains\Procurement\Models\PurchaseOrderLine;
use App\Models\User;

/**
 * Three-way match: purchase order, goods received and invoice must agree before an invoice is approved.
 * Exceptions can only be approved by a Director or Company Admin with a written reason.
 */
final class InvoiceService
{
    public function __construct(private readonly WebhookDispatcher $webhooks) {}

    /**
     * Run the checks and set the invoice to "matched" or "exception".
     *
     * @return list<string> issues found
     */
    public function match(SupplierInvoice $invoice): array
    {
        $issues = [];
        $tolerance = (float) config('delegation_of_authority.match_tolerance', 5);
        $subtotal = (float) $invoice->subtotal;
        $vat = (float) $invoice->vat;
        $supplier = $invoice->supplier;

        if (abs($subtotal + $vat - (float) $invoice->total) > 0.01) {
            $issues[] = 'The total does not equal the amount plus VAT.';
        }

        if ($supplier->vat_number === null && $vat > 0) {
            $issues[] = 'VAT is charged but the supplier has no VAT number on record, so this is not a valid tax invoice.';
        }
        if ($supplier->vat_number !== null) {
            $expected = round($subtotal * (float) config('delegation_of_authority.vat_rate'), 2);
            if (abs($vat - $expected) > 1.0) {
                $issues[] = sprintf('VAT should be R%s (15%%) but the invoice shows R%s.', number_format($expected, 2, '.', ' '), number_format($vat, 2, '.', ' '));
            }
        }

        $order = $invoice->purchaseOrder;
        $certificate = $invoice->certificate;
        if ($certificate !== null) {
            // Contractor invoices are matched to a certified payment certificate instead of a purchase order.
            if ($certificate->status !== 'certified') {
                $issues[] = 'The payment certificate has not been certified.';
            }
            if ($certificate->contract->supplier_id !== $invoice->supplier_id) {
                $issues[] = 'The payment certificate is for a different contractor.';
            }
            $previous = (float) SupplierInvoice::query()->where('payment_certificate_id', $certificate->id)->whereKeyNot($invoice->id)
                ->whereNotIn('status', ['rejected'])->sum('subtotal');
            if ($subtotal + $previous > (float) $certificate->amount_due + $tolerance) {
                $issues[] = sprintf('The certificate allows R%s; R%s has been invoiced.', number_format((float) $certificate->amount_due, 2, '.', ' '), number_format($subtotal + $previous, 2, '.', ' '));
            }
        } elseif ($order === null) {
            $issues[] = 'There is no purchase order for this invoice.';
        } else {
            if ($order->supplier_id !== $invoice->supplier_id) {
                $issues[] = 'The purchase order is for a different supplier.';
            }
            if (! in_array($order->status, ['issued', 'partially_received', 'received'], true)) {
                $issues[] = 'The purchase order has not been issued.';
            }

            $received = (float) PurchaseOrderLine::query()->where('purchase_order_id', $order->id)->get()
                ->sum(static fn (PurchaseOrderLine $l): float => (float) $l->received_quantity * (float) $l->unit_price);
            $previous = (float) SupplierInvoice::query()->where('purchase_order_id', $order->id)->whereKeyNot($invoice->id)
                ->whereNotIn('status', ['rejected'])->sum('subtotal');

            if ($subtotal + $previous > (float) $order->subtotal + $tolerance) {
                $issues[] = sprintf('Invoiced R%s against an order of R%s.', number_format($subtotal + $previous, 2, '.', ' '), number_format((float) $order->subtotal, 2, '.', ' '));
            }
            if ($subtotal + $previous > $received + $tolerance) {
                $issues[] = sprintf('Only R%s of goods have been received against this order, but R%s has been invoiced.', number_format($received, 2, '.', ' '), number_format($subtotal + $previous, 2, '.', ' '));
            }
        }

        $invoice->forceFill(['match_issues' => $issues ?: null, 'status' => $issues ? 'exception' : 'matched'])->save();

        return $issues;
    }

    /**
     * @throws FinanceException
     */
    public function approve(SupplierInvoice $invoice, User $by, ?string $overrideReason): void
    {
        if (! in_array($invoice->status, ['matched', 'exception'], true)) {
            throw new FinanceException('Only matched invoices, or exceptions with an override, can be approved.');
        }
        if ($invoice->captured_by === $by->id) {
            throw new FinanceException('The person who captured an invoice cannot also approve it.');
        }
        if ($invoice->status === 'exception') {
            if (! $by->can('override-invoice-match')) {
                throw new FinanceException('This invoice failed the three-way match. A Director or Company Admin must approve it with a reason.');
            }
            if (! $overrideReason) {
                throw new FinanceException('Give the reason for approving an invoice that failed the match.');
            }
        }

        $invoice->forceFill([
            'status' => 'approved', 'approved_by' => $by->id, 'approved_at' => now(),
            'override_reason' => $invoice->status === 'exception' ? $overrideReason : null,
        ])->save();

        $this->webhooks->send('invoice.approved', [
            'invoice' => $invoice->invoice_number, 'supplier' => $invoice->supplier->name,
            'total' => (float) $invoice->total, 'dueOn' => $invoice->due_date->toDateString(),
        ]);
    }

    public function reject(SupplierInvoice $invoice, string $reason, User $by): void
    {
        if (in_array($invoice->status, ['scheduled', 'paid'], true)) {
            throw new FinanceException('Scheduled or paid invoices cannot be rejected.');
        }
        $invoice->forceFill(['status' => 'rejected', 'override_reason' => $reason])->save();
        activity('finance')->causedBy($by)->performedOn($invoice)->withProperties(['reason' => $reason])->log('Invoice rejected');
    }
}
