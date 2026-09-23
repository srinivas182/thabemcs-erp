<?php

declare(strict_types=1);

namespace App\Domains\Rentals\Models;

use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * Someone renting, from application to former tenant. ID numbers are encrypted (POPIA).
 *
 * @property int $id
 * @property string $ulid
 * @property string $name
 * @property string $entity_type
 * @property string|null $id_number
 * @property string|null $email
 * @property string|null $phone
 * @property string|null $employer
 * @property string $status applicant|approved|current|former|declined
 * @property bool $fica_verified
 * @property bool $credit_checked
 * @property Carbon|null $screened_on
 * @property string|null $accounting_ref
 * @property string|null $notes
 */
class Tenant extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['name', 'entity_type', 'id_number', 'email', 'phone', 'employer', 'status', 'fica_verified', 'credit_checked', 'screened_on', 'accounting_ref', 'notes'];

    protected function casts(): array
    {
        return ['id_number' => 'encrypted', 'fica_verified' => 'boolean', 'credit_checked' => 'boolean', 'screened_on' => 'date'];
    }
}
