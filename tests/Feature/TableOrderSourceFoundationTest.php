<?php

namespace Tests\Feature;

use App\Models\RestaurantTable;
use App\Models\TableSession;
use App\Models\Tenant;
use App\Services\Settings\RestaurantSettingsService;
use App\Support\OrderSourceTypes;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TableOrderSourceFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_table_count_setting_creates_expected_active_tables(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Table Tenant',
            'slug' => 'table-tenant',
        ]);

        app(RestaurantSettingsService::class)->save($tenant, ['table_count' => 4]);

        $this->assertSame(4, RestaurantTable::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count());
        $this->assertDatabaseHas('tables', [
            'tenant_id' => $tenant->id,
            'number' => 1,
            'label' => 'Table 1',
            'status' => RestaurantTable::STATUS_FREE,
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('tables', [
            'tenant_id' => $tenant->id,
            'number' => 4,
            'label' => 'Table 4',
            'status' => RestaurantTable::STATUS_FREE,
            'is_active' => true,
        ]);

        app(RestaurantSettingsService::class)->save($tenant, ['table_count' => 2]);

        $this->assertSame(2, RestaurantTable::query()->where('tenant_id', $tenant->id)->where('is_active', true)->count());
        $this->assertDatabaseHas('tables', [
            'tenant_id' => $tenant->id,
            'number' => 3,
            'is_active' => false,
            'status' => RestaurantTable::STATUS_FREE,
        ]);
    }

    public function test_table_status_can_update(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Status Tenant',
            'slug' => 'status-tenant',
        ]);

        app(RestaurantSettingsService::class)->save($tenant, ['table_count' => 1]);

        $table = RestaurantTable::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $this->assertTrue($table->updateStatus(RestaurantTable::STATUS_COOKING));
        $this->assertSame(RestaurantTable::STATUS_COOKING, $table->fresh()->status);

        $this->expectException(\InvalidArgumentException::class);
        $table->updateStatus('not_a_status');
    }

    public function test_takeaway_and_walk_in_sources_are_valid_without_table_number(): void
    {
        $this->assertTrue(OrderSourceTypes::isValid(OrderSourceTypes::TABLE));
        $this->assertTrue(OrderSourceTypes::isValid(OrderSourceTypes::TAKEAWAY));
        $this->assertTrue(OrderSourceTypes::isValid(OrderSourceTypes::WALK_IN));
        $this->assertTrue(OrderSourceTypes::requiresTable(OrderSourceTypes::TABLE));
        $this->assertFalse(OrderSourceTypes::requiresTable(OrderSourceTypes::TAKEAWAY));
        $this->assertFalse(OrderSourceTypes::requiresTable(OrderSourceTypes::WALK_IN));
    }

    public function test_table_session_can_group_future_unpaid_table_tickets(): void
    {
        $tenant = Tenant::query()->create([
            'name' => 'Session Tenant',
            'slug' => 'session-tenant',
        ]);

        app(RestaurantSettingsService::class)->save($tenant, ['table_count' => 1]);
        $table = RestaurantTable::query()->where('tenant_id', $tenant->id)->firstOrFail();

        $session = TableSession::query()->create([
            'tenant_id' => $tenant->id,
            'table_id' => $table->id,
            'status' => TableSession::STATUS_OPEN,
            'opened_at' => now(),
        ]);

        $this->assertTrue($session->table->is($table));
        $this->assertSame(TableSession::STATUS_OPEN, $session->status);
    }
}
