<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $type travel|site|tool|meal|housing|cellphone|other
 * @property string $amount
 * @property string $frequency day|month|once
 * @property Carbon $from_date
 * @property Carbon|null $to_date
 * @property string|null $notes
 */
class EmployeeAllowance extends Model
{
    use BelongsToCompany;

    protected $fillable = ['employee_id', 'type', 'amount', 'frequency', 'from_date', 'to_date', 'notes'];

    protected function casts(): array
    {
        return ['amount' => 'decimal:2', 'from_date' => 'date', 'to_date' => 'date'];
    }
}
