<?php

declare(strict_types=1);

namespace App\Domains\Documents\Services;

use App\Domains\Documents\Models\Document;
use App\Domains\Documents\Models\DocumentVersion;
use App\Domains\Platform\Exceptions\QuotaExceededException;
use App\Domains\Platform\Services\QuotaService;
use App\Models\User;
use App\Support\Tenancy\CurrentCompany;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Stores documents privately per company, keeps every version, and enforces the storage quota.
 */
final class DocumentService
{
    public function __construct(private readonly QuotaService $quotas, private readonly CurrentCompany $context) {}

    /**
     * @param  array<string, mixed>  $attributes
     *
     * @throws QuotaExceededException
     */
    public function upload(UploadedFile $file, array $attributes, User $by, ?string $revision = null): Document
    {
        $this->quotas->ensureStorageFor($this->context->require(), (int) $file->getSize());

        return DB::transaction(function () use ($file, $attributes, $by, $revision): Document {
            $document = Document::query()->create([...$attributes, 'created_by' => $by->id]);
            $this->storeVersion($document, $file, $by, $revision, null, 1);

            return $document;
        });
    }

    /**
     * @throws QuotaExceededException
     */
    public function addVersion(Document $document, UploadedFile $file, User $by, ?string $revision, ?string $notes): DocumentVersion
    {
        $this->quotas->ensureStorageFor($this->context->require(), (int) $file->getSize());

        return DB::transaction(function () use ($document, $file, $by, $revision, $notes): DocumentVersion {
            $next = (int) DocumentVersion::query()->where('document_id', $document->id)->lockForUpdate()->max('version') + 1;

            return $this->storeVersion($document, $file, $by, $revision, $notes, $next);
        });
    }

    private function storeVersion(Document $document, UploadedFile $file, User $by, ?string $revision, ?string $notes, int $version): DocumentVersion
    {
        /** @var string $disk */
        $disk = config('platform.documents_disk');
        $company = $this->context->require();

        // Files are stored under an unguessable name; the original name is kept only in the database.
        $path = $file->storeAs(
            "{$company->ulid}/{$document->ulid}",
            'v'.$version.'-'.Str::random(24).'.'.strtolower($file->getClientOriginalExtension() ?: 'bin'),
            $disk,
        );

        return DocumentVersion::query()->create([
            'document_id' => $document->id,
            'version' => $version,
            'revision' => $revision,
            'disk' => $disk,
            'path' => (string) $path,
            'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => (int) $file->getSize(),
            'sha256' => (string) hash_file('sha256', $file->getRealPath()),
            'notes' => $notes,
            'uploaded_by' => $by->id,
        ]);
    }
}
