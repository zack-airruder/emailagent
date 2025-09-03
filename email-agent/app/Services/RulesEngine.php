<?php

namespace App\Services;

use App\Models\Account;
use App\Models\ProcessedEmail;
use App\Models\Rule;
use App\Services\LlmService;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;

class RulesEngine
{
    protected LlmService $llmService;

    public function __construct(LlmService $llmService)
    {
        $this->llmService = $llmService;
    }

    /**
     * Process email against all active rules for an account
     */
    public function processEmail(ProcessedEmail $email): array
    {
        $account = $email->account;
        $results = [];
        
        // First, try to classify the email using LLM if not already classified
        if (!$email->classification_category || !$email->classification_intent) {
            try {
                $classification = $this->llmService->classifyEmail($email, $account);
                
                $email->update([
                    'classification_category' => $classification['category'] ?? $email->classification_category,
                    'classification_intent' => $classification['intent'] ?? $email->classification_intent,
                    'classification_sentiment' => $classification['sentiment'] ?? $email->classification_sentiment,
                    'metadata' => array_merge($email->metadata ?? [], [
                        'llm_classification' => $classification,
                        'auto_classified_at' => now()->toISOString()
                    ])
                ]);
                
                $results['llm_classification'] = $classification;
                
            } catch (\Exception $e) {
                Log::warning('LLM classification failed during rule processing', [
                    'email_id' => $email->id,
                    'error' => $e->getMessage()
                ]);
            }
        }
        
        // Get active rules for this account, ordered by priority and execution order
        $rules = Rule::where('account_id', $account->id)
            ->where('status', 'active')
            ->where(function ($query) {
                $query->whereNull('active_from')
                    ->orWhere('active_from', '<=', now());
            })
            ->where(function ($query) {
                $query->whereNull('active_until')
                    ->orWhere('active_until', '>=', now());
            })
            ->orderBy('priority', 'desc')
            ->orderBy('execution_order', 'asc')
            ->get();
        
        foreach ($rules as $rule) {
            try {
                $ruleResult = $this->evaluateRule($rule, $email);
                $results[] = $ruleResult;
                
                // Update rule statistics
                $this->updateRuleStats($rule, $ruleResult['matched']);
                
                // If rule matched and has stop_processing flag, break
                if ($ruleResult['matched'] && $rule->stop_processing) {
                    break;
                }
                
            } catch (\Exception $e) {
                Log::error('Error processing rule', [
                    'rule_id' => $rule->id,
                    'email_id' => $email->id,
                    'error' => $e->getMessage()
                ]);
                
                $this->updateRuleStats($rule, false, $e->getMessage());
            }
        }
        
        return $results;
    }
    
    /**
     * Evaluate a single rule against an email
     */
    public function evaluateRule(Rule $rule, ProcessedEmail $email): array
    {
        $conditions = $rule->conditions;
        $logic = $rule->condition_logic ?? 'and';
        
        // Evaluate all conditions
        $conditionResults = [];
        foreach ($conditions as $condition) {
            $conditionResults[] = $this->evaluateCondition($condition, $email);
        }
        
        // Apply logic (AND/OR)
        $matched = $this->applyLogic($conditionResults, $logic);
        
        $result = [
            'rule_id' => $rule->id,
            'rule_name' => $rule->name,
            'matched' => $matched,
            'condition_results' => $conditionResults,
            'actions_executed' => []
        ];
        
        // If rule matched, execute actions
        if ($matched) {
            $result['actions_executed'] = $this->executeActions($rule->actions, $email);
        }
        
        return $result;
    }
    
    /**
     * Evaluate a single condition
     */
    protected function evaluateCondition(array $condition, ProcessedEmail $email): bool
    {
        $field = $condition['field'] ?? '';
        $operator = $condition['operator'] ?? 'equals';
        $value = $condition['value'] ?? '';
        
        // Get the actual value from the email
        $emailValue = $this->getEmailFieldValue($email, $field);
        
        return $this->compareValues($emailValue, $operator, $value);
    }
    
