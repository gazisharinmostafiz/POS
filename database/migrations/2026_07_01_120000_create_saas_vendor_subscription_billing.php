<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('plans', function (Blueprint $table) {
            $table->string('stripe_monthly_price_id')->nullable()->after('is_active');
            $table->string('stripe_yearly_price_id')->nullable()->after('stripe_monthly_price_id');
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->string('stripe_customer_id')->nullable()->after('subscription_ends_at');
        });

        Schema::create('vendor_subscriptions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('plan_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider')->default('stripe');
            $table->string('billing_interval')->default('monthly');
            $table->string('stripe_customer_id')->nullable();
            $table->string('stripe_subscription_id')->nullable();
            $table->string('stripe_subscription_item_id')->nullable();
            $table->string('stripe_price_id')->nullable();
            $table->string('status')->default('incomplete');
            $table->timestamp('trial_ends_at')->nullable();
            $table->timestamp('current_period_ends_at')->nullable();
            $table->timestamp('grace_ends_at')->nullable();
            $table->timestamp('cancelled_at')->nullable();
            $table->json('provider_metadata')->nullable();
            $table->timestamps();

            $table->unique(['provider', 'stripe_subscription_id']);
            $table->index(['tenant_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('vendor_subscriptions');

        Schema::table('tenants', function (Blueprint $table) {
            $table->dropColumn('stripe_customer_id');
        });

        Schema::table('plans', function (Blueprint $table) {
            $table->dropColumn(['stripe_monthly_price_id', 'stripe_yearly_price_id']);
        });
    }
};
