<?php

namespace App\Console\Commands;

use App\Support\AuditTrail;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use ZipArchive;

class RestoreSystem extends Command
{
    protected $signature = 'hims:restore {backup_file : Path or filename in storage/app/backups to restore} {--force : Force restore without interactive confirmation}';

    protected $description = 'Safely restore the HIMS database and public assets from a backup archive with APP_KEY verification';

    public function handle(): int
    {
        $backupArg = $this->argument('backup_file');
        $archivePath = $backupArg;

        if (! File::exists($archivePath)) {
            $candidatePath = storage_path('app/backups/'.$backupArg);
            if (File::exists($candidatePath)) {
                $archivePath = $candidatePath;
            } elseif (File::exists($candidatePath.'.zip')) {
                $archivePath = $candidatePath.'.zip';
            } else {
                $this->error("Backup file not found at: {$backupArg} or in storage/app/backups/");

                return self::FAILURE;
            }
        }

        $this->info("Opening backup archive: {$archivePath}...");
        $zip = new ZipArchive;
        if ($zip->open($archivePath) !== true) {
            $this->error('Failed to open ZIP archive.');

            return self::FAILURE;
        }

        $tempDir = storage_path('app/backups/restore_'.now()->format('Ymd_His'));
        File::makeDirectory($tempDir, 0755, true);

        try {
            $zip->extractTo($tempDir);
            $zip->close();

            // Verify manifest
            $manifestPath = $tempDir.DIRECTORY_SEPARATOR.'manifest.json';
            if (! File::exists($manifestPath)) {
                $this->error('Archive does not contain a valid manifest.json file.');

                return self::FAILURE;
            }

            $manifest = json_decode(File::get($manifestPath), true);
            $this->info('--- Backup Manifest ---');
            $this->line("Application: {$manifest['app_name']} ({$manifest['hims_version']})");
            $this->line("Created at:  {$manifest['created_at']}");
            $this->line("Tables:      {$manifest['tables_backed_up']}");

            // Verify APP_KEY compatibility
            $currentAppKey = config('app.key');
            $currentFingerprint = hash('sha256', (string) $currentAppKey);
            $backupFingerprint = $manifest['app_key_fingerprint'] ?? null;

            if ($backupFingerprint && $backupFingerprint !== $currentFingerprint) {
                $this->newLine();
                $this->warn('*******************************************************************');
                $this->warn(' CRITICAL WARNING: APP_KEY MISMATCH DETECTED                       ');
                $this->warn('*******************************************************************');
                $this->line('The current APP_KEY in your .env does NOT match the APP_KEY used');
                $this->line('when this backup was created.');
                $this->line('Impact: Encrypted database fields (such as employee phone numbers)');
                $this->line('CANNOT be decrypted and will cause DecryptException errors unless');
                $this->line('the original APP_KEY is restored.');
                $this->newLine();

                if (! $this->option('force') && ! $this->confirm('Do you still wish to proceed with database restore?')) {
                    $this->info('Restore aborted by user.');

                    return self::SUCCESS;
                }
            }

            if (! $this->option('force') && ! $this->confirm('WARNING: Restoring will overwrite existing database tables. Continue?')) {
                $this->info('Restore aborted by user.');

                return self::SUCCESS;
            }

            // Restore SQL dump
            $dumpFile = $tempDir.DIRECTORY_SEPARATOR.'database_dump.sql';
            if (File::exists($dumpFile)) {
                $this->info('Restoring database tables...');
                $sql = File::get($dumpFile);

                $pdo = DB::connection()->getPdo();
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0;');
                $pdo->exec($sql);
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1;');

                $this->info('Database tables restored successfully.');
            }

            // Restore storage files
            $storageExtract = $tempDir.DIRECTORY_SEPARATOR.'storage_public';
            if (File::isDirectory($storageExtract)) {
                $this->info('Restoring public storage attachments...');
                $targetPublic = storage_path('app/public');
                File::copyDirectory($storageExtract, $targetPublic);
                $this->info('Storage attachments restored.');
            }

            // Clear cache
            Artisan::call('optimize:clear');
            $this->info('Framework cache cleared.');

            AuditTrail::record('system_backup_restored', 'backups', basename($archivePath), afterState: [
                'archive' => basename($archivePath),
                'manifest' => $manifest,
            ]);

            $this->newLine();
            $this->info('====================================================');
            $this->info(' HIMS RESTORE COMPLETED SUCCESSFULLY');
            $this->info('====================================================');

            return self::SUCCESS;
        } catch (\Throwable $e) {
            $this->error('Restore error: '.$e->getMessage());

            return self::FAILURE;
        } finally {
            if (File::isDirectory($tempDir)) {
                File::deleteDirectory($tempDir);
            }
        }
    }
}
