<?php

namespace App\Services\Backups;

use App\Models\Backup;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class BackupService
{
    private const FEATURE_KEY = 'backups';

    public function createTenantBackup(Tenant $tenant, string $type, ?User $user = null, bool $encrypted = false): Backup
    {
        return $this->create(Backup::SCOPE_TENANT, $type, $tenant, $user, $encrypted);
    }

    public function createPlatformBackup(string $type = Backup::TYPE_FULL_PLATFORM, ?User $user = null, bool $encrypted = false): Backup
    {
        return $this->create(Backup::SCOPE_PLATFORM, $type, null, $user, $encrypted);
    }

    public function remoteStorageConfigured(): bool
    {
        return filter_var(env('BACKUP_REMOTE_ENABLED', false), FILTER_VALIDATE_BOOLEAN);
    }

    private function create(string $scope, string $type, ?Tenant $tenant, ?User $user, bool $encrypted): Backup
    {
        $backup = Backup::query()->create([
            'tenant_id' => $tenant?->id,
            'created_by' => $user?->id,
            'scope' => $scope,
            'type' => $type,
            'status' => Backup::STATUS_PENDING,
            'disk' => 'backups',
            'encrypted' => $encrypted,
            'metadata' => [
                'remote_storage_configured' => $this->remoteStorageConfigured(),
            ],
        ]);

        try {
            $package = $this->package($scope, $type, $tenant);
            $contents = json_encode($package, JSON_PRETTY_PRINT);

            if ($encrypted) {
                $contents = $this->encrypt($contents);
            }

            $filename = $this->filename($scope, $type, $tenant, $encrypted);
            $path = trim($scope.'/'.($tenant?->id ?? 'platform').'/'.$filename, '/');
            Storage::disk('backups')->put($path, $contents);
            $raw = Storage::disk('backups')->get($path);

            $backup->forceFill([
                'status' => Backup::STATUS_COMPLETED,
                'path' => $path,
                'filename' => $filename,
                'checksum' => hash('sha256', $raw),
                'size_bytes' => strlen($raw),
                'completed_at' => now(),
                'metadata' => array_merge($backup->metadata ?? [], [
                    'database_tables' => count($package['database']['tables'] ?? []),
                    'files_count' => count($package['files'] ?? []),
                    'remote_storage_placeholder' => 'Configure BACKUP_REMOTE_* to copy completed packages to remote storage later.',
                ]),
            ])->save();
        } catch (\Throwable $exception) {
            $backup->forceFill([
                'status' => Backup::STATUS_FAILED,
                'last_error' => $exception->getMessage(),
            ])->save();
        }

        return $backup->fresh();
    }

    private function package(string $scope, string $type, ?Tenant $tenant): array
    {
        return [
            'generated_at' => now()->toIso8601String(),
            'scope' => $scope,
            'type' => $type,
            'tenant' => $tenant ? ['id' => $tenant->id, 'name' => $tenant->name, 'slug' => $tenant->slug] : null,
            'database' => in_array($type, [Backup::TYPE_DATABASE, Backup::TYPE_FULL_TENANT, Backup::TYPE_FULL_PLATFORM], true)
                ? $this->databaseSnapshot($scope, $tenant)
                : ['tables' => []],
            'files' => $this->fileSnapshot($scope, $type, $tenant),
        ];
    }

    private function databaseSnapshot(string $scope, ?Tenant $tenant): array
    {
        $tables = $this->tableNames();

        return [
            'tables' => $tables->mapWithKeys(function (string $table) use ($scope, $tenant) {
                $query = DB::table($table);

                if ($scope === Backup::SCOPE_TENANT && $tenant && Schema::hasColumn($table, 'tenant_id')) {
                    $query->where('tenant_id', $tenant->id);
                }

                return [$table => $query->get()->map(fn ($row) => (array) $row)->all()];
            })->all(),
        ];
    }

    private function tableNames()
    {
        $connection = Schema::getConnection();
        $driver = $connection->getDriverName();

        $tables = match ($driver) {
            'sqlite' => collect(DB::select("SELECT name FROM sqlite_master WHERE type='table' AND name NOT LIKE 'sqlite_%'"))->pluck('name'),
            'pgsql' => collect(DB::select("SELECT table_name AS name FROM information_schema.tables WHERE table_schema = 'public' AND table_type = 'BASE TABLE'"))->pluck('name'),
            'mysql', 'mariadb' => collect(DB::select('SHOW TABLES'))->map(fn ($row) => array_values((array) $row)[0]),
            default => collect(),
        };

        return $tables
            ->filter(fn ($table) => $table !== 'migrations')
            ->values();
    }

    private function fileSnapshot(string $scope, string $type, ?Tenant $tenant): array
    {
        if ($type === Backup::TYPE_DATABASE) {
            return [];
        }

        $prefixes = match ($type) {
            Backup::TYPE_UPLOADS => ['public'],
            Backup::TYPE_INVOICES => ['invoices'],
            default => ['public', 'invoices'],
        };

        return collect($prefixes)
            ->flatMap(fn ($prefix) => Storage::disk('local')->allFiles($prefix))
            ->filter(function (string $path) use ($scope, $tenant) {
                if ($scope !== Backup::SCOPE_TENANT || ! $tenant) {
                    return true;
                }

                return str_contains($path, '/'.$tenant->id.'/') || str_contains($path, $tenant->slug);
            })
            ->map(fn ($path) => [
                'path' => $path,
                'size' => Storage::disk('local')->size($path),
                'checksum' => hash('sha256', Storage::disk('local')->get($path)),
                'content_base64' => base64_encode(Storage::disk('local')->get($path)),
            ])
            ->values()
            ->all();
    }

    private function filename(string $scope, string $type, ?Tenant $tenant, bool $encrypted): string
    {
        $name = implode('-', array_filter([
            $scope,
            $tenant?->slug,
            $type,
            now()->format('YmdHis'),
            Str::random(8),
        ]));

        return $name.'.json'.($encrypted ? '.enc' : '');
    }

    private function encrypt(string $contents): string
    {
        $key = env('BACKUP_ENCRYPTION_KEY');

        if (! $key) {
            throw new \RuntimeException('BACKUP_ENCRYPTION_KEY must be set for encrypted backups.');
        }

        $iv = random_bytes(16);
        $cipherText = openssl_encrypt($contents, 'AES-256-CBC', hash('sha256', $key, true), OPENSSL_RAW_DATA, $iv);

        if ($cipherText === false) {
            throw new \RuntimeException('Backup encryption failed.');
        }

        return base64_encode($iv.$cipherText);
    }

    public static function featureKey(): string
    {
        return self::FEATURE_KEY;
    }
}
