<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Branch;
use App\Models\Tenant;
use App\Models\TenantSetting;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthAccessAuditTest extends TestCase
{
    use RefreshDatabase;

    public function test_each_role_redirects_correctly_after_login(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Redirect Tenant', 'slug' => 'redirect']);
        $branch = $tenant->branches()->create(['name' => 'Main Branch', 'slug' => 'main']);

        $roles = [
            Roles::PLATFORM_OWNER => '/platform',
            Roles::SUPER_ADMIN => '/tenant/admin',
            Roles::ADMIN => '/tenant/admin',
            Roles::WAITER => '/waiter/pos',
            Roles::COUNTER => '/counter',
            Roles::KITCHEN => '/kitchen',
        ];

        foreach ($roles as $role => $path) {
            $user = User::factory()->create([
                'tenant_id' => $role === Roles::PLATFORM_OWNER ? null : $tenant->id,
                'branch_id' => $role === Roles::PLATFORM_OWNER ? null : $branch->id,
                'role' => $role,
                'email' => "{$role}@example.test",
                'password' => 'password',
            ]);

            $this->post('/login', [
                'email' => $user->email,
                'password' => 'password',
            ])->assertRedirect($path);

            $this->post('/logout')->assertRedirect('/login');
        }
    }

    public function test_restricted_pages_are_blocked_by_role(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Access Tenant', 'slug' => 'access']);
        $branch = $tenant->branches()->create(['name' => 'Main Branch', 'slug' => 'main']);

        $waiter = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => Roles::WAITER,
        ]);

        $this->actingAs($waiter);

        $this->get('/waiter/pos')->assertOk();
        $this->get('/counter')->assertForbidden();
        $this->get('/kitchen')->assertForbidden();
        $this->get('/tenant/admin')->assertForbidden();
        $this->get('/platform')->assertForbidden();
    }

    public function test_tenant_pages_are_blocked_without_tenant_context(): void
    {
        $admin = User::factory()->create([
            'tenant_id' => null,
            'branch_id' => null,
            'role' => Roles::ADMIN,
        ]);

        $this->actingAs($admin);

        $this->get('/tenant/admin')->assertNotFound();
    }

    public function test_passwords_are_hashed_when_users_are_created(): void
    {
        $user = User::query()->create([
            'name' => 'Hashed User',
            'email' => 'hashed@example.test',
            'role' => Roles::PLATFORM_OWNER,
            'password' => 'plain-password',
        ]);

        $this->assertNotSame('plain-password', $user->password);
        $this->assertTrue(Hash::check('plain-password', $user->password));
    }

    public function test_audit_log_created_for_login_logout_and_failed_login(): void
    {
        $user = User::factory()->create([
            'role' => Roles::PLATFORM_OWNER,
            'email' => 'audit@example.test',
            'password' => 'password',
        ]);

        $this->post('/login', [
            'email' => $user->email,
            'password' => 'password',
        ])->assertRedirect('/platform');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => AuditLogService::LOGIN,
        ]);

        $this->post('/logout')->assertRedirect('/login');

        $this->assertDatabaseHas('audit_logs', [
            'user_id' => $user->id,
            'event' => AuditLogService::LOGOUT,
        ]);

        $this->post('/login', [
            'email' => 'missing@example.test',
            'password' => 'wrong-password',
        ])->assertSessionHasErrors('email');

        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::FAILED_LOGIN,
        ]);
    }

    public function test_audit_log_created_for_settings_changes_and_delete_actions(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Audit Tenant', 'slug' => 'audit-tenant']);
        $branch = Branch::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Main Branch',
            'slug' => 'main',
        ]);
        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'role' => Roles::ADMIN,
        ]);

        $this->actingAs($user);

        $setting = TenantSetting::query()->create([
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'key' => 'platform.locale',
            'value' => ['locale' => 'en'],
        ]);

        app(AuditLogService::class)->settingsChanged($setting, ['changed' => ['locale']]);
        app(AuditLogService::class)->deleted($branch, ['reason' => 'test-delete']);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'event' => AuditLogService::SETTINGS_CHANGED,
            'auditable_type' => TenantSetting::class,
            'auditable_id' => $setting->id,
        ]);

        $this->assertDatabaseHas('audit_logs', [
            'tenant_id' => $tenant->id,
            'branch_id' => $branch->id,
            'user_id' => $user->id,
            'event' => AuditLogService::DELETE_ACTION,
            'auditable_type' => Branch::class,
            'auditable_id' => $branch->id,
        ]);
    }
}
