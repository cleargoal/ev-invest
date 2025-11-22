<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class DbBackup extends Command
{
    protected $signature = 'db:backup {--keep=10 : Number of recent backups to keep}';
    protected $description = 'Dump MySQL database to storage and rotate old backups';

    public function handle(): int
    {
        $db = config('database.connections.mysql');

        $backupDir = storage_path('app/backups');

        // Create backups directory if it doesn't exist
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }

        $filename = 'db_backup-' . date('Y-m-d_H-i-s') . '.sql';
        $path = $backupDir . '/' . $filename;

        $cmd = sprintf(
            'mysqldump -u%s -p%s -h%s --no-tablespaces %s > %s',
            $db['username'],
            $db['password'],
            $db['host'],
            $db['database'],
            $path
        );

        exec($cmd, $output, $returnCode);

        if ($returnCode !== 0) {
            $this->error('Backup failed!');
            return self::FAILURE;
        }

        $this->info("Backup saved to $path");

        // Rotate old backups
        $this->rotateBackups((int) $this->option('keep'));

        return self::SUCCESS;
    }

    /**
     * Remove old backups, keeping only the specified number of recent ones
     */
    protected function rotateBackups(int $keepCount): void
    {
        $backupDir = storage_path('app/backups');

        if (!is_dir($backupDir)) {
            return;
        }

        $backups = glob($backupDir . '/backup-*.sql');

        if (count($backups) <= $keepCount) {
            return;
        }

        // Sort by modification time (oldest first)
        usort($backups, fn($a, $b) => filemtime($a) <=> filemtime($b));

        // Remove oldest backups
        $toDelete = array_slice($backups, 0, count($backups) - $keepCount);

        foreach ($toDelete as $file) {
            if (unlink($file)) {
                $this->info("Deleted old backup: " . basename($file));
            }
        }
    }
}
