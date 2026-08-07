<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

class RestoreSaasBackup extends Command
{
    protected $signature = 'saas:restore 
                            {--force : Skip confirmation prompt}
                            {--path= : Path to backup file}';

    protected $description = 'Restore database from a backup file';

    public function handle(): int
    {
        $backupPath = $this->option('path');
        $force = $this->option('force');

        if (empty($backupPath)) {
            $this->error('Please provide a backup file path with --path option.');
            return Command::FAILURE;
        }

        if (! file_exists($backupPath)) {
            $this->error("Backup file not found: {$backupPath}");
            return Command::FAILURE;
        }

        if (! $force) {
            $this->warn('WARNING: This will replace your current database!');
            $confirmed = $this->confirm('Are you sure you want to continue?', false);
            if (! $confirmed) {
                $this->info('Restore cancelled.');
                return Command::CANCELLED;
            }
        }

        $this->info('Starting database restore...');

        $driver = DB::getDriverName();

        if ($driver === 'mysql') {
            $database = DB::getDatabaseName();
            $command = sprintf(
                'mysql --user=%s --password=%s --host=%s --port=%s %s < %s 2>&1',
                escapeshellarg(env('DB_USERNAME', '')),
                escapeshellarg(env('DB_PASSWORD', '')),
                escapeshellarg(env('DB_HOST', '127.0.0.1')),
                escapeshellarg(env('DB_PORT', '3306')),
                escapeshellarg($database),
                escapeshellarg($backupPath)
            );

            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            if ($exitCode !== 0) {
                $this->error('Restore failed.');
                $this->error(implode("\n", $output));
                return Command::FAILURE;
            }
        } elseif ($driver === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if (! file_exists(dirname($sqlitePath))) {
                mkdir(dirname($sqlitePath), 0755, true);
            }
            copy($backupPath, $sqlitePath);
        } else {
            $this->error('Unsupported database driver for restore.');
            return Command::FAILURE;
        }

        $this->info('Database restore completed successfully.');
        return Command::SUCCESS;
    }
}
