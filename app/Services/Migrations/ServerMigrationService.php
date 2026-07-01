<?php

namespace App\Services\Migrations;

use App\Models\Backup;
use App\Models\MigrationRemoteCredential;
use App\Models\ServerMigration;
use App\Models\User;
use App\Services\Backups\BackupService;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ServerMigrationService
{
    public function generatePackage(User $user): ServerMigration
    {
        $migration = ServerMigration::query()->create([
            'created_by' => $user->id,
            'status' => ServerMigration::STATUS_PENDING,
            'version' => $this->version(),
            'disk' => 'backups',
            'metadata' => [
                'remote_migration' => 'placeholder',
                'maintenance_mode' => 'placeholder',
            ],
        ]);

        try {
            $backup = app(BackupService::class)->createPlatformBackup(Backup::TYPE_FULL_PLATFORM, $user);

            if ($backup->status !== Backup::STATUS_COMPLETED) {
                throw new \RuntimeException('Required backup failed before migration package creation.');
            }

            $backupContent = Storage::disk($backup->disk)->get($backup->path);
            $backupPackage = json_decode($backupContent, true) ?: [];
            $package = $this->package($migration, $backup, $backupPackage);
            $contents = json_encode($package, JSON_PRETTY_PRINT);
            $filename = 'server-migration-'.$migration->id.'-'.now()->format('YmdHis').'-'.Str::random(8).'.json';
            $path = 'migrations/'.$filename;

            Storage::disk('backups')->put($path, $contents);
            $raw = Storage::disk('backups')->get($path);

            $migration->forceFill([
                'backup_id' => $backup->id,
                'status' => ServerMigration::STATUS_COMPLETED,
                'path' => $path,
                'filename' => $filename,
                'checksum' => hash('sha256', $raw),
                'size_bytes' => strlen($raw),
                'completed_at' => now(),
                'metadata' => array_merge($migration->metadata ?? [], [
                    'backup_checksum' => $backup->checksum,
                    'contains_env_example_checklist' => true,
                    'contains_restore_guide' => true,
                    'contains_secrets' => false,
                ]),
            ])->save();
        } catch (\Throwable $exception) {
            $migration->forceFill([
                'status' => ServerMigration::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        return $migration->fresh(['backup']);
    }

    public function saveRemoteCredentials(User $user, array $payload): MigrationRemoteCredential
    {
        return MigrationRemoteCredential::query()->create([
            'created_by' => $user->id,
            'name' => $payload['name'],
            'host' => $payload['host'] ?? null,
            'port' => $payload['port'] ?? null,
            'username' => $payload['username'] ?? null,
            'encrypted_credentials' => [
                'auth_type' => $payload['auth_type'] ?? 'password',
                'password' => $payload['password'] ?? null,
                'private_key' => $payload['private_key'] ?? null,
                'notes' => $payload['notes'] ?? null,
            ],
        ]);
    }

    private function package(ServerMigration $migration, Backup $backup, array $backupPackage): array
    {
        return [
            'manifest' => [
                'package_id' => $migration->id,
                'version' => $migration->version,
                'generated_at' => now()->toIso8601String(),
                'source_backup_id' => $backup->id,
                'source_backup_checksum' => $backup->checksum,
                'checksum_algorithm' => 'sha256',
                'remote_migration_placeholder' => 'Future SSH/SFTP transfer will consume encrypted credentials without exposing secrets.',
                'maintenance_mode_placeholder' => 'Use the platform maintenance placeholder before final cutover.',
            ],
            'database_dump' => $backupPackage['database'] ?? ['tables' => []],
            'uploaded_files' => $this->filesForPrefix($backupPackage, 'public'),
            'invoice_files' => $this->filesForPrefix($backupPackage, 'invoices'),
            'env_example_checklist' => $this->envChecklist(),
            'restore_guide_markdown' => $this->restoreGuide($backup),
        ];
    }

    private function filesForPrefix(array $backupPackage, string $prefix): array
    {
        return collect($backupPackage['files'] ?? [])
            ->filter(fn ($file) => str_starts_with($file['path'] ?? '', $prefix))
            ->values()
            ->all();
    }

    private function envChecklist(): array
    {
        $path = base_path('.env.example');

        if (! file_exists($path)) {
            return [];
        }

        return collect(file($path, FILE_IGNORE_NEW_LINES))
            ->filter(fn ($line) => trim($line) !== '' && ! str_starts_with(trim($line), '#'))
            ->map(function ($line) {
                [$key] = array_pad(explode('=', $line, 2), 2, null);

                return [
                    'key' => $key,
                    'required_on_target' => true,
                    'copy_secret_from_source' => false,
                    'note' => 'Set explicitly on the target server. Do not package production secret values.',
                ];
            })
            ->values()
            ->all();
    }

    private function restoreGuide(Backup $backup): string
    {
        return "# PosLAB Server Migration Restore Guide\n\n"
            ."## Package\n\n"
            ."- Version: ".$this->version()."\n"
            ."- Source backup ID: ".$backup->id."\n"
            ."- Source backup checksum: ".$backup->checksum."\n\n"
            ."## Steps\n\n"
            ."1. Put the current server into maintenance mode.\n"
            ."2. Provision the target server with PHP, database, Redis, queue, storage, and web server settings.\n"
            ."3. Create a fresh `.env` from `.env.example`; do not copy secrets from this package.\n"
            ."4. Restore database data from `database_dump`.\n"
            ."5. Restore `uploaded_files` and `invoice_files` to private storage paths.\n"
            ."6. Run Laravel migrations and cache commands on the target.\n"
            ."7. Verify health, login, tenant isolation, payments, printers, and backups.\n"
            ."8. Disable maintenance mode after DNS or proxy cutover.\n\n"
            ."Automated restore is intentionally not enabled in this phase and must require explicit confirmation.\n";
    }

    private function version(): string
    {
        return env('APP_VERSION', '0.1.0');
    }
}
