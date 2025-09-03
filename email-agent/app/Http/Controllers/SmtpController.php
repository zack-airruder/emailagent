<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ProcessedEmail;
use App\Services\SmtpService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Routing\Controller as BaseController;

class SmtpController extends BaseController
{
    use AuthorizesRequests;
    protected SmtpService $smtpService;
    
    public function __construct(SmtpService $smtpService)
    {
        $this->middleware('auth:sanctum');
        $this->smtpService = $smtpService;
    }
    
    /**
     * Send a new email
     */
    public function sendEmail(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);
        
        $validated = $request->validate([
            'to' => 'required|array|min:1',
            'to.*.email' => 'required|email',
            'to.*.name' => 'nullable|string|max:255',
            'cc' => 'nullable|array',
            'cc.*.email' => 'required|email',
            'cc.*.name' => 'nullable|string|max:255',
            'bcc' => 'nullable|array',
            'bcc.*.email' => 'required|email',
            'bcc.*.name' => 'nullable|string|max:255',
            'subject' => 'required|string|max:998',
            'body_text' => 'nullable|string',
            'body_html' => 'nullable|string',
            'attachments' => 'nullable|array',
            'attachments.*.path' => 'required|string',
            'attachments.*.name' => 'nullable|string',
            'email_type' => 'nullable|string|in:outbound,marketing,notification'
        ]);
        
        // Ensure at least one body type is provided
        if (empty($validated['body_text']) && empty($validated['body_html'])) {
            throw ValidationException::withMessages([
                'body' => 'Either body_text or body_html must be provided.'
            ]);
        }
        
