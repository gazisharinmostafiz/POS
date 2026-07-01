<?php

use App\Models\KitchenTicket;
use App\Models\Order;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('orders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('order_number')->unique();
            $table->string('source_type');
            $table->unsignedInteger('table_number')->nullable();
            $table->foreignId('waiter_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('cashier_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('order_status')->default(Order::STATUS_PENDING);
            $table->string('payment_status')->default(Order::PAYMENT_UNPAID);
            $table->boolean('is_addon')->default(false);
            $table->text('kitchen_note')->nullable();
            $table->decimal('subtotal', 10, 2)->default(0);
            $table->decimal('discount_total', 10, 2)->default(0);
            $table->decimal('service_charge_total', 10, 2)->default(0);
            $table->decimal('tax_total', 10, 2)->default(0);
            $table->decimal('total', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'order_status']);
            $table->index(['tenant_id', 'source_type', 'table_number']);
        });

        Schema::create('order_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->foreignId('menu_item_id')->nullable()->constrained()->nullOnDelete();
            $table->string('item_name_snapshot');
            $table->decimal('unit_price_snapshot', 10, 2);
            $table->unsignedInteger('quantity');
            $table->decimal('line_total', 10, 2);
            $table->text('item_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
        });

        Schema::create('kitchen_tickets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->constrained()->cascadeOnDelete();
            $table->string('ticket_number')->unique();
            $table->string('status')->default(KitchenTicket::STATUS_PENDING);
            $table->boolean('is_addon')->default(false);
            $table->text('kitchen_note')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'branch_id', 'status', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('kitchen_tickets');
        Schema::dropIfExists('order_items');
        Schema::dropIfExists('orders');
    }
};