    /**
     * Get field value from email
     */
    protected function getEmailFieldValue(ProcessedEmail $email, string $field): mixed
    {
        return match ($field) {
            'from_address' => $email->from_address,
            'from_name' => $email->from_name,
            'subject' => $email->subject,
            'body_text' => $email->body_text,
            'body_html' => $email->body_html,
            'to_addresses' => implode(', ', $email->to_addresses ?? []),
            'cc_addresses' => implode(', ', $email->cc_addresses ?? []),
            'received_at' => $email->received_at,
            'has_attachments' => !empty($email->attachments),
            'attachment_count' => count($email->attachments ?? []),
            'classification_intent' => $email->classification_intent,
            'classification_category' => $email->classification_category,
            'classification_sentiment' => $email->classification_sentiment,
            default => null
        };
    }
    
    /**
     * Compare values based on operator
     */
    protected function compareValues(mixed $emailValue, string $operator, mixed $conditionValue): bool
    {
        if ($emailValue === null) {
            return $operator === 'is_empty';
        }
        
        return match ($operator) {
            'equals' => $emailValue == $conditionValue,
            'not_equals' => $emailValue != $conditionValue,
            'contains' => str_contains(strtolower($emailValue), strtolower($conditionValue)),
            'not_contains' => !str_contains(strtolower($emailValue), strtolower($conditionValue)),
            'starts_with' => str_starts_with(strtolower($emailValue), strtolower($conditionValue)),
            'ends_with' => str_ends_with(strtolower($emailValue), strtolower($conditionValue)),
            'regex' => preg_match('/' . $conditionValue . '/i', $emailValue),
            'is_empty' => empty($emailValue),
            'is_not_empty' => !empty($emailValue),
            'greater_than' => $emailValue > $conditionValue,
            'less_than' => $emailValue < $conditionValue,
            'greater_equal' => $emailValue >= $conditionValue,
            'less_equal' => $emailValue <= $conditionValue,
            'in_list' => in_array($emailValue, explode(',', $conditionValue)),
            'not_in_list' => !in_array($emailValue, explode(',', $conditionValue)),
            'date_before' => Carbon::parse($emailValue)->lt(Carbon::parse($conditionValue)),
            'date_after' => Carbon::parse($emailValue)->gt(Carbon::parse($conditionValue)),
            'date_between' => $this->isDateBetween($emailValue, $conditionValue),
            default => false
        };
    }
    
    /**
     * Check if date is between two dates
     */
    protected function isDateBetween(mixed $emailValue, string $conditionValue): bool
    {
        $dates = explode(',', $conditionValue);
        if (count($dates) !== 2) {
            return false;
        }
        
        $emailDate = Carbon::parse($emailValue);
        $startDate = Carbon::parse(trim($dates[0]));
        $endDate = Carbon::parse(trim($dates[1]));
        
        return $emailDate->between($startDate, $endDate);
    }
    
    /**
     * Apply logic to condition results
     */
    protected function applyLogic(array $results, string $logic): bool
    {
        if (empty($results)) {
            return false;
        }
        
        return match ($logic) {
            'and' => !in_array(false, $results),
            'or' => in_array(true, $results),
            default => false
        };
    }
    
    /**
     * Execute rule actions
     */
    protected function executeActions(array $actions, ProcessedEmail $email): array
    {
        $executedActions = [];
        
        foreach ($actions as $action) {
            try {
                $result = $this->executeAction($action, $email);
                $executedActions[] = [
                    'action' => $action,
                    'success' => $result['success'],
                    'message' => $result['message'] ?? null
                ];
            } catch (\Exception $e) {
                $executedActions[] = [
                    'action' => $action,
                    'success' => false,
                    'message' => $e->getMessage()
                ];
            }
        }
        
        return $executedActions;
    }
    
