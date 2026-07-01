<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('orders', function (Blueprint $table) {
            $table->string('discount_type')->nullable()->after('discount_total');
            $table->decimal('discount_value', 10, 2)->default(0)->after('discount_type');
            $table->text('discount_reason')->nullable()->after('discount_value');
            $table->foreignId('discount_applied_by')->nullable()->after('discount_reason')->constrained('users')->nullOnDelete();
        });

        Schema::create('payments', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('order_ids');
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->decimal('cash_amount', 10, 2)->default(0);
            $table->decimal('card_amount', 10, 2)->default(0);
            $table->decimal('total_paid', 10, 2)->default(0);
            $table->decimal('total_payable', 10, 2)->default(0);
            $table->decimal('balance', 10, 2)->default(0);
            $table->decimal('change_amount', 10, 2)->default(0);
            $table->string('status');
            $table->timestamps();

            $table->index(['tenant_id', 'status']);
        });

        Schema::create('split_bills', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('order_id')->nullable()->constrained()->cascadeOnDelete();
            $table->json('order_ids');
            $table->foreignId('cashier_id')->constrained('users')->cascadeOnDelete();
            $table->unsignedInteger('people_count');
            $table->decimal('total_payable', 10, 2);
            $table->decimal('amount_per_person', 10, 2);
            $table->decimal('rounding_difference', 10, 2)->default(0);
            $table->timestamps();

            $table->index(['tenant_id', 'order_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('split_bills');
        Schema::dropIfExists('payments');

        Schema::table('orders', function (Blueprint $table) {
            $table->dropConstrainedForeignId('discount_applied_by');
            $table->dropColumn([
                'discount_type',
                'discount_value',
                'discount_reason',
            ]);
        });
    }
};
