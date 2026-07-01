<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AuditLogService
{
    public const LOGIN = 'login';
    public const LOGOUT = 'logout';
    public const FAILED_LOGIN = 'failed_login';
    public const SETTINGS_CHANGED = 'settings_changed';
    public const DELETE_ACTION = 'delete_action';
    public const IMPERSONATION_STARTED = 'impersonation_started';
    public const VENDOR_CREATED = 'vendor_created';
    public const VENDOR_UPDATED = 'vendor_updated';
    public const VENDOR_SUSPENDED = 'vendor_suspended';
    public const VENDOR_REACTIVATED = 'vendor_reactivated';
    public const USER_CREATED = 'user_created';
    public const USER_UPDATED = 'user_updated';
    public const USER_ACTIVATED = 'user_activated';
    public const USER_DEACTIVATED = 'user_deactivated';
    public const USER_PASSWORD_RESET = 'user_password_reset';
    public const SUBSCRIPTION_CREATED = 'subscription_created';
    public const SUBSCRIPTION_CHANGED = 'subscription_changed';
    public const SUBSCRIPTION_CANCELLED = 'subscription_cancelled';
    public const SUBSCRIPTION_WEBHOOK_SYNCED = 'subscription_webhook_synced';
    public const BACKUP_CREATED = 'backup_created';
    public const BACKUP_DOWNLOADED = 'backup_downloaded';
    public const BACKUP_RESTORED = 'backup_restored';
    public const MIGRATION_PACKAGE_CREATED = 'migration_package_created';
    public const MIGRATION_PACKAGE_DOWNLOADED = 'migration_package_downloaded';
    public const MIGRATION_RESTORE_CONFIRMED = 'migration_restore_confirmed';
    public const MIGRATION_CREDENTIALS_SAVED = 'migration_credentials_saved';
    public const MAINTENANCE_MODE_TOGGLED = 'maintenance_mode_toggled';
    public const RELEASE_NOTE_CREATED = 'release_note_created';
    public const UPDATE_BACKUP_CREATED = 'update_backup_created';

    public function record(
        string $event,
        ?Model $auditable = null,
        array $metadata = [],
        ?User $user = null,
        ?Request $request = null,
        ?User $impersonator = null
    ): AuditLog {
        $request ??= request();
        $user ??= Auth::user();
        $tenantId = current_tenant()?->id ?? $user?->tenant_id ?? $auditable?->tenant_id;
        $branchId = current_branch()?->id ?? $user?->branch_id ?? $auditable?->branch_id;

        return AuditLog::query()->create([
            'tenant_id' => $tenantId,
            'branch_id' => $branchId,
            'user_id' => $user?->id,
            'impersonator_user_id' => $impersonator?->id,
            'event' => $event,
            'auditable_type' => $auditable ? $auditable::class : null,
            'auditable_id' => $auditable?->getKey(),
            'ip_address' => $request?->ip(),
            'user_agent' => $request?->userAgent(),
            'metadata' => $metadata ?: null,
        ]);
    }

    public function login(User $user, Request $request): AuditLog
    {
        return $this->record(self::LOGIN, null, [], $user, $request);
    }

    public function logout(User $user, Request $request): AuditLog
    {
        return $this->record(self::LOGOUT, null, [], $user, $request);
    }

    public function failedLogin(string $email, Request $request): AuditLog
    {
        return $this->record(self::FAILED_LOGIN, null, ['email' => $email], null, $request);
    }

    public function settingsChanged(Model $setting, array $metadata = []): AuditLog
    {
        return $this->record(self::SETTINGS_CHANGED, $setting, $metadata);
    }

    public function deleted(Model $model, array $metadata = []): AuditLog
    {
        return $this->record(self::DELETE_ACTION, $model, $metadata);
    }

    public function impersonationStarted(User $targetUser, User $impersonator, Request $request): AuditLog
    {
        return $this->record(
            self::IMPERSONATION_STARTED,
            $targetUser,
            ['target_user_id' => $targetUser->id],
            $targetUser,
            $request,
            $impersonator
        );
    }

    public function vendorCreated(Model $tenant, array $metadata = []): AuditLog
    {
        return $this->record(self::VENDOR_CREATED, $tenant, $metadata);
    }

    public function vendorUpdated(Model $tenant, array $metadata = []): AuditLog
    {
        return $this->record(self::VENDOR_UPDATED, $tenant, $metadata);
    }

    public function vendorSuspended(Model $tenant, array $metadata = []): AuditLog
    {
        return $this->record(self::VENDOR_SUSPENDED, $tenant, $metadata);
    }

    public function vendorReactivated(Model $tenant, array $metadata = []): AuditLog
    {
        return $this->record(self::VENDOR_REACTIVATED, $tenant, $metadata);
    }

    public function userChanged(string $event, User $targetUser, array $metadata = []): AuditLog
    {
        return $this->record($event, $targetUser, $metadata);
    }

    public function subscriptionChanged(string $event, Model $tenant, array $metadata = []): AuditLog
    {
        return $this->record($event, $tenant, $metadata);
    }

    public function backupChanged(string $event, Model $backup, array $metadata = []): AuditLog
    {
        return $this->record($event, $backup, $metadata);
    }

    public function migrationChanged(string $event, Model $migration, array $metadata = []): AuditLog
    {
        return $this->record($event, $migration, $metadata);
    }

    public function releaseChanged(string $event, Model $releaseNote, array $metadata = []): AuditLog
    {
        return $this->record($event, $releaseNote, $metadata);
    }
}
