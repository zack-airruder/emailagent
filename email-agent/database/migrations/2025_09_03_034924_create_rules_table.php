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
        Schema::create('rules', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->onDelete('cascade');
            $table->string('name');
            $table->text('description')->nullable();
            
            // Rule Configuration
            $table->enum('status', ['active', 'inactive', 'draft'])->default('draft');
            $table->integer('priority')->default(100);
            $table->integer('execution_order')->default(1000);
            
            // Conditions
            $table->json('conditions'); // Array of condition objects
            $table->enum('condition_logic', ['AND', 'OR'])->default('AND');
            
            // Actions
            $table->json('actions'); // Array of action objects
            $table->boolean('stop_processing')->default(false);
            
            // Scheduling
            $table->json('schedule')->nullable(); // Cron-like schedule
            $table->timestamp('active_from')->nullable();
            $table->timestamp('active_until')->nullable();
            
            // Statistics
            $table->integer('execution_count')->default(0);
            $table->integer('success_count')->default(0);
            $table->integer('error_count')->default(0);
            $table->timestamp('last_executed_at')->nullable();
            $table->text('last_error')->nullable();
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            $table->foreignId('updated_by')->nullable()->constrained('users');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['account_id', 'status', 'priority']);
            $table->index(['account_id', 'execution_order']);
            $table->index(['status', 'active_from', 'active_until']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('rules');
    }
};
