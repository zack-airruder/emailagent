<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ProcessedEmail;
use App\Services\ImapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;

class EmailController extends Controller
{
    protected ImapService $imapService;

    public function __construct(ImapService $imapService)
    {
        $this->imapService = $imapService;
    }

    /**
     * Fetch emails for a specific account
     */
    public function fetchEmails(Request $request, Account $account): JsonResponse
    {
        // Check if user owns this account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $limit = $request->input('limit', 50);
        $result = $this->imapService->fetchEmails($account, $limit);

        return response()->json($result);
    }

    /**
     * Get processed emails for an account
     */
    public function getEmails(Request $request, Account $account): JsonResponse
    {
        // Check if user owns this account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = ProcessedEmail::where('account_id', $account->id)
            ->orderBy('received_at', 'desc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        // Filter by processing status if provided
        if ($request->has('processing_status')) {
            $query->where('processing_status', $request->input('processing_status'));
        }

        // Search in subject or from address
        if ($request->has('search')) {
            $search = $request->input('search');
            $query->where(function ($q) use ($search) {
                $q->where('subject', 'like', "%{$search}%")
                  ->orWhere('from_address', 'like', "%{$search}%")
                  ->orWhere('from_name', 'like', "%{$search}%");
            });
        }

        $emails = $query->paginate($request->input('per_page', 20));

        return response()->json($emails);
    }

    /**
     * Get a specific email
     */
    public function getEmail(Account $account, ProcessedEmail $email): JsonResponse
    {
        // Check if user owns this account and email belongs to account
        if ($account->user_id !== Auth::id() || $email->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($email);
    }

    /**
     * Mark email as read/unread
     */
    public function markAsRead(Request $request, Account $account, ProcessedEmail $email): JsonResponse
    {
        // Check if user owns this account and email belongs to account
        if ($account->user_id !== Auth::id() || $email->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $email->update([
            'status' => $request->input('read', true) ? 'read' : 'unread'
        ]);

        return response()->json([
            'success' => true,
            'message' => 'Email status updated',
            'email' => $email
        ]);
    }

    /**
     * Get email statistics for an account
     */
    public function getStats(Account $account): JsonResponse
    {
        // Check if user owns this account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'total_emails' => ProcessedEmail::where('account_id', $account->id)->count(),
            'unread_emails' => ProcessedEmail::where('account_id', $account->id)
                ->where('status', 'unread')->count(),
            'pending_processing' => ProcessedEmail::where('account_id', $account->id)
                ->where('processing_status', 'pending')->count(),
            'processed_today' => ProcessedEmail::where('account_id', $account->id)
                ->whereDate('created_at', today())->count(),
            'last_sync' => $account->last_imap_sync,
            'health_status' => $account->health_status
        ];

        return response()->json($stats);
    }
}
