<?php

namespace App\Console\Commands;

use App\Support\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class BackupSystem extends Command
{
    protected $signature = 'hims:backup {--only-db : Back up only the database} {--filename= : Custom name for the backup zip archive}';

    protected $description = 'Create a secure enterprise backup of the database, uploaded assets, and manifest with APP_KEY verification';

    public function handle(): int
    {
        $this->info('Starting HIMS Enterprise Backup...');

        $backupDir = storage_path('app/backups');
        if (! File::isDirectory($backupDir)) {
            File::makeDirectory($backupDir, 0755, true);
        }

        $timestamp = now()->format('Y-m-d_His');
        $customName = $this->option('filename');
        $archiveName = $customName ? ($customName.(str_ends_with($customName, '.zip') ? '' : '.zip')) : "hims_backup_{$timestamp}.zip";
        $archivePath = $backupDir.DIRECTORY_SEPARATOR.$archiveName;

        $tempDir = storage_path('app/backups/tmp_'.$timestamp);
        File::makeDirectory($tempDir, 0755, true);

        try {
            $connection = config('database.default');
            $this->info("Dumping database [connection: {$connection}]...");

            $sqlDumpPath = $tempDir.DIRECTORY_SEPARATOR.'database_dump.sql';
            $tablesCount = $this->dumpDatabase($connection, $sqlDumpPath);

            $hasStorageFiles = false;
            if (! $this->option('only-db')) {
                $this->info('Copying public storage attachments...');
                $publicStorage = storage_path('app/public');
                if (File::isDirectory($publicStorage)) {
                    $targetStorage = $tempDir.DIRECTORY_SEPARATOR.'storage_public';
                    File::copyDirectory($publicStorage, $targetStorage);
                    $hasStorageFiles = true;
                }
            }

            // APP_KEY security & manifest
            $appKey = config('app.key');
            $appKeyFingerprint = hash('sha256', (string) $appKey);

            $manifest = [
                'app_name' => config('app.name', 'Hospital Information Management System'),
                'hims_version' => '2.0-Enterprise',
                'created_at' => now()->toIso8601String(),
                'db_connection' => $connection,
                'tables_backed_up' => $tablesCount,
                'has_storage_files' => $hasStorageFiles,
                'app_key_fingerprint' => $appKeyFingerprint,
                'app_key_prefix' => substr((string) $appKey, 0, 10).'...',
                'php_version' => PHP_VERSION,
                'os' => PHP_OS,
            ];

            File::put($tempDir.DIRECTORY_SEPARATOR.'manifest.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            // Create ZIP archive
            $this->info("Creating zip archive: {$archiveName}...");
            $zip = new ZipArchive;
            if ($zip->open($archivePath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
                $this->error("Failed to create ZIP file at {$archivePath}");

                return self::FAILURE;
            }

            $files = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($tempDir, \RecursiveDirectoryIterator::SKIP_DOTS),
                \RecursiveIteratorIterator::SELF_FIRST
            );

            foreach ($files as $file) {
                $relativePath = substr($file->getPathname(), strlen($tempDir) + 1);
                $normalizedPath = str_replace('\\', '/', $relativePath);
                if ($file->isDir()) {
                    $zip->addEmptyDir($normalizedPath);
                } else {
                    $zip->addFile($file->getPathname(), $normalizedPath);
                }
            }

            $zip->close();

            $fileSize = number_format(filesize($archivePath) / 1024, 2).' KB';

            AuditTrail::record('system_backup_created', 'backups', $archiveName, afterState: [
                'archive_name' => $archiveName,
                'size' => $fileSize,
                'tables' => $tablesCount,
                'has_storage' => $hasStorageFiles,
            ]);

            $this->newLine();
            $this->info('====================================================');
            $this->info(' HIMS BACKUP COMPLETED SUCCESSFULLY');
            $this->info('====================================================');
            $this->table(
                ['Property', 'Details'],
                [
                    ['Archive File', $archivePath],
                    ['Archive Size', $fileSize],
                    ['Database Tables', (string) $tablesCount],
                    ['Storage Included', $hasStorageFiles ? 'Yes (storage/app/public)' : 'No (Database Only)'],
                    ['APP_KEY Fingerprint', substr($appKeyFingerprint, 0, 16).'... (SHA256)'],
                ]
            );

            $this->warn('CRITICAL SECURITY NOTE:');
            $this->line('Encrypted database fields (such as employee phone numbers) require the same APP_KEY to decrypt.');
            $this->line('Ensure you maintain a secure offline copy of your APP_KEY from .env.');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Backup failed: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }

    private function dumpDatabase(string $connection, string $outputPath): int
    {
        $handle = fopen($outputPath, 'w');
        if (! $handle) {
            throw new \RuntimeException("Cannot open file for writing: {$outputPath}");
        }

        fwrite($handle, "-- HIMS Enterprise Database Dump\n");
        fwrite($handle, '-- Created: '.now()->toIso8601String()."\n\n");
        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 0;\n\n");

        $pdo = DB::connection($connection)->getPdo();
        $driver = DB::connection($connection)->getDriverName();

        $tableNames = [];

        if ($driver === 'sqlite') {
            $stmt = $pdo->query("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'");
            $tableNames = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        } else {
            $stmt = $pdo->query('SHOW TABLES');
            $tableNames = $stmt->fetchAll(\PDO::FETCH_COLUMN);
        }

        foreach ($tableNames as $table) {
            $this->line(" - Backing up table: {$table}");
            fwrite($handle, "-- Table structure for `{$table}`\n");
            fwrite($handle, "DROP TABLE IF EXISTS `{$table}`;\n");

            if ($driver === 'sqlite') {
                $createStmt = $pdo->query("SELECT sql FROM sqlite_master WHERE type='table' AND name='{$table}'");
                $createSql = $createStmt->fetchColumn();
                fwrite($handle, "{$createSql};\n\n");
            } else {
                $createStmt = $pdo->query("SHOW CREATE TABLE `{$table}`");
                $createSql = $createStmt->fetch(\PDO::FETCH_ASSOC)['Create Table'] ?? '';
                fwrite($handle, "{$createSql};\n\n");
            }

            // Dump rows
            $dataStmt = $pdo->query("SELECT * FROM `{$table}`");
            $rows = $dataStmt->fetchAll(\PDO::FETCH_ASSOC);

            if (! empty($rows)) {
                fwrite($handle, "-- Dumping data for `{$table}`\n");
                foreach ($rows as $row) {
                    $keys = array_map(fn ($k) => "`{$k}`", array_keys($row));
                    $values = array_map(function ($val) use ($pdo) {
                        if ($val === null) {
                            return 'NULL';
                        }

                        return $pdo->quote((string) $val);
                    }, array_values($row));

                    $insertSql = "INSERT INTO `{$table}` (".implode(', ', $keys).') VALUES ('.implode(', ', $values).");\n";
                    fwrite($handle, $insertSql);
                }
                fwrite($handle, "\n");
            }
        }

        fwrite($handle, "SET FOREIGN_KEY_CHECKS = 1;\n");
        fclose($handle);

        return count($tableNames);
    }
}
