<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\MenuItem;
use App\Models\Tenant;
use App\Models\User;
use App\Support\Roles;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class MenuManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_edit_and_hide_item(): void
    {
        Storage::fake('public');

        [$tenant, $admin] = $this->tenantUser(Roles::ADMIN);
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mains',
            'sort_order' => 1,
        ]);

        $this->actingAs($admin)
            ->post('/tenant/menu/items', $this->itemPayload($category, [
                'name' => 'Fish Curry',
                'image' => $this->fakePngUpload(),
            ]))
            ->assertRedirect('/tenant/menu/items');

        $item = MenuItem::query()->where('tenant_id', $tenant->id)->where('name', 'Fish Curry')->firstOrFail();
        $this->assertNotNull($item->image_path);
        Storage::disk('public')->assertExists($item->image_path);

        $this->actingAs($admin)
            ->put("/tenant/menu/items/{$item->id}", $this->itemPayload($category, [
                'name' => 'Fish Curry Large',
                'price' => '14.50',
            ]))
            ->assertRedirect('/tenant/menu/items');

        $this->assertDatabaseHas('menu_items', [
            'id' => $item->id,
            'tenant_id' => $tenant->id,
            'name' => 'Fish Curry Large',
            'price' => 14.50,
        ]);

        $this->patch("/tenant/menu/items/{$item->id}/visibility")->assertRedirect();

        $this->assertFalse($item->fresh()->is_active);
    }

    public function test_waiter_cannot_manage_menu(): void
    {
        [$tenant, $waiter] = $this->tenantUser(Roles::WAITER);
        Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mains',
            'sort_order' => 1,
        ]);

        $this->actingAs($waiter)
            ->get('/tenant/menu/items')
            ->assertForbidden();
    }

    public function test_delete_is_restricted_to_super_admin_or_platform_owner(): void
    {
        [$tenant, $admin] = $this->tenantUser(Roles::ADMIN);
        $category = Category::query()->create([
            'tenant_id' => $tenant->id,
            'name' => 'Mains',
            'sort_order' => 1,
        ]);
        $item = MenuItem::query()->create($this->itemAttributes($tenant, $category));

        $this->actingAs($admin)
            ->delete("/tenant/menu/items/{$item->id}")
            ->assertForbidden();

        [$sameTenant, $superAdmin] = $this->tenantUser(Roles::SUPER_ADMIN, $tenant);

        $this->actingAs($superAdmin)
            ->delete("/tenant/menu/items/{$item->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('menu_items', ['id' => $item->id]);

        $platformItem = MenuItem::query()->create($this->itemAttributes($sameTenant, $category, ['name' => 'Platform Delete']));
        $platformOwner = User::factory()->create(['role' => Roles::PLATFORM_OWNER]);

        $this->actingAs($platformOwner)
            ->delete("/platform/vendors/menu/items/{$platformItem->id}")
            ->assertRedirect();

        $this->assertDatabaseMissing('menu_items', ['id' => $platformItem->id]);
    }

    public function test_item_list_is_tenant_scoped(): void
    {
        [$tenantA, $adminA] = $this->tenantUser(Roles::ADMIN);
        [$tenantB] = $this->tenantUser(Roles::ADMIN);

        $categoryA = Category::query()->create(['tenant_id' => $tenantA->id, 'name' => 'Tenant A Category']);
        $categoryB = Category::query()->create(['tenant_id' => $tenantB->id, 'name' => 'Tenant B Category']);

        MenuItem::query()->create($this->itemAttributes($tenantA, $categoryA, ['name' => 'Tenant A Item']));
        MenuItem::query()->create($this->itemAttributes($tenantB, $categoryB, ['name' => 'Tenant B Item']));

        $this->actingAs($adminA)
            ->get('/tenant/menu/items')
            ->assertOk()
            ->assertSee('Tenant A Item')
            ->assertDontSee('Tenant B Item');
    }

    public function test_waiter_pos_only_shows_active_available_items(): void
    {
        [$tenant, $waiter] = $this->tenantUser(Roles::WAITER);
        $category = Category::query()->create(['tenant_id' => $tenant->id, 'name' => 'Mains', 'is_active' => true]);

        MenuItem::query()->create($this->itemAttributes($tenant, $category, ['name' => 'Visible Item']));
        MenuItem::query()->create($this->itemAttributes($tenant, $category, ['name' => 'Hidden Item', 'is_active' => false]));
        MenuItem::query()->create($this->itemAttributes($tenant, $category, ['name' => 'Unavailable Item', 'is_available' => false]));

        $this->actingAs($waiter)
            ->getJson('/waiter/pos/data')
            ->assertOk()
            ->assertJsonFragment(['name' => 'Visible Item'])
            ->assertJsonMissing(['name' => 'Hidden Item'])
            ->assertJsonMissing(['name' => 'Unavailable Item']);
    }

    private function tenantUser(string $role, ?Tenant $tenant = null): array
    {
        $tenant ??= Tenant::query()->create([
            'name' => 'Menu Tenant '.uniqid(),
            'slug' => 'menu-tenant-'.uniqid(),
        ]);

        $user = User::factory()->create([
            'tenant_id' => $tenant->id,
            'role' => $role,
        ]);

        return [$tenant, $user];
    }

    private function itemPayload(Category $category, array $overrides = []): array
    {
        return array_merge([
            'category_id' => $category->id,
            'name' => 'Menu Item',
            'description' => 'Menu item description',
            'price' => '12.00',
            'is_available' => '1',
            'is_active' => '1',
            'sort_order' => 1,
        ], $overrides);
    }

    private function itemAttributes(Tenant $tenant, Category $category, array $overrides = []): array
    {
        return array_merge([
            'tenant_id' => $tenant->id,
            'category_id' => $category->id,
            'name' => 'Menu Item',
            'description' => 'Menu item description',
            'price' => 12,
            'is_available' => true,
            'is_active' => true,
            'sort_order' => 1,
        ], $overrides);
    }

    private function fakePngUpload(): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'menu');
        file_put_contents($path, base64_decode(
            'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+/p9sAAAAASUVORK5CYII='
        ));

        return new UploadedFile($path, 'menu.png', 'image/png', null, true);
    }
}