    /**
     * Execute a single action
     */
    protected function executeAction(array $action, ProcessedEmail $email): array
    {
        $type = $action['type'] ?? '';
        
        return match ($type) {
            'mark_as_read' => $this->markAsRead($email),
            'mark_as_unread' => $this->markAsUnread($email),
            'set_category' => $this->setCategory($email, $action['value'] ?? ''),
            'set_priority' => $this->setPriority($email, $action['value'] ?? 'normal'),
            'add_tag' => $this->addTag($email, $action['value'] ?? ''),
            'escalate' => $this->escalateEmail($email, $action),
            'auto_reply' => $this->scheduleAutoReply($email, $action),
            'forward' => $this->scheduleForward($email, $action),
            'delete' => $this->deleteEmail($email),
            'archive' => $this->archiveEmail($email),
            default => ['success' => false, 'message' => 'Unknown action type: ' . $type]
        };
    }
    
    /**
     * Mark email as read
     */
    protected function markAsRead(ProcessedEmail $email): array
    {
        $email->update(['status' => 'read']);
        return ['success' => true, 'message' => 'Email marked as read'];
    }
    
    /**
     * Mark email as unread
     */
    protected function markAsUnread(ProcessedEmail $email): array
    {
        $email->update(['status' => 'unread']);
        return ['success' => true, 'message' => 'Email marked as unread'];
    }
    
    /**
     * Set email category
     */
    protected function setCategory(ProcessedEmail $email, string $category): array
    {
        $email->update(['classification_category' => $category]);
        return ['success' => true, 'message' => 'Category set to: ' . $category];
    }
    
    /**
     * Set email priority
     */
    protected function setPriority(ProcessedEmail $email, string $priority): array
    {
        $metadata = $email->metadata ?? [];
        $metadata['priority'] = $priority;
        $email->update(['metadata' => $metadata]);
        return ['success' => true, 'message' => 'Priority set to: ' . $priority];
    }
    
    /**
     * Add tag to email
     */
    protected function addTag(ProcessedEmail $email, string $tag): array
    {
        $metadata = $email->metadata ?? [];
        $tags = $metadata['tags'] ?? [];
        if (!in_array($tag, $tags)) {
            $tags[] = $tag;
            $metadata['tags'] = $tags;
            $email->update(['metadata' => $metadata]);
        }
        return ['success' => true, 'message' => 'Tag added: ' . $tag];
    }
    
    /**
     * Escalate email to human agent
     */
    protected function escalateEmail(ProcessedEmail $email, array $action): array
    {
        // This will be implemented when we create the escalation system
        return ['success' => true, 'message' => 'Email escalated (placeholder)'];
    }
    
    /**
     * Schedule auto reply
     */
    protected function scheduleAutoReply(ProcessedEmail $email, array $action): array
    {
        // This will be implemented when we create the SMTP sending system
        return ['success' => true, 'message' => 'Auto reply scheduled (placeholder)'];
    }
    
    /**
     * Schedule email forward
     */
    protected function scheduleForward(ProcessedEmail $email, array $action): array
    {
        // This will be implemented when we create the SMTP sending system
        return ['success' => true, 'message' => 'Forward scheduled (placeholder)'];
    }
    
    /**
     * Delete email
     */
    protected function deleteEmail(ProcessedEmail $email): array
    {
        $email->update(['status' => 'deleted']);
        return ['success' => true, 'message' => 'Email deleted'];
    }
    
    /**
     * Archive email
     */
    protected function archiveEmail(ProcessedEmail $email): array
    {
        $email->update(['status' => 'archived']);
        return ['success' => true, 'message' => 'Email archived'];
    }
    
    /**
     * Update rule execution statistics
     */
    protected function updateRuleStats(Rule $rule, bool $matched, string $error = null): void
    {
        $rule->increment('execution_count');
        $rule->update(['last_executed_at' => now()]);
        
        if ($matched) {
            $rule->increment('match_count');
        }
        
        if ($error) {
            $rule->increment('error_count');
            $rule->update(['last_error' => $error]);
        }
    }
}
