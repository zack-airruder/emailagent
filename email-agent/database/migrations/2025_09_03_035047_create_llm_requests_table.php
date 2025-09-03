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
        Schema::create('llm_requests', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignUuid('email_id')->nullable()->constrained('processed_emails')->onDelete('set null');
            
            // Request Details
            $table->string('provider'); // openai, anthropic, etc.
            $table->string('model'); // gpt-4, claude-3, etc.
            $table->enum('request_type', ['classification', 'response_generation', 'sentiment_analysis', 'intent_detection', 'content_moderation']);
            $table->longText('prompt');
            $table->json('parameters')->nullable(); // temperature, max_tokens, etc.
            
            // Response Details
            $table->enum('status', ['pending', 'completed', 'failed', 'timeout', 'rate_limited']);
            $table->longText('response')->nullable();
            $table->text('error_message')->nullable();
            $table->integer('http_status_code')->nullable();
            
            // Timing
            $table->timestamp('request_sent_at');
            $table->timestamp('response_received_at')->nullable();
            $table->integer('response_time_ms')->nullable();
            
            // Usage Tracking
            $table->integer('prompt_tokens')->nullable();
            $table->integer('completion_tokens')->nullable();
            $table->integer('total_tokens')->nullable();
            $table->decimal('cost_usd', 10, 6)->nullable();
            
            // Quality Metrics
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->json('quality_metrics')->nullable(); // Custom quality scores
            $table->boolean('human_reviewed')->default(false);
            $table->enum('human_rating', ['excellent', 'good', 'fair', 'poor'])->nullable();
            
            // Rate Limiting
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->json('rate_limit_headers')->nullable();
            
            // Context
            $table->json('context_data')->nullable(); // Additional context passed to LLM
            $table->string('session_id')->nullable(); // For conversation tracking
            $table->uuid('parent_request_id')->nullable(); // For chained requests
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->foreignId('requested_by')->constrained('users');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['provider', 'model', 'created_at']);
            $table->index(['account_id', 'request_type']);
            $table->index(['status', 'created_at']);
            $table->index(['request_type', 'created_at']);
            $table->index(['cost_usd', 'created_at']);
            $table->index(['next_retry_at', 'retry_count']);
            
            // Self-referencing foreign key
            $table->foreign('parent_request_id')->references('id')->on('llm_requests')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('llm_requests');
    }
};
