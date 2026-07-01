<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('provider_account_settings', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('account_reference')->nullable();
            $table->string('terminal_id')->nullable();
            $table->json('settings')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['tenant_id', 'branch_id', 'provider']);
        });

        Schema::create('webhook_events', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('event_id')->nullable();
            $table->string('event_type')->nullable();
            $table->json('payload');
            $table->json('headers')->nullable();
            $table->boolean('signature_verified')->default(false);
            $table->timestamp('processed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider', 'event_id']);
        });

        Schema::table('payments', function (Blueprint $table) {
            $table->string('provider')->nullable()->after('status');
            $table->string('provider_transaction_id')->nullable()->after('provider');
            $table->string('terminal_id')->nullable()->after('provider_transaction_id');
            $table->json('provider_metadata')->nullable()->after('terminal_id');
        });
    }

    public function down(): void
    {
        Schema::table('payments', function (Blueprint $table) {
            $table->dropColumn([
                'provider',
                'provider_transaction_id',
                'terminal_id',
                'provider_metadata',
            ]);
        });

        Schema::dropIfExists('webhook_events');
        Schema::dropIfExists('provider_account_settings');
    }
};
