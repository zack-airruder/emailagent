<?php

namespace App\Services;

use App\Models\Account;
use App\Models\LlmRequest;
use App\Models\ProcessedEmail;
use Illuminate\Support\Facades\Log;
use OpenAI;

class LlmService
{
    /**
     * Classify an email using LLM
     */
    public function classifyEmail(ProcessedEmail $email, Account $account): array
    {
        $prompt = $this->buildClassificationPrompt($email);
        
        $request = $this->createLlmRequest([
            'account_id' => $account->id,
            'processed_email_id' => $email->id,
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'request_type' => 'classification',
            'prompt' => $prompt,
            'parameters' => [
                'temperature' => 0.3,
                'max_tokens' => 500
            ]
        ]);

        try {
            $client = OpenAI::client(config('openai.api_key'));
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are an email classification assistant. Analyze emails and provide structured classification data in JSON format.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.3,
                'max_tokens' => 500,
            ]);

            $content = $response->choices[0]->message->content;
            $classification = json_decode($content, true);

            $this->updateLlmRequest($request, [
                'status' => 'completed',
                'response' => $content,
                'http_status' => 200,
                'completion_tokens' => $response->usage->completionTokens,
                'prompt_tokens' => $response->usage->promptTokens,
                'total_tokens' => $response->usage->totalTokens,
                'cost_estimate' => $this->calculateCost('gpt-3.5-turbo', $response->usage->totalTokens),
                'response_time_ms' => $request->created_at->diffInMilliseconds(now())
            ]);

