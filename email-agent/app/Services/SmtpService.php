<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProcessedEmail;
use App\Models\SendLog;
use Illuminate\Mail\Mailable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception as PHPMailerException;

class SmtpService
{
    /**
     * Send an email using account's SMTP configuration
     */
    public function sendEmail(Account $account, array $emailData): SendLog
    {
        $sendLog = $this->createSendLog($account, $emailData);
        
        try {
            $mail = $this->createMailer($account);
            
            // Configure email details
            $this->configureEmail($mail, $emailData);
            
            // Send the email
            $success = $mail->send();
            
            if ($success) {
                $this->updateSendLog($sendLog, [
                    'status' => 'sent',
                    'sent_at' => now(),
                    'smtp_response' => 'Email sent successfully',
                    'delivery_status' => 'delivered'
                ]);
            } else {
                $this->updateSendLog($sendLog, [
                    'status' => 'failed',
                    'error_message' => $mail->ErrorInfo,
                    'smtp_response' => $mail->ErrorInfo
                ]);
            }
            
        } catch (PHPMailerException $e) {
            $this->updateSendLog($sendLog, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'smtp_response' => $e->getMessage()
            ]);
            
            Log::error('SMTP sending failed', [
                'account_id' => $account->id,
                'send_log_id' => $sendLog->id,
                'error' => $e->getMessage()
            ]);
        }
        
