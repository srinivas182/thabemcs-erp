<?php

declare(strict_types=1);

namespace App\Domains\Compliance\Models;

use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * A request from a person to see, correct or delete their personal information (POPIA s23-25).
 *
 * @property int $id
 * @property string $ulid
 * @property int $number
 * @property string $requester_name
 * @property string|null $requester_email
 * @property string $type access|correction|deletion|objection
 * @property string $subject_type employee|user|investor|other
 * @property int|null $subject_id
 * @property string|null $details
 * @property Carbon $received_on
 * @property Carbon $due_on
 * @property string $status open|in_progress|completed|refused
 * @property string|null $outcome
 * @property int|null $handled_by
 * @property Carbon|null $completed_at
 * @property-read User|null $handler
 */
class DataSubjectRequest extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['number', 'requester_name', 'requester_email', 'type', 'subject_type', 'subject_id', 'details', 'received_on', 'due_on', 'status', 'outcome'];

    protected function casts(): array
    {
        return ['received_on' => 'date', 'due_on' => 'date', 'completed_at' => 'datetime', 'number' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function handler(): BelongsTo
    {
        return $this->belongsTo(User::class, 'handled_by');
    }
}
