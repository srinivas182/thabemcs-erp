<?php

declare(strict_types=1);

namespace App\Domains\Platform\Console;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Facades\Storage;

/**
 * Rehearses a restore: takes the latest backup, loads it into a scratch database, counts a few tables
 * and times the whole thing. A backup nobody has restored is not a backup.
 *
 * Run monthly (and before go-live) on a machine that can reach MySQL.
 */
final class VerifyBackupCommand extends Command
{
    protected $signature = 'backup:verify {--database=thabekhulu_restore_check : Scratch database to restore into}';

    protected $description = 'Restore the latest backup into a scratch database and check it';

    public function handle(): int
    {
        $disk = (string) config('platform.backup_disk', 'backups');
        $files = collect(Storage::disk($disk)->files())->filter(fn (string $f): bool => str_starts_with($f, 'db-'))->sortDesc();
        $latest = $files->first();

        if ($latest === null) {
            $this->error('There are no backups to check.');

            return self::FAILURE;
        }

        $scratch = (string) $this->option('database');
        $local = storage_path('app/'.basename((string) $latest));
        file_put_contents($local, Storage::disk($disk)->get((string) $latest));

        $started = microtime(true);
        $credentials = sprintf('-h%s -P%s -u%s %s',
            escapeshellarg((string) config('database.connections.mysql.host')),
            escapeshellarg((string) config('database.connections.mysql.port')),
            escapeshellarg((string) config('database.connections.mysql.username')),
            config('database.connections.mysql.password') ? '-p'.escapeshellarg((string) config('database.connections.mysql.password')) : '',
        );

        Process::timeout(7200)->run("mysql {$credentials} -e ".escapeshellarg("drop database if exists `{$scratch}`; create database `{$scratch}`;"));
        $restore = Process::timeout(7200)->run('gunzip -c '.escapeshellarg($local)." | mysql {$credentials} ".escapeshellarg($scratch));
        @unlink($local);

        if (! $restore->successful()) {
            $this->error('The restore failed: '.$restore->errorOutput());

            return self::FAILURE;
        }

        $seconds = round(microtime(true) - $started, 1);
        $counts = [];
        foreach (['companies', 'projects', 'supplier_invoices', 'users'] as $table) {
            $counts[$table] = (int) DB::connection('mysql')->select("select count(*) as n from `{$scratch}`.`{$table}`")[0]->n;
        }

        $this->info("Restored {$latest} into {$scratch} in {$seconds} seconds.");
        $this->table(['Table', 'Rows'], collect($counts)->map(static fn (int $n, string $t): array => [$t, $n])->values()->all());

        if ($counts['companies'] === 0 || $counts['users'] === 0) {
            $this->error('The restored database looks empty. Investigate before relying on these backups.');

            return self::FAILURE;
        }

        activity('backups')->withProperties(['file' => $latest, 'seconds' => $seconds, ...$counts])->log('Restore rehearsed');

        return self::SUCCESS;
    }
}
