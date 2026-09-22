<?php

declare(strict_types=1);

namespace App\Domains\Site\Models;

use App\Domains\Procurement\Models\GoodsReceipt;
use App\Domains\Projects\Models\Project;
use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Carbon;

/**
 * Materials received on site.
 *
 * @property int $id
 * @property string $ulid
 * @property int $project_id
 * @property string $client_id
 * @property int|null $supplier_id
 * @property string|null $supplier_name
 * @property string|null $delivery_note_number
 * @property string $items
 * @property string $condition
 * @property string|null $notes
 * @property Carbon $received_at
 * @property int|null $received_by
 * @property-read User|null $receiver
 * @property-read Project $project
 */
class Delivery extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['project_id', 'client_id', 'supplier_id', 'supplier_name', 'delivery_note_number', 'items', 'condition', 'notes', 'received_at', 'received_by'];

    protected function casts(): array
    {
        return ['received_at' => 'datetime'];
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
    public function receiver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'received_by');
    }

    /**
     * @return HasOne<GoodsReceipt, $this>
     */
    public function goodsReceipt(): HasOne
    {
        return $this->hasOne(GoodsReceipt::class);
    }
}