        return $sendLog->fresh();
    }
    
    /**
     * Send a reply to an existing email
     */
    public function sendReply(ProcessedEmail $originalEmail, string $replyContent, array $options = []): SendLog
    {
        $account = $originalEmail->account;
        
        $emailData = [
            'to' => [['email' => $originalEmail->from_address, 'name' => $originalEmail->from_name]],
            'subject' => 'Re: ' . $originalEmail->subject,
            'body_html' => $this->formatReplyBody($replyContent, $originalEmail, $options),
            'body_text' => strip_tags($replyContent),
            'reply_to_message_id' => $originalEmail->message_id,
            'in_reply_to' => $originalEmail->message_id,
            'references' => $originalEmail->message_id,
            'processed_email_id' => $originalEmail->id,
            'email_type' => 'reply'
        ];
        
        return $this->sendEmail($account, $emailData);
    }
    
    /**
     * Send a forward of an existing email
     */
    public function forwardEmail(ProcessedEmail $originalEmail, array $recipients, string $message = ''): SendLog
    {
        $account = $originalEmail->account;
        
        $emailData = [
            'to' => $recipients,
            'subject' => 'Fwd: ' . $originalEmail->subject,
            'body_html' => $this->formatForwardBody($message, $originalEmail),
            'body_text' => $this->formatForwardBodyText($message, $originalEmail),
            'processed_email_id' => $originalEmail->id,
            'email_type' => 'forward'
        ];
        
        return $this->sendEmail($account, $emailData);
    }
    
    /**
     * Test SMTP connection for an account
     */
    public function testConnection(Account $account): array
    {
        try {
            $mail = $this->createMailer($account);
            
            // Test connection without sending
            $mail->smtpConnect();
            $mail->smtpClose();
            
            return [
                'success' => true,
                'message' => 'SMTP connection successful'
            ];
            
        } catch (PHPMailerException $e) {
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Create PHPMailer instance with account configuration
     */
    protected function createMailer(Account $account): PHPMailer
    {
        $mail = new PHPMailer(true);
        
        // Server settings
        $mail->isSMTP();
        $mail->Host = $account->smtp_host;
        $mail->SMTPAuth = true;
        $mail->Username = $account->smtp_username;
        $mail->Password = $account->smtp_password;
        $mail->SMTPSecure = $account->smtp_encryption === 'tls' ? PHPMailer::ENCRYPTION_STARTTLS : PHPMailer::ENCRYPTION_SMTPS;
        $mail->Port = $account->smtp_port;
        
        // Set from address
        $mail->setFrom($account->email_address, $account->display_name ?? $account->email_address);
        
        // Enable debug output if needed
        if (config('app.debug')) {
            $mail->SMTPDebug = SMTP::DEBUG_SERVER;
        }
        
        return $mail;
    }
    
    /**
     * Configure email details in PHPMailer
     */
    protected function configureEmail(PHPMailer $mail, array $emailData): void
    {
        // Recipients
        foreach ($emailData['to'] as $recipient) {
            $mail->addAddress($recipient['email'], $recipient['name'] ?? '');
        }
        
        // CC recipients
        if (!empty($emailData['cc'])) {
            foreach ($emailData['cc'] as $cc) {
                $mail->addCC($cc['email'], $cc['name'] ?? '');
            }
        }
        
        // BCC recipients
        if (!empty($emailData['bcc'])) {
            foreach ($emailData['bcc'] as $bcc) {
                $mail->addBCC($bcc['email'], $bcc['name'] ?? '');
            }
        }
        
        // Subject and body
        $mail->Subject = $emailData['subject'];
        
        if (!empty($emailData['body_html'])) {
            $mail->isHTML(true);
            $mail->Body = $emailData['body_html'];
            $mail->AltBody = $emailData['body_text'] ?? strip_tags($emailData['body_html']);
        } else {
            $mail->isHTML(false);
            $mail->Body = $emailData['body_text'] ?? '';
        }
        
        // Headers for threading
        if (!empty($emailData['in_reply_to'])) {
            $mail->addCustomHeader('In-Reply-To', $emailData['in_reply_to']);
        }
        
        if (!empty($emailData['references'])) {
            $mail->addCustomHeader('References', $emailData['references']);
        }
        
        // Attachments
        if (!empty($emailData['attachments'])) {
            foreach ($emailData['attachments'] as $attachment) {
                if (file_exists($attachment['path'])) {
                    $mail->addAttachment($attachment['path'], $attachment['name'] ?? '');
                }
            }
        }
    }
    
    /**
     * Create send log record
     */
    protected function createSendLog(Account $account, array $emailData): SendLog
    {
        return SendLog::create([
            'account_id' => $account->id,
            'processed_email_id' => $emailData['processed_email_id'] ?? null,
            'to_addresses' => array_column($emailData['to'], 'email'),
            'cc_addresses' => !empty($emailData['cc']) ? array_column($emailData['cc'], 'email') : [],
            'bcc_addresses' => !empty($emailData['bcc']) ? array_column($emailData['bcc'], 'email') : [],
            'subject' => $emailData['subject'],
            'body_text' => $emailData['body_text'] ?? '',
            'body_html' => $emailData['body_html'] ?? '',
            'email_type' => $emailData['email_type'] ?? 'outbound',
            'status' => 'pending',
            'smtp_host' => $account->smtp_host,
            'smtp_port' => $account->smtp_port,
            'smtp_encryption' => $account->smtp_encryption,
            'metadata' => [
                'created_at' => now()->toISOString(),
                'user_agent' => 'EmailAgent/1.0',
                'send_configuration' => [
                    'host' => $account->smtp_host,
                    'port' => $account->smtp_port,
                    'encryption' => $account->smtp_encryption
                ]
            ]
        ]);
    }
    
    /**
     * Update send log with results
     */
    protected function updateSendLog(SendLog $sendLog, array $data): void
    {
        $sendLog->update(array_merge($data, [
            'updated_at' => now()
        ]));
    }
    
    /**
     * Format reply body with original email context
     */
    protected function formatReplyBody(string $replyContent, ProcessedEmail $originalEmail, array $options = []): string
    {
        $signature = $options['signature'] ?? '';
        $includeOriginal = $options['include_original'] ?? true;
        
        $html = '<div>' . nl2br(htmlspecialchars($replyContent)) . '</div>';
        
        if ($signature) {
            $html .= '<br><div>' . nl2br(htmlspecialchars($signature)) . '</div>';
        }
        
        if ($includeOriginal) {
            $html .= '<br><hr><div>';
            $html .= '<strong>From:</strong> ' . htmlspecialchars($originalEmail->from_address) . '<br>';
            $html .= '<strong>Date:</strong> ' . $originalEmail->received_at->format('M j, Y, g:i A') . '<br>';
            $html .= '<strong>Subject:</strong> ' . htmlspecialchars($originalEmail->subject) . '<br><br>';
            $html .= $originalEmail->body_html ?: nl2br(htmlspecialchars($originalEmail->body_text));
            $html .= '</div>';
        }
        
        return $html;
    }
    
    /**
     * Format forward body with original email
     */
    protected function formatForwardBody(string $message, ProcessedEmail $originalEmail): string
    {
        $html = '';
        
        if ($message) {
            $html .= '<div>' . nl2br(htmlspecialchars($message)) . '</div><br>';
        }
        
        $html .= '<div>---------- Forwarded message ---------</div>';
        $html .= '<strong>From:</strong> ' . htmlspecialchars($originalEmail->from_address) . '<br>';
        $html .= '<strong>Date:</strong> ' . $originalEmail->received_at->format('M j, Y, g:i A') . '<br>';
        $html .= '<strong>Subject:</strong> ' . htmlspecialchars($originalEmail->subject) . '<br>';
        
        if ($originalEmail->to_addresses) {
            $html .= '<strong>To:</strong> ' . implode(', ', $originalEmail->to_addresses) . '<br>';
        }
        
        $html .= '<br>' . ($originalEmail->body_html ?: nl2br(htmlspecialchars($originalEmail->body_text)));
        
        return $html;
    }
    
    /**
     * Format forward body as plain text
     */
    protected function formatForwardBodyText(string $message, ProcessedEmail $originalEmail): string
    {
        $text = '';
        
        if ($message) {
            $text .= $message . "\n\n";
        }
        
        $text .= "---------- Forwarded message ---------\n";
        $text .= "From: {$originalEmail->from_address}\n";
        $text .= "Date: " . $originalEmail->received_at->format('M j, Y, g:i A') . "\n";
        $text .= "Subject: {$originalEmail->subject}\n";
        
        if ($originalEmail->to_addresses) {
            $text .= "To: " . implode(', ', $originalEmail->to_addresses) . "\n";
        }
        
        $text .= "\n" . $originalEmail->body_text;
        
        return $text;
    }
    
    /**
     * Get sending statistics for an account
     */
    public function getSendingStats(Account $account, string $period = '30d'): array
    {
        $startDate = match($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subMonth()
        };
        
        $logs = SendLog::where('account_id', $account->id)
            ->where('created_at', '>=', $startDate)
            ->get();
        
        return [
            'total_sent' => $logs->count(),
            'successful' => $logs->where('status', 'sent')->count(),
            'failed' => $logs->where('status', 'failed')->count(),
            'pending' => $logs->where('status', 'pending')->count(),
            'by_type' => $logs->groupBy('email_type')->map->count(),
            'by_status' => $logs->groupBy('status')->map->count(),
            'recent_errors' => $logs->where('status', 'failed')
                ->sortByDesc('created_at')
                ->take(5)
                ->map(function ($log) {
                    return [
                        'id' => $log->id,
                        'subject' => $log->subject,
                        'error' => $log->error_message,
                        'created_at' => $log->created_at
                    ];
                })
                ->values()
        ];
    }
}
