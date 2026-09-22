<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A batch of approved supplier invoices paid together. Draft -> pending_approval -> approved -> paid.
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property Carbon $pay_on
 * @property string $status
 * @property string $total
 * @property int $created_by
 * @property Carbon|null $approved_at
 * @property Carbon|null $paid_at
 * @property-read User $creator
 * @property-read Collection<int, SupplierInvoice> $invoices
 */
class PaymentRun extends Model implements Approvable
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = ['number', 'pay_on', 'status', 'total', 'created_by'];

    protected function casts(): array
    {
        return ['pay_on' => 'date', 'total' => 'decimal:2', 'number' => 'integer', 'approved_at' => 'datetime', 'paid_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'total'])->logOnlyDirty();
    }

    public function reference(): string
    {
        return sprintf('PR-%04d', $this->number);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /**
     * @return HasMany<SupplierInvoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(SupplierInvoice::class);
    }

    public function approvalTitle(): string
    {
        return "Payment run {$this->reference()}";
    }

    public function approvalUrl(): string
    {
        return route('payment-runs.show', $this);
    }

    public function onApprovalGranted(ApprovalRequest $request): void
    {
        $this->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
    }

    public function onApprovalRejected(ApprovalRequest $request, ?string $comment): void
    {
        // Rejected runs release their invoices so they can go into another run.
        $this->invoices()->update(['payment_run_id' => null, 'status' => 'approved']);
        $this->forceFill(['status' => 'rejected'])->save();
    }
}
