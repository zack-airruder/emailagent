<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\ProcessedEmail;
use App\Services\LlmService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Gate;

class LlmController extends Controller
{
    protected LlmService $llmService;

    public function __construct(LlmService $llmService)
    {
        $this->middleware('auth:sanctum');
        $this->llmService = $llmService;
    }

    /**
     * Classify an email using LLM
     */
    public function classifyEmail(Request $request, Account $account, ProcessedEmail $email): JsonResponse
    {
        Gate::authorize('view', $account);
        
        if ($email->account_id !== $account->id) {
            return response()->json(['error' => 'Email not found'], 404);
        }

        try {
            $classification = $this->llmService->classifyEmail($email, $account);
            
            // Update email with classification data
            $email->update([
                'category' => $classification['category'] ?? null,
                'priority' => $classification['priority'] ?? null,
                'tags' => $classification['tags'] ?? [],
                'metadata' => array_merge($email->metadata ?? [], [
                    'llm_classification' => $classification,
                    'classified_at' => now()->toISOString()
                ])
            ]);

            return response()->json([
                'success' => true,
                'classification' => $classification,
                'email' => $email->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Classification failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Generate a response to an email using LLM
     */
    public function generateResponse(Request $request, Account $account, ProcessedEmail $email): JsonResponse
    {
        Gate::authorize('view', $account);
        
        if ($email->account_id !== $account->id) {
            return response()->json(['error' => 'Email not found'], 404);
        }

        $request->validate([
            'context' => 'sometimes|array',
            'tone' => 'sometimes|string|in:professional,friendly,formal,casual',
            'include_signature' => 'sometimes|boolean'
        ]);

        try {
            $context = $request->input('context', []);
            
            // Add tone preference to context if provided
            if ($request->has('tone')) {
                $context['tone'] = $request->input('tone');
            }
            
            $response = $this->llmService->generateResponse($email, $account, $context);
            
            if (!$response) {
                return response()->json([
                    'success' => false,
                    'error' => 'Failed to generate response'
                ], 500);
            }

            return response()->json([
                'success' => true,
                'response' => $response,
                'context' => $context
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Response generation failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Analyze email sentiment using LLM
     */
    public function analyzeSentiment(Request $request, Account $account, ProcessedEmail $email): JsonResponse
    {
        Gate::authorize('view', $account);
        
        if ($email->account_id !== $account->id) {
            return response()->json(['error' => 'Email not found'], 404);
        }

        try {
            $sentiment = $this->llmService->analyzeSentiment($email, $account);
            
            // Update email with sentiment data
            $email->update([
                'metadata' => array_merge($email->metadata ?? [], [
                    'sentiment_analysis' => $sentiment,
                    'sentiment_analyzed_at' => now()->toISOString()
                ])
            ]);

            return response()->json([
                'success' => true,
                'sentiment' => $sentiment,
                'email' => $email->fresh()
            ]);

        } catch (\Exception $e) {
            return response()->json([
                'success' => false,
                'error' => 'Sentiment analysis failed: ' . $e->getMessage()
            ], 500);
        }
    }

    /**
     * Get LLM usage statistics for an account
     */
    public function getUsageStats(Request $request, Account $account): JsonResponse
    {
        Gate::authorize('view', $account);

        $request->validate([
            'period' => 'sometimes|string|in:24h,7d,30d'
        ]);

        $period = $request->input('period', '30d');
        $stats = $this->llmService->getUsageStats($account, $period);

        return response()->json([
            'success' => true,
            'period' => $period,
            'stats' => $stats
        ]);
    }

    /**
     * Batch classify multiple emails
     */
    public function batchClassify(Request $request, Account $account): JsonResponse
    {
        Gate::authorize('view', $account);

        $request->validate([
            'email_ids' => 'required|array|max:10',
            'email_ids.*' => 'integer|exists:processed_emails,id'
        ]);

        $emailIds = $request->input('email_ids');
        $emails = ProcessedEmail::where('account_id', $account->id)
            ->whereIn('id', $emailIds)
            ->get();

        if ($emails->count() !== count($emailIds)) {
            return response()->json(['error' => 'Some emails not found'], 404);
        }

        $results = [];
        $errors = [];

        foreach ($emails as $email) {
            try {
                $classification = $this->llmService->classifyEmail($email, $account);
                
                // Update email with classification data
                $email->update([
                    'category' => $classification['category'] ?? null,
                    'priority' => $classification['priority'] ?? null,
                    'tags' => $classification['tags'] ?? [],
                    'metadata' => array_merge($email->metadata ?? [], [
                        'llm_classification' => $classification,
                        'classified_at' => now()->toISOString()
                    ])
                ]);

                $results[] = [
                    'email_id' => $email->id,
                    'success' => true,
                    'classification' => $classification
                ];

            } catch (\Exception $e) {
                $errors[] = [
                    'email_id' => $email->id,
                    'success' => false,
                    'error' => $e->getMessage()
                ];
            }
        }

        return response()->json([
            'success' => empty($errors),
            'results' => $results,
            'errors' => $errors,
            'summary' => [
                'total' => count($emailIds),
                'successful' => count($results),
                'failed' => count($errors)
            ]
        ]);
    }

    /**
     * Get LLM request history for an account
     */
    public function getRequestHistory(Request $request, Account $account): JsonResponse
    {
        Gate::authorize('view', $account);

        $request->validate([
            'type' => 'sometimes|string|in:classification,response_generation,sentiment_analysis',
            'status' => 'sometimes|string|in:pending,completed,failed',
            'limit' => 'sometimes|integer|min:1|max:100'
        ]);

        $query = $account->llmRequests()
            ->with('processedEmail:id,subject,from_address')
            ->orderBy('created_at', 'desc');

        if ($request->has('type')) {
            $query->where('request_type', $request->input('type'));
        }

        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $limit = $request->input('limit', 50);
        $requests = $query->limit($limit)->get();

        return response()->json([
            'success' => true,
            'requests' => $requests,
            'filters' => $request->only(['type', 'status']),
            'limit' => $limit
        ]);
    }
}
