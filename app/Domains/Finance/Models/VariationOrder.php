<?php

declare(strict_types=1);

namespace App\Domains\Finance\Models;

use App\Domains\Finance\Services\BudgetService;
use App\Domains\Projects\Models\Project;
use App\Domains\Workflow\Contracts\Approvable;
use App\Domains\Workflow\Models\ApprovalRequest;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

/**
 * A change to the scope or cost of the works. Approved variations adjust the budget line.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $budget_line_id
 * @property int|null $site_instruction_id
 * @property int $number
 * @property string $title
 * @property string|null $description
 * @property string $reason
 * @property string $amount
 * @property int $time_impact_days
 * @property string $status draft|pending_approval|approved|rejected
 * @property int $requested_by
 * @property Carbon|null $approved_at
 * @property-read Project $project
 * @property-read BudgetLine $budgetLine
 * @property-read User $requester
 */
class VariationOrder extends Model implements Approvable
{
    use BelongsToCompany, HasPublicUlid, LogsActivity;

    protected $fillable = ['project_id', 'budget_line_id', 'site_instruction_id', 'number', 'title', 'description', 'reason', 'amount', 'time_impact_days', 'status', 'requested_by'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'time_impact_days' => 'integer', 'number' => 'integer', 'approved_at' => 'datetime'];
    }

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()->logOnly(['status', 'amount', 'time_impact_days'])->logOnlyDirty();
    }

    public function reference(): string
    {
        return sprintf('VO-%03d', $this->number);
    }

    /**
     * @return BelongsTo<Project, $this>
     */
    public function project(): BelongsTo
    {
        return $this->belongsTo(Project::class);
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
    public function requester(): BelongsTo
    {
        return $this->belongsTo(User::class, 'requested_by');
    }

    public function approvalTitle(): string
    {
        return "{$this->reference()} {$this->title} ({$this->project->name})";
    }

    public function approvalUrl(): string
    {
        return route('projects.budget', $this->project).'#variations';
    }

    public function onApprovalGranted(ApprovalRequest $request): void
    {
        $this->forceFill(['status' => 'approved', 'approved_at' => now()])->save();
        app(BudgetService::class)->checkThresholds($this->budgetLine);
    }

    public function onApprovalRejected(ApprovalRequest $request, ?string $comment): void
    {
        $this->forceFill(['status' => 'rejected'])->save();
    }
}
