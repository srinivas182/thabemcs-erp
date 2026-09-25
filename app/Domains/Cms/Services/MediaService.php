<?php

declare(strict_types=1);

namespace App\Domains\Cms\Services;

use App\Domains\Cms\Models\CmsMedia;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * The media library behind the website. These files are public, unlike everything in Documents, so what
 * may be uploaded is deliberately narrow: images and PDFs only, checked by what the file actually is
 * rather than by its name.
 */
final class MediaService
{
    public function __construct(private readonly UploadGuard $guard) {}

    public function upload(UploadedFile $file, ?string $alt, User $by): CmsMedia
    {
        /** @var list<string> $accepted */
        $accepted = (array) config('cms.uploads.accepted');
        $mime = (string) $file->getMimeType();

        if (! in_array($mime, $accepted, true)) {
            throw new CmsException('Only images and PDFs can go on the website.');
        }
        if ($file->getSize() > (int) config('cms.uploads.max_bytes')) {
            throw new CmsException('That file is too large. Please keep uploads under 10 MB.');
        }

        // Refuse anything dangerous, and re-encode images so only the picture survives.
        $cleaned = $this->guard->check($file);
        if ($cleaned !== null) {
            $file = new UploadedFile($cleaned, $file->getClientOriginalName(), $mime, null, true);
        }

        $name = Str::slug(pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME)).'-'.Str::lower(Str::random(6));
        $extension = $file->extension() ?: 'bin';
        $path = $file->storeAs('website/'.now()->format('Y/m'), "{$name}.{$extension}", (string) config('cms.disk', 'public'));

        $size = @getimagesize($file->getRealPath());

        return CmsMedia::query()->create([
            'path' => (string) $path,
            'file_name' => $file->getClientOriginalName(),
            'mime_type' => $mime,
            'bytes' => (int) $file->getSize(),
            'width' => $size === false ? null : $size[0],
            'height' => $size === false ? null : $size[1],
            'alt' => $alt,
            'uploaded_by' => $by->id,
        ]);
    }

    public function delete(CmsMedia $media): void
    {
        Storage::disk((string) config('cms.disk', 'public'))->delete($media->path);
        $media->delete();
    }
}
