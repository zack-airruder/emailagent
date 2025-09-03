<?php

use App\Http\Controllers\Auth\AccountController;
use App\Http\Controllers\EmailController;
use App\Http\Controllers\EscalationController;
use App\Http\Controllers\LlmController;
use App\Http\Controllers\RuleController;
use App\Http\Controllers\SmtpController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/user', function (Request $request) {
    return $request->user();
})->middleware('auth:sanctum');

// Account Management Routes
Route::middleware('auth:sanctum')->group(function () {
    // Account management routes
    Route::apiResource('accounts', AccountController::class);
    Route::post('accounts/{account}/test-connection', [AccountController::class, 'testConnection']);
    
    // Email management routes
    Route::post('accounts/{account}/fetch-emails', [EmailController::class, 'fetchEmails']);
    Route::get('accounts/{account}/emails', [EmailController::class, 'getEmails']);
    Route::get('accounts/{account}/emails/{email}', [EmailController::class, 'getEmail']);
    Route::patch('accounts/{account}/emails/{email}/read', [EmailController::class, 'markAsRead']);
    Route::get('accounts/{account}/stats', [EmailController::class, 'getStats']);
    
    // Rule management routes
    Route::get('accounts/{account}/rules', [RuleController::class, 'index']);
    Route::post('accounts/{account}/rules', [RuleController::class, 'store']);
    Route::get('accounts/{account}/rules/{rule}', [RuleController::class, 'show']);
    Route::put('accounts/{account}/rules/{rule}', [RuleController::class, 'update']);
    Route::delete('accounts/{account}/rules/{rule}', [RuleController::class, 'destroy']);
    Route::post('accounts/{account}/rules/{rule}/test', [RuleController::class, 'testRule']);
    Route::post('accounts/{account}/rules/reorder', [RuleController::class, 'reorder']);
    Route::get('accounts/{account}/rules/{rule}/stats', [RuleController::class, 'getStats']);
    
    // LLM integration routes
    Route::post('accounts/{account}/emails/{email}/classify', [LlmController::class, 'classifyEmail']);
    Route::post('accounts/{account}/emails/{email}/generate-response', [LlmController::class, 'generateResponse']);
    Route::post('accounts/{account}/emails/{email}/analyze-sentiment', [LlmController::class, 'analyzeSentiment']);
    Route::post('accounts/{account}/emails/batch-classify', [LlmController::class, 'batchClassify']);
    Route::get('accounts/{account}/llm/usage-stats', [LlmController::class, 'getUsageStats']);
    Route::get('accounts/{account}/llm/request-history', [LlmController::class, 'getRequestHistory']);
    
    // SMTP sending routes
    Route::post('accounts/{account}/send-email', [SmtpController::class, 'sendEmail']);
    Route::post('accounts/{account}/emails/{email}/reply', [SmtpController::class, 'sendReply']);
    Route::post('accounts/{account}/emails/{email}/forward', [SmtpController::class, 'forwardEmail']);
    Route::post('accounts/{account}/test-smtp', [SmtpController::class, 'testConnection']);
    
    // Escalation management routes
    Route::get('escalations', [EscalationController::class, 'index']);
    Route::post('escalations', [EscalationController::class, 'store']);
    Route::get('escalations/{escalation}', [EscalationController::class, 'show']);
    Route::put('escalations/{escalation}', [EscalationController::class, 'update']);
    Route::post('escalations/{escalation}/assign', [EscalationController::class, 'assign']);
    Route::post('escalations/{escalation}/resolve', [EscalationController::class, 'resolve']);
    Route::get('escalations/stats', [EscalationController::class, 'stats']);
    Route::get('my-escalations', [EscalationController::class, 'myEscalations']);
    Route::get('accounts/{account}/send-stats', [SmtpController::class, 'getStats']);
    Route::get('accounts/{account}/send-logs', [SmtpController::class, 'getSendLogs']);
    Route::get('accounts/{account}/send-logs/{sendLogId}', [SmtpController::class, 'getSendLog']);
    Route::post('accounts/{account}/send-logs/{sendLogId}/retry', [SmtpController::class, 'retrySend']);
});