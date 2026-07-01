<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('chat_messages', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained()->nullOnDelete();
            $table->string('room');
            $table->foreignId('sender_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('related_order_id')->nullable()->constrained('orders')->nullOnDelete();
            $table->text('message');
            $table->timestamps();

            $table->index(['tenant_id', 'room', 'created_at']);
        });

        Schema::create('chat_reads', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('room');
            $table->foreignId('last_read_message_id')->nullable()->constrained('chat_messages')->nullOnDelete();
            $table->timestamps();

            $table->unique(['tenant_id', 'user_id', 'room']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('chat_reads');
        Schema::dropIfExists('chat_messages');
    }
};
