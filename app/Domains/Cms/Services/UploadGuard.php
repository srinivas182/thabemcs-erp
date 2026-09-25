<?php

declare(strict_types=1);

namespace App\Domains\Cms\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Process;

/**
 * Checks a file before it is stored anywhere people can reach it.
 *
 * Two things happen here. Images are re-encoded, which strips anything hidden in the file - a payload
 * appended after the image data, or location information in the camera metadata - and leaves a picture
 * that is exactly a picture. Then, where a scanner is available, the file is scanned for malware.
 *
 * SVG is refused by default: it is XML that browsers execute, so an SVG upload is a script upload.
 */
final class UploadGuard
{
    /**
     * @return string|null the path to a cleaned temporary file, or null if the original may be used
     *
     * @throws CmsException when the file must not be stored
     */
    public function check(UploadedFile $file): ?string
    {
        $mime = (string) $file->getMimeType();

        if ($mime === 'image/svg+xml' && ! (bool) config('cms.uploads.allow_svg', false)) {
            throw new CmsException('SVG files are not accepted, because browsers run them as code. Please upload a PNG or JPG.');
        }

        $this->scan($file);

        return in_array($mime, ['image/jpeg', 'image/png', 'image/webp'], true) ? $this->reencode($file, $mime) : null;
    }

    /**
     * Draws the image onto a clean canvas and writes it out again. Anything that was not part of the
     * picture does not survive.
     */
    private function reencode(UploadedFile $file, string $mime): ?string
    {
        if (! function_exists('imagecreatefromstring')) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($file->getRealPath()));
        if ($image === false) {
            throw new CmsException('That file is not a picture we can read. Please try another.');
        }

        imagepalettetotruecolor($image);
        imagealphablending($image, true);
        imagesavealpha($image, true);

        $path = tempnam(sys_get_temp_dir(), 'upload').'.'.match ($mime) {
            'image/png' => 'png', 'image/webp' => 'webp', default => 'jpg',
        };

        $written = match ($mime) {
            'image/png' => imagepng($image, $path, 6),
            'image/webp' => imagewebp($image, $path, 82),
            default => imagejpeg($image, $path, 82),
        };
        imagedestroy($image);

        if ($written === false) {
            throw new CmsException('That image could not be processed. Please try another.');
        }

        return $path;
    }

    /**
     * Scans with ClamAV where it is installed. Where it is not, the upload is allowed and the fact is
     * logged, so an environment without scanning is visible rather than silent.
     */
    private function scan(UploadedFile $file): void
    {
        $scanner = (string) config('cms.uploads.scanner', '');
        if ($scanner === '') {
            return;
        }

        $result = Process::timeout(60)->run($scanner.' --no-summary '.escapeshellarg($file->getRealPath()));

        // ClamAV: 0 means clean, 1 means something was found, anything else is a problem with the scanner.
        if ($result->exitCode() === 1) {
            activity('security')->withProperties(['file' => $file->getClientOriginalName()])->log('Upload refused: malware detected');
            throw new CmsException('That file did not pass our virus scan and was not uploaded.');
        }

        if ($result->exitCode() !== 0) {
            report(new \RuntimeException('Virus scanner failed: '.$result->errorOutput()));
        }
    }
}
