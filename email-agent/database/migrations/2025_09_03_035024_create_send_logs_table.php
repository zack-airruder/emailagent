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
        Schema::create('send_logs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('account_id')->constrained('accounts')->onDelete('cascade');
            $table->foreignUuid('email_id')->nullable()->constrained('processed_emails')->onDelete('set null');
            $table->uuid('escalation_id')->nullable();
            
            // Email Details
            $table->string('message_id')->unique();
            $table->string('from_address');
            $table->json('to_addresses'); // Array of recipients
            $table->json('cc_addresses')->nullable();
            $table->json('bcc_addresses')->nullable();
            $table->string('subject');
            $table->longText('body_text')->nullable();
            $table->longText('body_html')->nullable();
            
            // Send Configuration
            $table->enum('send_type', ['auto_reply', 'manual', 'escalation_response', 'bulk', 'test']);
            $table->enum('priority', ['low', 'normal', 'high', 'urgent'])->default('normal');
            $table->json('headers')->nullable(); // Custom headers
            $table->json('attachments')->nullable(); // Attachment metadata
            
            // Delivery Status
            $table->enum('status', ['queued', 'sending', 'sent', 'delivered', 'bounced', 'failed', 'rejected']);
            $table->text('status_message')->nullable();
            $table->timestamp('queued_at');
            $table->timestamp('sent_at')->nullable();
            $table->timestamp('delivered_at')->nullable();
            $table->timestamp('bounced_at')->nullable();
            
            // SMTP Details
            $table->string('smtp_server')->nullable();
            $table->integer('smtp_response_code')->nullable();
            $table->text('smtp_response_message')->nullable();
            $table->json('smtp_headers')->nullable(); // Full SMTP headers
            
            // Tracking
            $table->boolean('read_receipt_requested')->default(false);
            $table->timestamp('read_at')->nullable();
            $table->integer('retry_count')->default(0);
            $table->timestamp('next_retry_at')->nullable();
            $table->json('retry_log')->nullable(); // Array of retry attempts
            
            // Content Analysis
            $table->integer('body_size')->nullable();
            $table->integer('attachment_count')->default(0);
            $table->integer('attachment_total_size')->default(0);
            $table->json('content_flags')->nullable(); // Spam, security flags
            
            // Metadata
            $table->json('metadata')->nullable();
            $table->foreignId('sent_by')->constrained('users');
            
            $table->timestamps();
            
            // Indexes
            $table->index(['status', 'queued_at']);
            $table->index(['account_id', 'status']);
            $table->index(['send_type', 'status']);
            $table->index(['sent_at', 'status']);
            $table->index(['from_address', 'sent_at']);
            $table->index(['next_retry_at', 'retry_count']);
            
            // Foreign key for escalations
            $table->foreign('escalation_id')->references('id')->on('escalations')->onDelete('set null');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('send_logs');
    }
};
