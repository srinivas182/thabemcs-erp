<?php

declare(strict_types=1);

namespace App\Domains\Documents\Models;

use App\Models\User;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * An immutable uploaded file. New uploads create a new version; old versions are kept.
 *
 * @property int $id
 * @property int $document_id
 * @property int $version
 * @property string|null $revision
 * @property string $disk
 * @property string $path
 * @property string $original_name
 * @property string $mime_type
 * @property int $size_bytes
 * @property string $sha256
 * @property string|null $notes
 * @property int|null $uploaded_by
 * @property Carbon $created_at
 * @property-read User|null $uploader
 * @property-read Document $document
 */
class DocumentVersion extends Model
{
    use BelongsToCompany;

    protected $fillable = ['document_id', 'version', 'revision', 'disk', 'path', 'original_name', 'mime_type', 'size_bytes', 'sha256', 'notes', 'uploaded_by'];

    protected function casts(): array
    {
        return ['version' => 'integer', 'size_bytes' => 'integer'];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<Document, $this>
     */
    public function document(): BelongsTo
    {
        return $this->belongsTo(Document::class);
    }
}
