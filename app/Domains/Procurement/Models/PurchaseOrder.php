<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * Draft -> pending_approval -> approved -> issued -> partially_received -> received (or cancelled / rejected).
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $supplier_id
 * @property int|null $requisition_id
 * @property int $number
 * @property string $status
 * @property bool $vat_applies
 * @property string $subtotal
 * @property string $vat
 * @property string $total
 * @property Carbon|null $expected_delivery
 * @property string|null $delivery_instructions
 * @property int $created_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $issued_at
 * @property-read Project $project
 * @property-read Supplier $supplier
 * @property-read Requisition|null $requisition
 * @property-read User $creator
 * @property-read Collection<int, PurchaseOrderLine> $lines
 * @property-read Collection<int, GoodsReceipt> $receipts
 */
class PurchaseOrder extends Model implements Approvable
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = ['project_id', 'supplier_id', 'requisition_id', 'number', 'status', 'vat_applies', 'expected_delivery', 'delivery_instructions', 'created_by'];

    protected function casts(): array
    {
        return [
            'vat_applies' => 'boolean', 'subtotal' => 'decimal:2', 'vat' => 'decimal:2', 'total' => 'decimal:2',
            'expected_delivery' => 'date', 'approved_at' => 'datetime', 'issued_at' => 'datetime', 'number' => 'integer',
        ];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'subtotal', 'total', 'supplier_id'])->logOnlyDirty();
    }

    public function reference(): string
    {
        return sprintf('PO-%04d', $this->number);
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
     * @return BelongsTo<Requisition, $this>
     */
    public function requisition(): BelongsTo
    {
        return $this->belongsTo(Requisition::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<PurchaseOrderLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(PurchaseOrderLine::class)->orderBy('sort');
    }

    /**
     * @return HasMany<GoodsReceipt, $this>
     */
    public function receipts(): HasMany
    {
        return $this->hasMany(GoodsReceipt::class)->latest('id');
    }

    /**
     * @return MorphMany<ApprovalRequest, $this>
     */
    public function approvals(): MorphMany
    {
        return $this->morphMany(ApprovalRequest::class, 'approvable')->latest('id');
    }

    public function approvalTitle(): string
    {
        return "{$this->reference()} to {$this->supplier->name}";
    }

    public function approvalUrl(): string
    {
        return route('purchase-orders.show', $this);
    }

    public function onApprovalGranted(ApprovalRequest $request): void
    {
        $this->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
    }

    public function onApprovalRejected(ApprovalRequest $request, ?string $comment): void
    {
        $this->forceFill(['status' => 'draft'])->save();
    }

    /**
     * Recalculate totals from the lines (VAT at the standard rate when the supplier is a VAT vendor).
     */
    public function recalculate(): void
    {
        $subtotal = round((float) $this->lines()->get()->sum(static fn (PurchaseOrderLine $l): float => (float) $l->quantity * (float) $l->unit_price), 2);
        $vat = $this->vat_applies ? round($subtotal * (float) config('delegation_of_authority.vat_rate'), 2) : 0.0;

        $this->forceFill(['subtotal' => $subtotal, 'vat' => $vat, 'total' => $subtotal + $vat])->save();
    }
}
