<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Factories\HasFactory;

class Account extends Model
{
    use HasFactory, HasUuids;

    protected $fillable = [
        'user_id',
        'name',
        'email_address',
        'status',
        'imap_host',
        'imap_port',
        'imap_encryption',
        'imap_username',
        'imap_password',
        'smtp_host',
        'smtp_port',
        'smtp_encryption',
        'smtp_username',
        'smtp_password',
        'auto_reply_enabled',
        'processing_enabled',
        'max_emails_per_hour',
        'timezone',
        'language',
        'signature',
        'settings',
        'health_status',
        'last_health_check',
        'last_imap_sync',
        'last_error',
        'metadata'
    ];

    protected $casts = [
        'auto_reply_enabled' => 'boolean',
        'processing_enabled' => 'boolean',
        'max_emails_per_hour' => 'integer',
        'settings' => 'array',
        'last_health_check' => 'datetime',
        'last_imap_sync' => 'datetime',
        'metadata' => 'array'
    ];

    protected $hidden = [
        'imap_password',
        'smtp_password'
    ];

    // Relationships
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rules(): HasMany
    {
        return $this->hasMany(Rule::class);
    }

    public function processedEmails(): HasMany
    {
        return $this->hasMany(ProcessedEmail::class);
    }

    public function escalations(): HasMany
    {
        return $this->hasMany(Escalation::class);
    }

    public function sendLogs(): HasMany
    {
        return $this->hasMany(SendLog::class);
    }

    public function llmRequests(): HasMany
    {
        return $this->hasMany(LlmRequest::class);
    }

    // Scopes
    public function scopeActive($query)
    {
        return $query->where('status', 'active');
    }

    public function scopeHealthy($query)
    {
        return $query->where('health_status', 'healthy');
    }

    // Helper methods
    public function isActive(): bool
    {
        return $this->status === 'active';
    }

    public function isHealthy(): bool
    {
        return $this->health_status === 'healthy';
    }

    public function canProcessEmails(): bool
    {
        return $this->isActive() && $this->processing_enabled && $this->isHealthy();
    }

    public function getImapConfig(): array
    {
        return [
            'host' => $this->imap_host,
            'port' => $this->imap_port,
            'encryption' => $this->imap_encryption,
            'username' => $this->imap_username,
            'password' => decrypt($this->imap_password),
            'validate_cert' => true
        ];
    }

    public function getSmtpConfig(): array
    {
        return [
            'host' => $this->smtp_host,
            'port' => $this->smtp_port,
            'encryption' => $this->smtp_encryption,
            'username' => $this->smtp_username,
            'password' => decrypt($this->smtp_password)
        ];
    }
}
