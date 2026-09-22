<?php

declare(strict_types=1);

namespace App\Domains\Workforce\Models;

use App\Domains\Documents\Models\Document;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * @property int $id
 * @property int $employee_id
 * @property string $type
 * @property int|null $document_id
 * @property Carbon|null $expires_on
 * @property Carbon $created_at
 */
class EmployeeDocument extends Model
{
    use BelongsToCompany;

    protected $fillable = ['employee_id', 'type', 'document_id', 'expires_on'];

    protected function casts(): array
    {
        return ['expires_on' => 'date'];
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
