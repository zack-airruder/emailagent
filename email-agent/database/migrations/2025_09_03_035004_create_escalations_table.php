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
        Schema::create('escalations', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignUuid('email_id')->constrained('processed_emails')->onDelete('cascade');
            
            // Escalation Details
            $table->enum('escalation_type', ['auto', 'manual', 'rule_based', 'error_recovery']);
            $table->enum('priority', ['low', 'medium', 'high', 'urgent'])->default('medium');
            $table->text('reason');
            $table->json('context')->nullable(); // Additional context data
            
            // State Management
            $table->enum('status', ['pending', 'assigned', 'in_review', 'approved', 'rejected', 'escalated_further'])->default('pending');
            $table->foreignId('assigned_to')->nullable()->constrained('users');
            $table->timestamp('assigned_at')->nullable();
            
            // SLA Tracking
            $table->timestamp('sla_deadline')->nullable();
            $table->boolean('sla_breached')->default(false);
            $table->timestamp('sla_breach_time')->nullable();
            
            // Response Draft
            $table->text('draft_subject')->nullable();
            $table->longText('draft_body')->nullable();
            $table->json('draft_metadata')->nullable();
            $table->timestamp('draft_updated_at')->nullable();
            
            // Resolution
            $table->text('resolution_notes')->nullable();
            $table->enum('resolution_action', ['approved', 'rejected', 'modified', 'escalated'])->nullable();
            $table->timestamp('resolved_at')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users');
            
            // Escalation Chain
            $table->uuid('parent_escalation_id')->nullable();
            $table->integer('escalation_level')->default(1);
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->foreignId('created_by')->constrained('users');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'priority', 'sla_deadline']);
            $table->index(['assigned_to', 'status']);
            $table->index(['account_id', 'status']);
            $table->index(['escalation_type', 'priority']);
            $table->index(['sla_deadline', 'sla_breached']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('escalations');
    }
};
