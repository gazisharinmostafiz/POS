<?php

namespace Tests\Feature;

use App\Models\AuditLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\AuditLogService;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class UserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_creates_waiter(): void
    {
        [$tenant, $admin] = $this->tenantUser(Roles::ADMIN);

        $this->actingAs($admin)
            ->post('/tenant/users', [
                'name' => 'New Waiter',
                'email' => 'new-waiter@example.test',
                'role' => Roles::WAITER,
                'password' => 'password123',
                'is_active' => '1',
            ])
            ->assertRedirect('/tenant/users');

        $this->assertDatabaseHas('users', [
            'tenant_id' => $tenant->id,
            'name' => 'New Waiter',
            'email' => 'new-waiter@example.test',
            'role' => Roles::WAITER,
            'is_active' => true,
        ]);

        $created = User::query()->where('email', 'new-waiter@example.test')->firstOrFail();
        $this->assertTrue(Hash::check('password123', $created->password));
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::USER_CREATED,
            'auditable_type' => User::class,
            'auditable_id' => $created->id,
            'user_id' => $admin->id,
        ]);
    }

    public function test_super_admin_deletes_user(): void
    {
        [$tenant, $superAdmin] = $this->tenantUser(Roles::SUPER_ADMIN);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);

        $this->actingAs($superAdmin)
            ->delete("/tenant/users/{$target->id}")
            ->assertRedirect('/tenant/users');

        $this->assertDatabaseMissing('users', ['id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', [
            'event' => AuditLogService::DELETE_ACTION,
            'auditable_type' => User::class,
            'auditable_id' => $target->id,
            'user_id' => $superAdmin->id,
        ]);
    }

    public function test_admin_cannot_delete_if_restricted(): void
    {
        [$tenant, $admin] = $this->tenantUser(Roles::ADMIN);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::WAITER,
        ]);

        $this->actingAs($admin)
            ->delete("/tenant/users/{$target->id}")
            ->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
    }

    public function test_only_admin_cannot_delete_self(): void
    {
        [, $superAdmin] = $this->tenantUser(Roles::SUPER_ADMIN);

        $this->actingAs($superAdmin)
            ->delete("/tenant/users/{$superAdmin->id}")
            ->assertStatus(422);

        $this->assertDatabaseHas('users', ['id' => $superAdmin->id]);
    }

    public function test_tenant_isolation_works(): void
    {
        [$tenantA, $adminA] = $this->tenantUser(Roles::ADMIN);
        [$tenantB] = $this->tenantUser(Roles::ADMIN);
        $userA = User::factory()->create([
            'tenant_id' => $tenantA->id,
            'name' => 'Tenant A User',
            'role' => Roles::WAITER,
        ]);
        $userB = User::factory()->create([
            'tenant_id' => $tenantB->id,
            'name' => 'Tenant B User',
            'role' => Roles::WAITER,
        ]);

        $this->actingAs($adminA)
            ->get('/tenant/users')
            ->assertOk()
            ->assertSee('Tenant A User')
            ->assertDontSee('Tenant B User');

        $this->actingAs($adminA)
            ->put("/tenant/users/{$userB->id}", [
                'name' => 'Bad Update',
                'email' => $userB->email,
                'role' => Roles::WAITER,
                'is_active' => '1',
            ])
            ->assertNotFound();

        $this->assertDatabaseHas('users', [
            'id' => $userB->id,
            'name' => 'Tenant B User',
        ]);
        $this->assertDatabaseHas('users', ['id' => $userA->id]);
    }

    public function test_activate_deactivate_reset_password_and_last_login_display(): void
    {
        [$tenant, $admin] = $this->tenantUser(Roles::ADMIN);
        $target = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => Roles::COUNTER,
            'last_login_at' => now(),
        ]);

        $this->actingAs($admin)->patch("/tenant/users/{$target->id}/deactivate")->assertRedirect();
        $this->assertFalse($target->fresh()->is_active);

        $this->actingAs($admin)->patch("/tenant/users/{$target->id}/activate")->assertRedirect();
        $this->assertTrue($target->fresh()->is_active);

        $this->actingAs($admin)
            ->patch("/tenant/users/{$target->id}/password", ['password' => 'new-password'])
            ->assertRedirect();
        $this->assertTrue(Hash::check('new-password', $target->fresh()->password));

        $this->actingAs($admin)
            ->get('/tenant/users')
            ->assertOk()
            ->assertSee($target->last_login_at->format('Y-m-d H:i'));

        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogService::USER_DEACTIVATED, 'auditable_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogService::USER_ACTIVATED, 'auditable_id' => $target->id]);
        $this->assertDatabaseHas('audit_logs', ['event' => AuditLogService::USER_PASSWORD_RESET, 'auditable_id' => $target->id]);
    }

    public function test_platform_owner_can_manage_platform_and_tenant_users(): void
    {
        $tenant = Tenant::query()->create(['name' => 'Managed Tenant', 'slug' => 'managed-tenant']);
        $platformOwner = User::factory()->create([
            'tenant_id' => null,
            'role' => Roles::PLATFORM_OWNER,
        ]);

        $this->actingAs($platformOwner)
            ->post('/platform/users', [
                'name' => 'Platform Operator',
                'email' => 'platform-operator@example.test',
                'role' => Roles::PLATFORM_OWNER,
                'password' => 'password123',
                'is_active' => '1',
            ])
            ->assertRedirect('/platform/users');

        $this->actingAs($platformOwner)
            ->post("/platform/vendors/{$tenant->id}/users", [
                'name' => 'Tenant Admin',
                'email' => 'tenant-admin@example.test',
                'role' => Roles::ADMIN,
                'password' => 'password123',
                'is_active' => '1',
            ])
            ->assertRedirect("/platform/vendors/{$tenant->id}/users");

        $this->assertDatabaseHas('users', [
            'email' => 'platform-operator@example.test',
            'tenant_id' => null,
            'role' => Roles::PLATFORM_OWNER,
        ]);
        $this->assertDatabaseHas('users', [
            'email' => 'tenant-admin@example.test',
            'tenant_id' => $tenant->id,
            'role' => Roles::ADMIN,
        ]);
        $this->assertSame(2, AuditLog::query()->where('event', AuditLogService::USER_CREATED)->count());
    }

    public function test_waiter_counter_and_kitchen_cannot_access_user_management(): void
    {
        [$tenant] = $this->tenantUser(Roles::ADMIN);

        foreach ([Roles::WAITER, Roles::COUNTER, Roles::KITCHEN] as $role) {
            $user = User::factory()->create([
                'tenant_id' => $tenant->id,
                'role' => $role,
            ]);

            $this->actingAs($user)
                ->get('/tenant/users')
                ->assertForbidden();
        }
    }

    private function tenantUser(string $role): array
    {
        $tenant = Tenant::query()->create([
            'name' => 'Users Tenant '.uniqid(),
            'slug' => 'users-tenant-'.uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
        ]);

        return [$tenant, $user];
    }
}
