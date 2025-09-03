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
        Schema::create('processed_emails', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->onDelete('cascade');
            
            // Email Identification
            $table->string('message_id')->index(); // RFC822 Message-ID
            $table->string('content_hash', 64)->index(); // SHA256 of content
            $table->bigInteger('imap_uid')->nullable();
            $table->string('folder_name')->default('INBOX');
            
            // Email Headers
            $table->string('from_email');
            $table->string('from_name')->nullable();
            $table->json('to_addresses'); // Array of recipient objects
            $table->json('cc_addresses')->nullable();
            $table->json('bcc_addresses')->nullable();
            $table->string('subject');
            $table->timestamp('email_date');
            
            // Content
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            $table->json('attachments')->nullable(); // Array of attachment metadata
            $table->json('headers')->nullable(); // Full email headers
            
            // Processing Status
            $table->enum('processing_status', ['pending', 'processing', 'completed', 'error', 'skipped'])->default('pending');
            $table->timestamp('processed_at')->nullable();
            $table->integer('processing_time_ms')->nullable();
            $table->text('error_message')->nullable();
            $table->json('error_details')->nullable();
            
            // Classification Results
            $table->string('intent')->nullable();
            $table->string('category')->nullable();
            $table->string('sentiment')->nullable();
            $table->decimal('confidence_score', 5, 4)->nullable();
            $table->json('classification_metadata')->nullable();
            
            // Response Information
            $table->enum('response_status', ['none', 'auto_replied', 'escalated', 'manual_reply', 'suppressed'])->default('none');
            $table->uuid('response_id')->nullable(); // Links to send_logs
            $table->foreignUuid('escalation_id')->nullable()->constrained('escalations');
            
            // Deduplication
            $table->boolean('is_duplicate')->default(false);
            $table->uuid('original_email_id')->nullable();
            
            $table->timestamps();
            
            // Indexes for performance
            $table->index(['account_id', 'processing_status']);
            $table->index(['account_id', 'email_date']);
            $table->index(['message_id', 'account_id']);
            $table->index(['content_hash', 'account_id']);
            $table->index(['intent', 'category']);
            $table->index(['response_status']);
            $table->index(['is_duplicate', 'original_email_id']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('processed_emails');
    }
};
