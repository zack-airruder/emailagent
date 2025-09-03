<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignId('user_id')->constrained()->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            
            // IMAP Configuration
            $table->string('imap_host');
            $table->integer('imap_port')->default(993);
            $table->enum('imap_encryption', ['ssl', 'tls', 'none'])->default('ssl');
            $table->string('imap_username');
            $table->text('imap_password_encrypted');
            $table->boolean('imap_validate_cert')->default(true);
            
            // SMTP Configuration
            $table->string('smtp_host');
            $table->integer('smtp_port')->default(587);
            $table->enum('smtp_encryption', ['tls', 'ssl', 'none'])->default('tls');
            $table->string('smtp_username');
            $table->text('smtp_password_encrypted');
            $table->string('smtp_from_name');
            $table->string('smtp_from_email');
            
            // Status and Health
            $table->enum('status', ['active', 'inactive', 'suspended'])->default('active');
            $table->enum('health_status', ['healthy', 'warning', 'error'])->default('healthy');
            $table->integer('consecutive_failures')->default(0);
            $table->timestamp('last_sync_at')->nullable();
            $table->timestamp('last_error_at')->nullable();
            $table->text('last_error_message')->nullable();
            
            // Settings
            $table->json('sync_settings')->nullable();
            $table->json('notification_settings')->nullable();
            $table->json('metadata')->nullable();
            
            $table->timestamps();
            
            // Indexes
            $table->index(['user_id', 'status']);
            $table->index(['health_status', 'consecutive_failures']);
            $table->index('last_sync_at');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
