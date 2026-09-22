<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Contracts\Models\PaymentCertificate;
use App\Domains\Procurement\Models\PurchaseOrder;
use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A supplier's tax invoice. Captured -> matched / exception -> approved -> scheduled -> paid (or rejected).
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $supplier_id
 * @property int|null $purchase_order_id
 * @property int|null $budget_line_id
 * @property int|null $payment_certificate_id
 * @property int|null $payment_run_id
 * @property string $invoice_number
 * @property Carbon $invoice_date
 * @property Carbon $due_date
 * @property string $subtotal
 * @property string $vat
 * @property string $total
 * @property string $status
 * @property list<string>|null $match_issues
 * @property string|null $override_reason
 * @property int|null $document_id
 * @property int $captured_by
 * @property int|null $approved_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property-read Project $project
 * @property-read Supplier $supplier
 * @property-read PurchaseOrder|null $purchaseOrder
 * @property-read BudgetLine|null $budgetLine
 * @property-read User|null $approver
 * @property-read PaymentRun|null $paymentRun
 * @property-read PaymentCertificate|null $certificate
 */
class SupplierInvoice extends Model
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = [
        'project_id', 'supplier_id', 'purchase_order_id', 'payment_certificate_id', 'budget_line_id', 'invoice_number', 'invoice_date', 'due_date',
        'subtotal', 'vat', 'total', 'document_id', 'captured_by',
    ];

    protected function casts(): array
    {
        return [
            'invoice_date' => 'date', 'due_date' => 'date', 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2',
            'match_issues' => 'array', 'approved_at' => 'datetime', 'paid_at' => 'datetime',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total', 'override_reason', 'payment_run_id'])->logOnlyDirty();
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }

    /**
     * @return BelongsTo<PurchaseOrder, $this>
     */
    public function purchaseOrder(): BelongsTo
    {
        return $this->belongsTo(PurchaseOrder::class);
    }

    /**
     * @return BelongsTo<BudgetLine, $this>
     */
    public function budgetLine(): BelongsTo
    {
        return $this->belongsTo(BudgetLine::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return BelongsTo<PaymentRun, $this>
     */
    public function paymentRun(): BelongsTo
    {
        return $this->belongsTo(PaymentRun::class);
    }

    /**
     * @return BelongsTo<PaymentCertificate, $this>
     */
    public function certificate(): BelongsTo
    {
        return $this->belongsTo(PaymentCertificate::class, 'payment_certificate_id');
    }
}
