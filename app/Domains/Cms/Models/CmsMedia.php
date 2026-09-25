<?php

declare(strict_types=1);

namespace App\Domains\Cms\Models;

use App\Models\User;
use App\Support\HasPublicUlid;
use App\Support\Tenancy\BelongsToCompany;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

/**
 * An image or document in the media library, served publicly on the website.
 *
 * @property int $id
 * @property string $ulid
 * @property string $path
 * @property string $file_name
 * @property string $mime_type
 * @property int $bytes
 * @property int|null $width
 * @property int|null $height
 * @property string|null $alt
 * @property string|null $title
 * @property int $uploaded_by
 */
class CmsMedia extends Model
{
    use BelongsToCompany, HasPublicUlid;

    protected $fillable = ['path', 'file_name', 'mime_type', 'bytes', 'width', 'height', 'alt', 'title', 'uploaded_by'];

    protected function casts(): array
    {
        return ['bytes' => 'integer', 'width' => 'integer', 'height' => 'integer'];
    }

    public function url(): string
    {
        return Storage::disk((string) config('cms.disk', 'public'))->url($this->path);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }
}
