<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Services\ImapService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;

class AccountController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Display a listing of user's email accounts
     */
    public function index(): JsonResponse
    {
        $accounts = Auth::user()->accounts()->with(['rules', 'processedEmails'])->get();
        
        return response()->json([
            'success' => true,
            'data' => $accounts
        ]);
    }

    /**
     * Store a new email account
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email_address' => 'required|email|unique:accounts,email_address',
            'imap_host' => 'required|string|max:255',
            'imap_port' => 'required|integer|min:1|max:65535',
            'imap_encryption' => 'required|in:ssl,tls,none',
            'imap_username' => 'required|string|max:255',
            'imap_password' => 'required|string',
            'smtp_host' => 'required|string|max:255',
            'smtp_port' => 'required|integer|min:1|max:65535',
            'smtp_encryption' => 'required|in:ssl,tls,none',
            'smtp_username' => 'required|string|max:255',
            'smtp_password' => 'required|string',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'signature' => 'nullable|string|max:1000',
            'auto_reply_enabled' => 'boolean',
            'processing_enabled' => 'boolean',
            'max_emails_per_hour' => 'nullable|integer|min:1|max:1000'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        $data['user_id'] = Auth::id();
        $data['status'] = 'inactive'; // Start as inactive until tested
        $data['health_status'] = 'unknown';
        
        // Encrypt passwords
        $data['imap_password'] = encrypt($data['imap_password']);
        $data['smtp_password'] = encrypt($data['smtp_password']);

        $account = Account::create($data);

        return response()->json([
            'success' => true,
            'message' => 'Email account created successfully',
            'data' => $account
        ], 201);
    }

    /**
     * Display the specified account
     */
    public function show(Account $account): JsonResponse
    {
        $this->authorize('view', $account);
        
        $account->load(['rules', 'processedEmails' => function($query) {
            $query->latest()->limit(10);
        }]);

        return response()->json([
            'success' => true,
            'data' => $account
        ]);
    }

    /**
     * Update the specified account
     */
    public function update(Request $request, Account $account): JsonResponse
    {
        $this->authorize('update', $account);

        $validator = Validator::make($request->all(), [
            'name' => 'sometimes|required|string|max:255',
            'email_address' => [
                'sometimes',
                'required',
                'email',
                Rule::unique('accounts')->ignore($account->id)
            ],
            'imap_host' => 'sometimes|required|string|max:255',
            'imap_port' => 'sometimes|required|integer|min:1|max:65535',
            'imap_encryption' => 'sometimes|required|in:ssl,tls,none',
            'imap_username' => 'sometimes|required|string|max:255',
            'imap_password' => 'sometimes|required|string',
            'smtp_host' => 'sometimes|required|string|max:255',
            'smtp_port' => 'sometimes|required|integer|min:1|max:65535',
            'smtp_encryption' => 'sometimes|required|in:ssl,tls,none',
            'smtp_username' => 'sometimes|required|string|max:255',
            'smtp_password' => 'sometimes|required|string',
            'timezone' => 'nullable|string|max:50',
            'language' => 'nullable|string|max:10',
            'signature' => 'nullable|string|max:1000',
            'auto_reply_enabled' => 'boolean',
            'processing_enabled' => 'boolean',
            'max_emails_per_hour' => 'nullable|integer|min:1|max:1000',
            'status' => 'sometimes|in:active,inactive,suspended'
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'errors' => $validator->errors()
            ], 422);
        }

        $data = $validator->validated();
        
        // Encrypt passwords if provided
        if (isset($data['imap_password'])) {
            $data['imap_password'] = encrypt($data['imap_password']);
        }
        if (isset($data['smtp_password'])) {
            $data['smtp_password'] = encrypt($data['smtp_password']);
        }

        $account->update($data);

        return response()->json([
            'success' => true,
            'message' => 'Account updated successfully',
            'data' => $account->fresh()
        ]);
    }

    /**
     * Remove the specified account
     */
    public function destroy(Account $account): JsonResponse
    {
        $this->authorize('delete', $account);
        
        $account->delete();

        return response()->json([
            'success' => true,
            'message' => 'Account deleted successfully'
        ]);
    }

    /**
     * Test account connection
     */
    public function testConnection(Account $account, ImapService $imapService): JsonResponse
    {
        $this->authorize('update', $account);
        
        $result = $imapService->testConnection($account);
        
        return response()->json($result);
    }
}
