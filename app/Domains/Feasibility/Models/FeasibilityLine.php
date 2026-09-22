<?php

declare(strict_types=1);

namespace App\Domains\Feasibility\Models;

use App\Domains\Feasibility\Enums\LineBasis;
use App\Domains\Feasibility\Enums\LineCategory;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;

/**
 * One cost or revenue line, spread evenly between start_month and end_month.
 *
 * @property int $id
 * @property int $feasibility_id
 * @property LineCategory $category
 * @property string $description
 * @property LineBasis $basis
 * @property string|null $amount
 * @property string|null $rate
 * @property int $start_month
 * @property int $end_month
 * @property int $sort
 */
class FeasibilityLine extends Model
{
    use BelongsToCompany;

    protected $fillable = ['feasibility_id', 'category', 'description', 'basis', 'amount', 'rate', 'start_month', 'end_month', 'sort'];

    protected function casts(): array
    {
        return [
            'category' => LineCategory::class,
            'basis' => LineBasis::class,
            'amount' => 'decimal:2',
            'rate' => 'decimal:3',
            'start_month' => 'integer',
            'end_month' => 'integer',
            'sort' => 'integer',
        ];
    }
}
