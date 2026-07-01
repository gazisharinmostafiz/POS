<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('provider_account_settings', function (Blueprint $table) {
            $table->longText('encrypted_credentials')->nullable()->after('settings');
        });

        Schema::create('payment_provider_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('payment_id')->nullable()->constrained()->nullOnDelete();
            $table->string('provider');
            $table->string('action');
            $table->json('request_payload')->nullable();
            $table->json('response_payload')->nullable();
            $table->unsignedSmallInteger('status_code')->nullable();
            $table->boolean('success')->default(false);
            $table->text('error_message')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'provider', 'action']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('payment_provider_logs');

        Schema::table('provider_account_settings', function (Blueprint $table) {
            $table->dropColumn('encrypted_credentials');
        });
    }
};
