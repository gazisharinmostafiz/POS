<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('plans', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->unsignedInteger('sort_order')->default(0);
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('plan_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('plan_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('name')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['plan_id', 'feature_key']);
        });

        Schema::create('addons', function (Blueprint $table) {
            $table->id();
            $table->string('name');
            $table->string('slug')->unique();
            $table->text('description')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('addon_features', function (Blueprint $table) {
            $table->id();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->string('name')->nullable();
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['addon_id', 'feature_key']);
        });

        Schema::create('tenant_addons', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('addon_id')->constrained()->cascadeOnDelete();
            $table->string('status')->default('active');
            $table->timestamp('starts_at')->nullable();
            $table->timestamp('ends_at')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'addon_id']);
        });

        Schema::create('feature_flags', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->string('feature_key');
            $table->boolean('enabled')->default(true);
            $table->unsignedInteger('limit')->nullable();
            $table->json('metadata')->nullable();
            $table->timestamps();

            $table->unique(['tenant_id', 'feature_key']);
        });

        Schema::table('tenants', function (Blueprint $table) {
            $table->foreignId('plan_id')->nullable()->after('id')->constrained()->nullOnDelete();
            $table->string('subscription_status')->after('is_active')->default('active');
            $table->timestamp('subscription_ends_at')->nullable()->after('subscription_status');
        });
    }

    public function down(): void
    {
        Schema::table('tenants', function (Blueprint $table) {
            $table->dropConstrainedForeignId('plan_id');
            $table->dropColumn(['subscription_status', 'subscription_ends_at']);
        });

        Schema::dropIfExists('feature_flags');
        Schema::dropIfExists('tenant_addons');
        Schema::dropIfExists('addon_features');
        Schema::dropIfExists('addons');
        Schema::dropIfExists('plan_features');
        Schema::dropIfExists('plans');
    }
};
