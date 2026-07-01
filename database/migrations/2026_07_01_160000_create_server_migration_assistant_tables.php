<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('server_migrations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('backup_id')->nullable()->constrained('backups')->nullOnDelete();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('pending');
            $table->string('version');
            $table->string('disk')->default('backups');
            $table->string('path')->nullable();
            $table->string('filename')->nullable();
            $table->string('checksum')->nullable();
            $table->unsignedBigInteger('size_bytes')->default(0);
            $table->json('metadata')->nullable();
            $table->text('last_error')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->timestamps();
        });

        Schema::create('migration_remote_credentials', function (Blueprint $table) {
            $table->id();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('name');
            $table->string('host')->nullable();
            $table->unsignedInteger('port')->nullable();
            $table->string('username')->nullable();
            $table->longText('encrypted_credentials')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('migration_remote_credentials');
        Schema::dropIfExists('server_migrations');
    }
};
