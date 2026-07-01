<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->string('bridge_token_hash')->nullable()->after('kitchen_category_routes');
            $table->string('bridge_status')->nullable()->after('bridge_token_hash');
            $table->timestamp('last_seen_at')->nullable()->after('bridge_status');
        });
    }

    public function down(): void
    {
        Schema::table('printers', function (Blueprint $table) {
            $table->dropColumn(['bridge_token_hash', 'bridge_status', 'last_seen_at']);
        });
    }
};
