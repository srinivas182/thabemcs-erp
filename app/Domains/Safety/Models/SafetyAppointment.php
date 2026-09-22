<?php

declare(strict_types=1);

namespace App\Domains\Safety\Models;

use App\Domains\Suppliers\Models\Supplier;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A legal H&S appointment on a project (Construction Regulations 2014 / OHS Act).
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $type
 * @property string $appointee_name
 * @property int|null $employee_id
 * @property int|null $supplier_id
 * @property Carbon $appointed_on
 * @property Carbon|null $competency_expires_on
 * @property Carbon|null $ended_on
 * @property int|null $document_id
 * @property int $recorded_by
 * @property-read Supplier|null $supplier
 */
class SafetyAppointment extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'type', 'appointee_name', 'employee_id', 'supplier_id', 'appointed_on', 'competency_expires_on', 'ended_on', 'document_id', 'recorded_by'];

    protected function casts(): array
    {
        return ['appointed_on' => 'date', 'competency_expires_on' => 'date', 'ended_on' => 'date'];
    }

    /**
     * @return BelongsTo<Supplier, $this>
     */
    public function supplier(): BelongsTo
    {
        return $this->belongsTo(Supplier::class);
    }
}
