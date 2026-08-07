<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class AutoExecuteSaasBackup extends Command
{
    protected $signature = 'saas:backup 
                            {--encrypt : Encrypt the backup with APP_KEY} 
                            {--destination=storage/backup : Backup destination path}
                            {--verify : Verify backup integrity after creation}';

    protected $description = 'Create an encrypted database backup of the SaaS application';

    public function handle(): int
    {
        $this->info('Starting SaaS backup...');

        $encrypt = $this->option('encrypt');
        $destination = $this->option('destination');
        $verify = $this->option('verify');

        if (! is_dir($destination)) {
            mkdir($destination, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $backupFilename = 'backup_' . $timestamp . '.sql';
        $fullPath = $destination . '/' . $backupFilename;

        $this->info("Creating database dump...");

        $connection = DB::connection();
        $database = $connection->getDatabaseName();
        $driver = $connection->getDriverName();

        if ($driver === 'mysql') {
            $mysqldumpPath = (string) \Process::which('mysqldump');
            if (empty($mysqldumpPath)) {
                $this->error('mysqldump not found in PATH. Cannot create MySQL backup.');
                return Command::FAILURE;
            }

            $envFile = base_path('.env');
            $command = sprintf(
                '%s --user=%s --password=%s --host=%s --port=%s --single-transaction --routines --triggers --events --add-drop-table --complete-insert %s > %s 2>&1',
                $mysqldumpPath,
                escapeshellarg(env('DB_USERNAME', '')),
                escapeshellarg(env('DB_PASSWORD', '')),
                escapeshellarg(env('DB_HOST', '127.0.0.1')),
                escapeshellarg(env('DB_PORT', '3306')),
                escapeshellarg($database),
                escapeshellarg($fullPath)
            );

            $output = [];
            $exitCode = 0;
            exec($command, $output, $exitCode);

            if ($exitCode !== 0 || ! file_exists($fullPath)) {
                $this->error('Database dump failed.');
                $this->error(implode("\n", $output));
                return Command::FAILURE;
            }
        } elseif ($driver === 'sqlite') {
            $sqlitePath = config('database.connections.sqlite.database');
            if (! file_exists($sqlitePath)) {
                $this->error('SQLite database file not found.');
                return Command::FAILURE;
            }
            copy($sqlitePath, $fullPath);
        } else {
            $this->error('Unsupported database driver.');
            return Command::FAILURE;
        }

        $backupSize = round(filesize($fullPath) / 1024 / 1024, 2);
        $this->info("Backup created: {$fullPath} ({$backupSize} MB)");

        if ($verify) {
            $this->info('Verifying backup integrity...');
            $lineCount = count(file($fullPath));
            $output = [];
            exec("grep -c 'CREATE TABLE' " . escapeshellarg($fullPath), $output, $returnCode);
            $hasCreateTable = (int) ($output[0] ?? 0);
            $this->info("Backup contains {$lineCount} lines");
            if ($hasCreateTable > 0) {
                $this->info('Backup verification: PASSED');
            } else {
                $this->warn('Backup verification: No CREATE TABLE statements found');
            }
        }

        $encryptedPath = null;

        if ($encrypt) {
            $this->info('Encrypting backup...');
            $appKey = config('app.key');
            if ($appKey === null || $appKey === '') {
                $this->error('APP_KEY is not set. Cannot encrypt backup.');
                return Command::FAILURE;
            }

            $content = file_get_contents($fullPath);
            $encrypted = openssl_encrypt(
                $content,
                'AES-256-CBC',
                base64_decode(substr($appKey, 7)),
                0,
                substr($appKey, 0, 16)
            );

            if ($encrypted === false) {
                $this->error('Encryption failed.');
                return Command::FAILURE;
            }

            $encryptedPath = $destination . '/backup_' . $timestamp . '.sql.enc';
            file_put_contents($encryptedPath, $encrypted);
            $encryptedSize = round(filesize($encryptedPath) / 1024 / 1024, 2);
            $this->info("Encrypted backup created: {$encryptedPath} ({$encryptedSize} MB)");
        }

        $this->logBackupToAudit($backupFilename, $fullPath, $backupSize, $encrypt, $encryptedPath);

        $this->info('Backup completed successfully.');
        return Command::SUCCESS;
    }

    protected function logBackupToAudit(string $filename, string $path, float $size, bool $encrypt, ?string $encryptedPath): void
    {
        try {
            \DB::table('audit_logs')->insert([
                'restaurant_id' => null,
                'user_id' => null,
                'action' => 'saas_backup_created',
                'subject_type' => 'backup',
                'subject_id' => null,
                'old_values' => null,
                'new_values' => json_encode([
                    'filename' => $filename,
                    'path' => $path,
                    'size_mb' => $size,
                    'encrypted' => $encrypt,
                    'encrypted_path' => $encryptedPath,
                ]),
                'ip_address' => null,
                'user_agent' => 'saas:backup command',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        } catch (\Throwable $e) {
            $this->warn('Failed to log backup to audit trail: ' . $e->getMessage());
        }
    }
}
