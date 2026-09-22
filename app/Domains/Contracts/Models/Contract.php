<?php

declare(strict_types=1);

namespace App\Domains\Contracts\Models;

use App\Domains\Projects\Models\Project;
use App\Domains\Suppliers\Models\Supplier;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Carbon;

/**
 * A construction contract with a contractor (JBCC, NEC, GCC or other), valued monthly by payment certificates.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $supplier_id
 * @property int|null $budget_line_id
 * @property string $reference
 * @property string $contract_form
 * @property string $contract_sum
 * @property string $retention_percent
 * @property string|null $retention_cap_percent
 * @property string $release_at_practical_percent
 * @property Carbon|null $practical_completion_on
 * @property Carbon|null $final_completion_on
 * @property string $status
 * @property-read Project $project
 * @property-read Supplier $supplier
 * @property-read Collection<int, PaymentCertificate> $certificates
 */
class Contract extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'supplier_id', 'budget_line_id', 'reference', 'contract_form', 'contract_sum', 'retention_percent', 'retention_cap_percent', 'release_at_practical_percent', 'practical_completion_on', 'final_completion_on', 'status'];

    protected function casts(): array
    {
        return ['contract_sum' => 'decimal:2', 'retention_percent' => 'decimal:2', 'retention_cap_percent' => 'decimal:2', 'release_at_practical_percent' => 'decimal:2', 'practical_completion_on' => 'date', 'final_completion_on' => 'date'];
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
     * @return HasMany<PaymentCertificate, $this>
     */
    public function certificates(): HasMany
    {
        return $this->hasMany(PaymentCertificate::class);
    }
}
