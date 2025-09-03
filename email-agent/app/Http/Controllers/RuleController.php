<?php

namespace App\Http\Controllers;

use App\Models\Account;
use App\Models\Rule;
use App\Services\RulesEngine;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule as ValidationRule;

class RuleController extends Controller
{
    protected RulesEngine $rulesEngine;

    public function __construct(RulesEngine $rulesEngine)
    {
        $this->rulesEngine = $rulesEngine;
    }

    /**
     * Get all rules for an account
     */
    public function index(Request $request, Account $account): JsonResponse
    {
        // Check if user owns this account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $query = Rule::where('account_id', $account->id)
            ->orderBy('priority', 'desc')
            ->orderBy('execution_order', 'asc');

        // Filter by status if provided
        if ($request->has('status')) {
            $query->where('status', $request->input('status'));
        }

        $rules = $query->paginate($request->input('per_page', 20));

        return response()->json($rules);
    }

    /**
     * Create a new rule
     */
    public function store(Request $request, Account $account): JsonResponse
    {
        // Check if user owns this account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'required|in:active,inactive,draft',
            'priority' => 'required|integer|min:1|max:10',
            'execution_order' => 'nullable|integer|min:1',
            'conditions' => 'required|array|min:1',
            'conditions.*.field' => 'required|string',
            'conditions.*.operator' => 'required|string',
            'conditions.*.value' => 'required',
            'condition_logic' => 'required|in:and,or',
            'actions' => 'required|array|min:1',
            'actions.*.type' => 'required|string',
            'actions.*.value' => 'nullable',
            'stop_processing' => 'boolean',
            'schedule' => 'nullable|array',
            'active_from' => 'nullable|date',
            'active_until' => 'nullable|date|after:active_from'
        ]);

        // Set execution order if not provided
        if (!isset($validated['execution_order'])) {
            $maxOrder = Rule::where('account_id', $account->id)->max('execution_order') ?? 0;
            $validated['execution_order'] = $maxOrder + 1;
        }

        $validated['account_id'] = $account->id;
        $validated['user_id'] = Auth::id();

        $rule = Rule::create($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rule created successfully',
            'rule' => $rule
        ], 201);
    }

    /**
     * Get a specific rule
     */
    public function show(Account $account, Rule $rule): JsonResponse
    {
        // Check if user owns this account and rule belongs to account
        if ($account->user_id !== Auth::id() || $rule->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        return response()->json($rule);
    }

    /**
     * Update a rule
     */
    public function update(Request $request, Account $account, Rule $rule): JsonResponse
    {
        // Check if user owns this account and rule belongs to account
        if ($account->user_id !== Auth::id() || $rule->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'name' => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'status' => 'sometimes|required|in:active,inactive,draft',
            'priority' => 'sometimes|required|integer|min:1|max:10',
            'execution_order' => 'nullable|integer|min:1',
            'conditions' => 'sometimes|required|array|min:1',
            'conditions.*.field' => 'required_with:conditions|string',
            'conditions.*.operator' => 'required_with:conditions|string',
            'conditions.*.value' => 'required_with:conditions',
            'condition_logic' => 'sometimes|required|in:and,or',
            'actions' => 'sometimes|required|array|min:1',
            'actions.*.type' => 'required_with:actions|string',
            'actions.*.value' => 'nullable',
            'stop_processing' => 'boolean',
            'schedule' => 'nullable|array',
            'active_from' => 'nullable|date',
            'active_until' => 'nullable|date|after:active_from'
        ]);

        $rule->update($validated);

        return response()->json([
            'success' => true,
            'message' => 'Rule updated successfully',
            'rule' => $rule
        ]);
    }

    /**
     * Delete a rule
     */
    public function destroy(Account $account, Rule $rule): JsonResponse
    {
        // Check if user owns this account and rule belongs to account
        if ($account->user_id !== Auth::id() || $rule->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $rule->delete();

        return response()->json([
            'success' => true,
            'message' => 'Rule deleted successfully'
        ]);
    }

    /**
     * Test a rule against sample data
     */
    public function testRule(Request $request, Account $account, Rule $rule): JsonResponse
    {
        // Check if user owns this account and rule belongs to account
        if ($account->user_id !== Auth::id() || $rule->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'sample_email' => 'required|array',
            'sample_email.from_address' => 'required|email',
            'sample_email.from_name' => 'nullable|string',
            'sample_email.subject' => 'required|string',
            'sample_email.body_text' => 'nullable|string',
            'sample_email.body_html' => 'nullable|string',
            'sample_email.to_addresses' => 'nullable|array',
            'sample_email.cc_addresses' => 'nullable|array',
            'sample_email.attachments' => 'nullable|array'
        ]);

        // Create a temporary ProcessedEmail object for testing
        $testEmail = new \App\Models\ProcessedEmail($validated['sample_email']);
        $testEmail->account_id = $account->id;
        $testEmail->received_at = now();

        // Test the rule
        $result = $this->rulesEngine->evaluateRule($rule, $testEmail);

        return response()->json([
            'success' => true,
            'test_result' => $result
        ]);
    }

    /**
     * Reorder rules
     */
    public function reorder(Request $request, Account $account): JsonResponse
    {
        // Check if user owns this account
        if ($account->user_id !== Auth::id()) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $validated = $request->validate([
            'rule_orders' => 'required|array',
            'rule_orders.*.id' => 'required|integer|exists:rules,id',
            'rule_orders.*.execution_order' => 'required|integer|min:1'
        ]);

        foreach ($validated['rule_orders'] as $ruleOrder) {
            Rule::where('id', $ruleOrder['id'])
                ->where('account_id', $account->id)
                ->update(['execution_order' => $ruleOrder['execution_order']]);
        }

        return response()->json([
            'success' => true,
            'message' => 'Rules reordered successfully'
        ]);
    }

    /**
     * Get rule statistics
     */
    public function getStats(Account $account, Rule $rule): JsonResponse
    {
        // Check if user owns this account and rule belongs to account
        if ($account->user_id !== Auth::id() || $rule->account_id !== $account->id) {
            return response()->json(['error' => 'Unauthorized'], 403);
        }

        $stats = [
            'execution_count' => $rule->execution_count,
            'match_count' => $rule->match_count,
            'error_count' => $rule->error_count,
            'match_rate' => $rule->execution_count > 0 ? round(($rule->match_count / $rule->execution_count) * 100, 2) : 0,
            'last_executed_at' => $rule->last_executed_at,
            'last_error' => $rule->last_error,
            'created_at' => $rule->created_at,
            'updated_at' => $rule->updated_at
        ];

        return response()->json($stats);
    }
}