        try {
            $sendLog = $this->smtpService->sendEmail($account, $validated);
            
            return response()->json([
                'success' => true,
                'message' => 'Email sent successfully',
                'send_log' => [
                    'id' => $sendLog->id,
                    'status' => $sendLog->status,
                    'sent_at' => $sendLog->sent_at,
                    'to_addresses' => $sendLog->to_addresses,
                    'subject' => $sendLog->subject
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send email',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Send a reply to an existing email
     */
    public function sendReply(Request $request, ProcessedEmail $email): JsonResponse
    {
        $this->authorize('update', $email->account);
        
        $validated = $request->validate([
            'reply_content' => 'required|string',
            'signature' => 'nullable|string',
            'include_original' => 'nullable|boolean'
        ]);
        
        try {
            $options = [
                'signature' => $validated['signature'] ?? '',
                'include_original' => $validated['include_original'] ?? true
            ];
            
            $sendLog = $this->smtpService->sendReply(
                $email,
                $validated['reply_content'],
                $options
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Reply sent successfully',
                'send_log' => [
                    'id' => $sendLog->id,
                    'status' => $sendLog->status,
                    'sent_at' => $sendLog->sent_at,
                    'to_addresses' => $sendLog->to_addresses,
                    'subject' => $sendLog->subject
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to send reply',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Forward an existing email
     */
    public function forwardEmail(Request $request, ProcessedEmail $email): JsonResponse
    {
        $this->authorize('update', $email->account);
        
        $validated = $request->validate([
            'recipients' => 'required|array|min:1',
            'recipients.*.email' => 'required|email',
            'recipients.*.name' => 'nullable|string|max:255',
            'message' => 'nullable|string'
        ]);
        
        try {
            $sendLog = $this->smtpService->forwardEmail(
                $email,
                $validated['recipients'],
                $validated['message'] ?? ''
            );
            
            return response()->json([
                'success' => true,
                'message' => 'Email forwarded successfully',
                'send_log' => [
                    'id' => $sendLog->id,
                    'status' => $sendLog->status,
                    'sent_at' => $sendLog->sent_at,
                    'to_addresses' => $sendLog->to_addresses,
                    'subject' => $sendLog->subject
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to forward email',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Test SMTP connection for an account
     */
    public function testConnection(Account $account): JsonResponse
    {
        $this->authorize('update', $account);
        
        try {
            $result = $this->smtpService->testConnection($account);
            
            return response()->json([
                'success' => $result['success'],
                'message' => $result['success'] ? $result['message'] : $result['error'],
                'connection_details' => [
                    'host' => $account->smtp_host,
                    'port' => $account->smtp_port,
                    'encryption' => $account->smtp_encryption,
                    'username' => $account->smtp_username
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Connection test failed',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get sending statistics for an account
     */
    public function getStats(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);
        
        $validated = $request->validate([
            'period' => 'nullable|string|in:24h,7d,30d'
        ]);
        
        try {
            $stats = $this->smtpService->getSendingStats(
                $account,
                $validated['period'] ?? '30d'
            );
            
            return response()->json([
                'success' => true,
                'stats' => $stats,
                'account' => [
                    'id' => $account->id,
                    'email_address' => $account->email_address,
                    'display_name' => $account->display_name
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve statistics',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get send logs for an account
     */
    public function getSendLogs(Request $request, Account $account): JsonResponse
    {
        $this->authorize('view', $account);
        
        $validated = $request->validate([
            'status' => 'nullable|string|in:pending,sent,failed',
            'email_type' => 'nullable|string|in:outbound,reply,forward,marketing,notification',
            'per_page' => 'nullable|integer|min:1|max:100',
            'page' => 'nullable|integer|min:1'
        ]);
        
        try {
            $query = $account->sendLogs()
                ->with('processedEmail')
                ->orderBy('created_at', 'desc');
            
            if (!empty($validated['status'])) {
                $query->where('status', $validated['status']);
            }
            
            if (!empty($validated['email_type'])) {
                $query->where('email_type', $validated['email_type']);
            }
            
            $sendLogs = $query->paginate(
                $validated['per_page'] ?? 20,
                ['*'],
                'page',
                $validated['page'] ?? 1
            );
            
            return response()->json([
                'success' => true,
                'send_logs' => $sendLogs->items(),
                'pagination' => [
                    'current_page' => $sendLogs->currentPage(),
                    'per_page' => $sendLogs->perPage(),
                    'total' => $sendLogs->total(),
                    'last_page' => $sendLogs->lastPage()
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retrieve send logs',
                'error' => $e->getMessage()
            ], 500);
        }
    }
    
    /**
     * Get a specific send log
     */
    public function getSendLog(Account $account, int $sendLogId): JsonResponse
    {
        $this->authorize('view', $account);
        
        try {
            $sendLog = $account->sendLogs()
                ->with('processedEmail')
                ->findOrFail($sendLogId);
            
            return response()->json([
                'success' => true,
                'send_log' => $sendLog
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Send log not found',
                'error' => $e->getMessage()
            ], 404);
        }
    }
    
    /**
     * Retry a failed send
     */
    public function retrySend(Account $account, int $sendLogId): JsonResponse
    {
        $this->authorize('update', $account);
        
        try {
            $sendLog = $account->sendLogs()
                ->where('status', 'failed')
                ->findOrFail($sendLogId);
            
            // Reconstruct email data from send log
            $emailData = [
                'to' => array_map(fn($email) => ['email' => $email], $sendLog->to_addresses),
                'cc' => array_map(fn($email) => ['email' => $email], $sendLog->cc_addresses ?? []),
                'bcc' => array_map(fn($email) => ['email' => $email], $sendLog->bcc_addresses ?? []),
                'subject' => $sendLog->subject,
                'body_text' => $sendLog->body_text,
                'body_html' => $sendLog->body_html,
                'email_type' => $sendLog->email_type,
                'processed_email_id' => $sendLog->processed_email_id
            ];
            
            $newSendLog = $this->smtpService->sendEmail($account, $emailData);
            
            return response()->json([
                'success' => true,
                'message' => 'Email retry initiated',
                'original_send_log_id' => $sendLog->id,
                'new_send_log' => [
                    'id' => $newSendLog->id,
                    'status' => $newSendLog->status,
                    'sent_at' => $newSendLog->sent_at
                ]
            ]);
            
        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'message' => 'Failed to retry send',
                'error' => $e->getMessage()
            ], 500);
        }
    }
}
