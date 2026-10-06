<?php

namespace App\Services;

use Illuminate\Support\Facades\File;
use RuntimeException;
use Symfony\Component\Process\Process;

/**
 * Database backups (Doc 20: regular backups and restore tests).
 * MySQL is dumped with mysqldump/mariadb-dump and gzipped into storage/app/backups.
 */
class BackupService
{
    public function directory(): string
    {
        $dir = storage_path('app/backups');
        File::ensureDirectoryExists($dir);

        return $dir;
    }

    /** @return string full path of the new backup file */
    public function run(): string
    {
        $db = config('database.connections.'.config('database.default'));
        $file = $this->directory().'/'.$db['database'].'_'.now()->format('Y-m-d_His').'.sql.gz';

        if ($db['driver'] === 'sqlite') {
            File::put($file, gzencode(File::get($db['database'])));
        } else {
            $process = new Process([
                env('DB_DUMP_BINARY', 'mysqldump'),
                '--host='.$db['host'], '--port='.$db['port'], '--user='.$db['username'],
                '--single-transaction', '--routines', '--triggers', '--default-character-set=utf8mb4',
                $db['database'],
            ], null, ['MYSQL_PWD' => (string) $db['password']], null, 600);
            $out = gzopen($file, 'wb9');
            $process->run(function ($type, $buffer) use ($out) {
                if ($type === Process::OUT) {
                    gzwrite($out, $buffer);
                }
            });
            gzclose($out);
            if (! $process->isSuccessful()) {
                File::delete($file);
                throw new RuntimeException('Backup failed: '.trim($process->getErrorOutput()));
            }
        }

        $this->prune();
        AuditLogger::log('backup.created', null, null, ['file' => basename($file), 'size' => filesize($file)]);

        return $file;
    }

    /** Keep only the most recent backups. */
    public function prune(): void
    {
        $keep = (int) env('BACKUP_KEEP', 14);
        collect($this->list())->slice($keep)->each(fn ($b) => File::delete($b['path']));
    }

    /** @return array<int, array{name: string, path: string, size: int, time: int}> newest first */
    public function list(): array
    {
        return collect(File::glob($this->directory().'/*.sql.gz'))
            ->map(fn ($p) => ['name' => basename($p), 'path' => $p, 'size' => filesize($p), 'time' => filemtime($p)])
            ->sortByDesc('time')->values()->all();
    }
}
