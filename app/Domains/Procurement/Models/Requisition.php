<?php

declare(strict_types=1);

namespace App\Domains\Procurement\Models;

use App\Domains\Projects\Models\Project;
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
 * A request to buy something for a project. Draft -> submitted -> approved -> awarded (PO raised).
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $number
 * @property string $title
 * @property string|null $notes
 * @property Carbon|null $needed_by
 * @property string $status draft|submitted|approved|rejected|awarded|cancelled
 * @property string $estimated_total
 * @property int|null $awarded_quote_id
 * @property string|null $award_reason
 * @property string|null $single_source_reason
 * @property int $requested_by
 * @property-read Project $project
 * @property-read User $requester
 * @property-read Collection<int, RequisitionLine> $lines
 * @property-read Collection<int, RequisitionQuote> $quotes
 */
class Requisition extends Model implements Approvable
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = ['project_id', 'number', 'title', 'notes', 'needed_by', 'status', 'estimated_total', 'requested_by'];

    protected function casts(): array
    {
        return ['needed_by' => 'date', 'estimated_total' => 'decimal:2', 'number' => 'integer'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'estimated_total', 'awarded_quote_id', 'award_reason'])->logOnlyDirty();
    }

    public function reference(): string
    {
        return sprintf('REQ-%04d', $this->number);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    /**
     * @return HasMany<RequisitionLine, $this>
     */
    public function lines(): HasMany
    {
        return $this->hasMany(RequisitionLine::class)->orderBy('sort');
    }

    /**
     * @return HasMany<RequisitionQuote, $this>
     */
    public function quotes(): HasMany
    {
        return $this->hasMany(RequisitionQuote::class)->orderBy('amount');
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
        return "{$this->reference()} {$this->title}";
    }

    public function approvalUrl(): string
    {
        return route('requisitions.show', $this);
    }

    public function onApprovalGranted(ApprovalRequest $request): void
    {
        $this->update(['status' => 'approved']);
    }

    public function onApprovalRejected(ApprovalRequest $request, ?string $comment): void
    {
        $this->update(['status' => 'rejected']);
    }
}
