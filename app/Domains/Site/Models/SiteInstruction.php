<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A numbered written instruction to a contractor.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property int $number
 * @property int|null $supplier_id
 * @property string $subject
 * @property string $instruction
 * @property bool $cost_implication
 * @property bool $time_implication
 * @property string $status
 * @property int|null $issued_by
 * @property Carbon $issued_at
 * @property-read User|null $issuer
 * @property-read Project $project
 */
class SiteInstruction extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'number', 'supplier_id', 'subject', 'instruction', 'cost_implication', 'time_implication', 'status', 'issued_by', 'issued_at'];

    protected function casts(): array
    {
        return ['cost_implication' => 'boolean', 'time_implication' => 'boolean', 'issued_at' => 'datetime', 'number' => 'integer'];
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
    public function issuer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }
}