            return $classification ?: [];

        } catch (\Exception $e) {
            $this->updateLlmRequest($request, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'http_status' => $e->getCode() ?: 500
            ]);

            Log::error('LLM classification failed', [
                'email_id' => $email->id,
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);

            return [];
        }
    }

    /**
     * Generate a response to an email using LLM
     */
    public function generateResponse(ProcessedEmail $email, Account $account, array $context = []): ?string
    {
        $prompt = $this->buildResponsePrompt($email, $account, $context);
        
        $request = $this->createLlmRequest([
            'account_id' => $account->id,
            'processed_email_id' => $email->id,
            'provider' => 'openai',
            'model' => 'gpt-4',
            'request_type' => 'response_generation',
            'prompt' => $prompt,
            'parameters' => [
                'temperature' => 0.7,
                'max_tokens' => 1000
            ],
            'context' => $context
        ]);

        try {
            $client = OpenAI::client(config('openai.api_key'));
            $response = $client->chat()->create([
                'model' => 'gpt-4',
                'messages' => [
                    ['role' => 'system', 'content' => $this->getSystemPrompt($account)],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.7,
                'max_tokens' => 1000,
            ]);

            $content = $response->choices[0]->message->content;

            $this->updateLlmRequest($request, [
                'status' => 'completed',
                'response' => $content,
                'http_status' => 200,
                'completion_tokens' => $response->usage->completionTokens,
                'prompt_tokens' => $response->usage->promptTokens,
                'total_tokens' => $response->usage->totalTokens,
                'cost_estimate' => $this->calculateCost('gpt-4', $response->usage->totalTokens),
                'response_time_ms' => $request->created_at->diffInMilliseconds(now())
            ]);

            return $content;

        } catch (\Exception $e) {
            $this->updateLlmRequest($request, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'http_status' => $e->getCode() ?: 500
            ]);

            Log::error('LLM response generation failed', [
                'email_id' => $email->id,
                'account_id' => $account->id,
                'error' => $e->getMessage()
            ]);

            return null;
        }
    }

    /**
     * Analyze email sentiment using LLM
     */
    public function analyzeSentiment(ProcessedEmail $email, Account $account): array
    {
        $prompt = "Analyze the sentiment of this email and provide a JSON response with 'sentiment' (positive/negative/neutral), 'confidence' (0-1), and 'reasoning':\n\n" . 
                  "Subject: {$email->subject}\n" .
                  "Body: {$email->body_text}";
        
        $request = $this->createLlmRequest([
            'account_id' => $account->id,
            'processed_email_id' => $email->id,
            'provider' => 'openai',
            'model' => 'gpt-3.5-turbo',
            'request_type' => 'sentiment_analysis',
            'prompt' => $prompt,
            'parameters' => [
                'temperature' => 0.2,
                'max_tokens' => 300
            ]
        ]);

        try {
            $client = OpenAI::client(config('openai.api_key'));
            $response = $client->chat()->create([
                'model' => 'gpt-3.5-turbo',
                'messages' => [
                    ['role' => 'system', 'content' => 'You are a sentiment analysis assistant. Analyze emails and provide structured sentiment data in JSON format.'],
                    ['role' => 'user', 'content' => $prompt]
                ],
                'temperature' => 0.2,
                'max_tokens' => 300,
            ]);

            $content = $response->choices[0]->message->content;
            $sentiment = json_decode($content, true);

            $this->updateLlmRequest($request, [
                'status' => 'completed',
                'response' => $content,
                'http_status' => 200,
                'completion_tokens' => $response->usage->completionTokens,
                'prompt_tokens' => $response->usage->promptTokens,
                'total_tokens' => $response->usage->totalTokens,
                'cost_estimate' => $this->calculateCost('gpt-3.5-turbo', $response->usage->totalTokens),
                'response_time_ms' => $request->created_at->diffInMilliseconds(now())
            ]);

            return $sentiment ?: [];

        } catch (\Exception $e) {
            $this->updateLlmRequest($request, [
                'status' => 'failed',
                'error_message' => $e->getMessage(),
                'http_status' => $e->getCode() ?: 500
            ]);

            return [];
        }
    }

    /**
     * Build classification prompt
     */
    protected function buildClassificationPrompt(ProcessedEmail $email): string
    {
        return "Classify this email and provide a JSON response with the following fields:\n" .
               "- category: (inquiry, complaint, support, sales, spam, other)\n" .
               "- priority: (low, medium, high, urgent)\n" .
               "- requires_response: (true/false)\n" .
               "- estimated_response_time: (immediate, within_hour, within_day, within_week)\n" .
               "- tags: (array of relevant tags)\n" .
               "- confidence: (0-1 confidence score)\n\n" .
               "Email Details:\n" .
               "From: {$email->from_address}\n" .
               "Subject: {$email->subject}\n" .
               "Body: {$email->body_text}";
    }

    /**
     * Build response generation prompt
     */
    protected function buildResponsePrompt(ProcessedEmail $email, Account $account, array $context): string
    {
        $contextStr = !empty($context) ? "\nContext: " . json_encode($context) : '';
        
        return "Generate a professional email response to the following email. " .
               "Keep the tone appropriate for the business context.\n\n" .
               "Original Email:\n" .
               "From: {$email->from_address}\n" .
               "Subject: {$email->subject}\n" .
               "Body: {$email->body_text}" .
               $contextStr;
    }

    /**
     * Get system prompt for the account
     */
    protected function getSystemPrompt(Account $account): string
    {
        $settings = $account->settings ?? [];
        $tone = $settings['response_tone'] ?? 'professional';
        $signature = $settings['email_signature'] ?? '';
        
        return "You are an AI email assistant for {$account->email_address}. " .
               "Respond in a {$tone} tone. " .
               "Always be helpful, accurate, and concise. " .
               ($signature ? "Include this signature: {$signature}" : '');
    }

    /**
     * Create LLM request record
     */
    protected function createLlmRequest(array $data): LlmRequest
    {
        return LlmRequest::create(array_merge($data, [
            'status' => 'pending',
            'created_at' => now()
        ]));
    }

    /**
     * Update LLM request with response data
     */
    protected function updateLlmRequest(LlmRequest $request, array $data): void
    {
        $request->update(array_merge($data, [
            'completed_at' => now()
        ]));
    }

    /**
     * Calculate estimated cost for LLM usage
     */
    protected function calculateCost(string $model, int $totalTokens): float
    {
        // Approximate pricing (as of 2024)
        $pricing = [
            'gpt-3.5-turbo' => 0.002 / 1000, // $0.002 per 1K tokens
            'gpt-4' => 0.03 / 1000, // $0.03 per 1K tokens
        ];

        return ($pricing[$model] ?? 0) * $totalTokens;
    }

    /**
     * Get LLM usage statistics for an account
     */
    public function getUsageStats(Account $account, string $period = '30d'): array
    {
        $startDate = match($period) {
            '24h' => now()->subDay(),
            '7d' => now()->subWeek(),
            '30d' => now()->subMonth(),
            default => now()->subMonth()
        };

        $requests = LlmRequest::where('account_id', $account->id)
            ->where('created_at', '>=', $startDate)
            ->get();

        return [
            'total_requests' => $requests->count(),
            'successful_requests' => $requests->where('status', 'completed')->count(),
            'failed_requests' => $requests->where('status', 'failed')->count(),
            'total_tokens' => $requests->sum('total_tokens'),
            'total_cost' => $requests->sum('cost_estimate'),
            'average_response_time' => $requests->where('status', 'completed')->avg('response_time_ms'),
            'requests_by_type' => $requests->groupBy('request_type')->map->count(),
            'requests_by_model' => $requests->groupBy('model')->map->count()
        ];
    }
}
