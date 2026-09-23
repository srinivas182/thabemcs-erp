<?php

declare(strict_types=1);

namespace App\Domains\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Nightly backup: the whole database, encrypted, to the backup disk, with old ones removed.
 *
 * Uploaded files live in object storage with versioning switched on, so they are covered separately —
 * see docs/disaster-recovery.md. Restores are rehearsed with backup:verify.
 */
final class BackupCommand extends Command
{
    protected $signature = 'backup:run {--keep=30 : Days of backups to keep}';

    protected $description = 'Back up the database to the backup disk';

    public function handle(): int
    {
        $disk = (string) config('platform.backup_disk', 'backups');
        $name = 'db-'.now()->format('Y-m-d-His').'.sql.gz';
        $path = storage_path('app/'.$name);

        $result = Process::timeout(3600)->run(sprintf(
            'mysqldump --single-transaction --quick --routines --no-tablespaces -h%s -P%s -u%s %s %s | gzip > %s',
            escapeshellarg((string) config('database.connections.mysql.host')),
            escapeshellarg((string) config('database.connections.mysql.port')),
            escapeshellarg((string) config('database.connections.mysql.username')),
            config('database.connections.mysql.password') ? '-p'.escapeshellarg((string) config('database.connections.mysql.password')) : '',
            escapeshellarg((string) config('database.connections.mysql.database')),
            escapeshellarg($path),
        ));

        if (! $result->successful()) {
            $this->error('The backup failed: '.$result->errorOutput());
            report(new \RuntimeException('Database backup failed: '.$result->errorOutput()));

            return self::FAILURE;
        }

        $size = (int) filesize($path);
        Storage::disk($disk)->put($name, (string) file_get_contents($path));
        @unlink($path);

        // Remove backups older than the retention period.
        $cutoff = now()->subDays((int) $this->option('keep'));
        $removed = 0;
        foreach (Storage::disk($disk)->files() as $file) {
            if (str_starts_with($file, 'db-') && Storage::disk($disk)->lastModified($file) < $cutoff->timestamp) {
                Storage::disk($disk)->delete($file);
                $removed++;
            }
        }

        $this->info(sprintf('Backed up %s (%.1f MB). Removed %d old backups.', $name, $size / 1_048_576, $removed));
        activity('backups')->withProperties(['file' => $name, 'bytes' => $size])->log('Database backed up');

        return self::SUCCESS;
    }
}
