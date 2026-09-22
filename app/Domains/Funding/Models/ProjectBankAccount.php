<?php

declare(strict_types=1);

namespace App\Domains\Funding\Models;

use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Carbon;

/**
 * The ring-fenced bank account for a project. Only the last four digits are stored.
 *
 * @property int $id
 * @property int $project_id
 * @property string $bank
 * @property string $account_name
 * @property string $account_last4
 * @property Carbon|null $opened_on
 */
class ProjectBankAccount extends Model
{
    use BelongsToCompany;

    protected $fillable = ['project_id', 'bank', 'account_name', 'account_last4', 'opened_on'];

    protected function casts(): array
    {
        return ['opened_on' => 'date'];
    }
}
