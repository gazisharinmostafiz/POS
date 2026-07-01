<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('backups', function (Blueprint $table) {
            $table->id();
            $table->foreignId('tenant_id')->nullable()->constrained()->cascadeOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('scope');
            $table->string('type');
            $table->string('status')->default('pending');
            $table->string('disk')->default('backups');
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->boolean('encrypted')->default(false);
            $table->string('remote_disk')->nullable();
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();

            $table->index(['tenant_id', 'scope', 'status']);
            $table->index(['scope', 'type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('backups');
    }
};
