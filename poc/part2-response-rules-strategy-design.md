# Part 2: Response Rules & Strategy

## Overview
Design for the Rules Engine that classifies emails, evaluates conditions, and determines automated actions with throttling and decision traceability.

## HTTP Interfaces

### Rules CRUD Operations

#### GET /rules
**Query Parameters:**
```
?status=active|inactive|draft
&account_id=uuid
&category=support|sales|billing
&limit=50
&offset=0
&sort=priority|updated_at
&order=asc|desc
```

**Response:**
```json
{
  "rules": [
    {
      "id": "string",
      "name": "string",
      "description": "string",
      "priority": "number", // 1-1000, lower = higher priority
      "status": "string", // "active", "inactive", "draft"
      "conditions": {
        "operator": "AND|OR",
        "rules": [
          {
            "field": "string", // "sender", "subject", "llm_intent", etc.
            "operator": "string", // "equals", "contains", "matches", etc.
            "value": "any",
            "case_sensitive": "boolean"
          }
        ]
      },
      "action": {
        "type": "string", // "auto_send", "auto_draft", "queue_human", "skip", "label", "move"
        "options": "object" // Action-specific configuration
      },
      "throttle": {
        "starter_batch": "number", // First N emails for testing
        "daily_limit": "number",
        "failure_threshold": "number", // Pause after N failures
        "bounce_threshold": "number" // Pause after N% bounce rate
      },
      "created_at": "string",
      "updated_at": "string",
      "created_by": "string",
      "stats": {
        "matched_count": "number",
        "success_count": "number",
        "failure_count": "number",
        "last_matched": "string"
      }
    }
  ],
  "total": "number",
  "has_more": "boolean"
}
```

#### POST /rules
```json
{
  "name": "string",
  "description": "string?",
  "priority": "number", // 1-1000
  "account_id": "string?", // null = applies to all accounts
  "conditions": {
    "operator": "AND|OR",
    "rules": [
      {
        "field": "string",
        "operator": "string",
        "value": "any",
        "case_sensitive": "boolean?"
      }
    ]
  },
  "action": {
    "type": "string",
    "options": "object"
  },
  "throttle": {
    "starter_batch": "number?", // Default: 10
    "daily_limit": "number?", // Default: 100
    "failure_threshold": "number?", // Default: 5
    "bounce_threshold": "number?" // Default: 10 (10%)
  },
  "status": "string?" // Default: "draft"
}
```

#### PUT /rules/:id
```json
{
  "name": "string?",
  "description": "string?",
  "priority": "number?",
  "conditions": "object?",
  "action": "object?",
  "throttle": "object?",
  "status": "string?"
}
```

#### POST /rules/preview
```json
{
  "rule": {
    "conditions": "object", // Same as rule conditions
    "action": "object"
  },
  "sample_email": {
    "sender": "string",
    "recipient": "string",
    "subject": "string",
    "body_preview": "string", // First 500 chars
    "headers": "object",
    "llm_classification": {
      "intent": "string",
      "category": "string",
      "confidence": "number",
      "risk": "string"
    }
  }
}
```

**Response:**
```json
{
  "matches": "boolean",
  "evaluation_trace": [
    {
      "condition_index": "number",
      "field": "string",
      "operator": "string",
      "expected": "any",
      "actual": "any",
      "result": "boolean",
      "execution_time_ms": "number"
    }
  ],
  "final_result": "boolean",
  "would_execute_action": {
    "type": "string",
    "blocked_by_throttle": "boolean",
    "throttle_reason": "string?",
    "estimated_action": "object"
  },
  "total_evaluation_time_ms": "number"
}
```

## Condition Grammar

### Supported Fields
```json
{
  "email_fields": {
    "sender": "string", // From address
    "sender_domain": "string", // Domain part of From
    "sender_name": "string", // Display name
    "recipient": "string", // To address
    "recipient_alias": "string", // Local part of To
    "subject": "string",
    "body_preview": "string", // First 1000 chars
    "folder": "string", // IMAP folder name
    "has_attachments": "boolean",
    "attachment_count": "number",
    "message_size": "number", // Bytes
    "thread_count": "number" // Messages in thread
  },
  "temporal_fields": {
    "arrival_time": "datetime", // When received
    "arrival_hour": "number", // 0-23
    "arrival_day_of_week": "number", // 1-7 (Monday=1)
    "arrival_timezone": "string", // Account timezone
    "is_business_hours": "boolean", // 9-17 in account timezone
    "is_weekend": "boolean"
  },
  "llm_fields": {
    "llm_intent": "string", // "question", "complaint", "request", etc.
    "llm_category": "string", // "support", "sales", "billing", etc.
    "llm_confidence": "number", // 0.0-1.0
    "llm_risk": "string", // "low", "medium", "high"
    "llm_language": "string", // "en", "es", "fr", etc.
    "llm_sentiment": "string", // "positive", "neutral", "negative"
    "llm_urgency": "string" // "low", "medium", "high", "urgent"
  },
  "context_fields": {
    "account_id": "string",
    "is_reply": "boolean", // Has In-Reply-To header
    "is_forward": "boolean", // Subject starts with "Fwd:"
    "is_auto_reply": "boolean", // Auto-Submitted header
    "previous_automation_count": "number", // Times this thread was automated
    "sender_history_count": "number", // Previous emails from sender
    "last_human_reply": "datetime?" // Last human response in thread
  }
}
```

### Supported Operators
```json
{
  "string_operators": {
    "equals": "Exact match (case-sensitive option)",
    "not_equals": "Not equal",
    "contains": "Substring match",
    "not_contains": "Does not contain substring",
    "starts_with": "Prefix match",
    "ends_with": "Suffix match",
    "matches": "Regular expression match",
    "not_matches": "Does not match regex",
    "in_list": "Value in comma-separated list",
    "not_in_list": "Value not in list",
    "is_empty": "Field is null or empty string",
    "is_not_empty": "Field has value"
  },
  "numeric_operators": {
    "equals": "Exact numeric match",
    "not_equals": "Not equal",
    "greater_than": ">",
    "greater_than_or_equal": ">=",
    "less_than": "<",
    "less_than_or_equal": "<=",
    "between": "Inclusive range [min, max]",
    "not_between": "Outside range"
  },
  "datetime_operators": {
    "equals": "Exact datetime match",
    "before": "Earlier than",
    "after": "Later than",
    "between": "Date range",
    "within_last": "Within last N minutes/hours/days",
    "older_than": "Older than N minutes/hours/days"
  },
  "boolean_operators": {
    "is_true": "Field is true",
    "is_false": "Field is false"
  }
}
```

### Condition Examples
```json
{
  "simple_conditions": [
    {
      "field": "sender_domain",
      "operator": "equals",
      "value": "customer.com",
      "case_sensitive": false
    },
    {
      "field": "subject",
      "operator": "contains",
      "value": "urgent",
      "case_sensitive": false
    },
    {
      "field": "llm_intent",
      "operator": "in_list",
      "value": "question,request,complaint"
    },
    {
      "field": "arrival_time",
      "operator": "within_last",
      "value": "2 hours"
    }
  ],
  "complex_condition": {
    "operator": "AND",
    "rules": [
      {
        "operator": "OR",
        "rules": [
          {
            "field": "llm_category",
            "operator": "equals",
            "value": "support"
          },
          {
            "field": "llm_category",
            "operator": "equals",
            "value": "billing"
          }
        ]
      },
      {
        "field": "llm_confidence",
        "operator": "greater_than",
        "value": 0.8
      },
      {
        "field": "is_business_hours",
        "operator": "is_true"
      }
    ]
  }
}
```

## Action Types & Options

### Auto-Send
```json
{
  "type": "auto_send",
  "options": {
    "prompt_id": "string", // Prompt template to use
    "prompt_variables": "object", // Variable overrides
    "delay_minutes": "number?", // Optional delay before sending
    "require_approval_if_risk": "string?", // "medium", "high"
    "copy_to_sent": "boolean", // Default: true
    "add_signature": "boolean", // Default: true
    "preserve_thread": "boolean" // Default: true
  }
}
```

### Auto-Draft
```json
{
  "type": "auto_draft",
  "options": {
    "prompt_id": "string",
    "prompt_variables": "object",
    "save_to_folder": "string", // Default: "Drafts"
    "notify_human": "boolean", // Send notification
    "notification_delay_minutes": "number" // Default: 60
  }
}
```

### Queue for Human
```json
{
  "type": "queue_human",
  "options": {
    "priority": "string", // "low", "medium", "high", "urgent"
    "assigned_to": "string?", // User ID or team
    "sla_hours": "number", // Response SLA
    "escalation_hours": "number?", // Auto-escalate after
    "include_draft": "boolean", // Generate suggested response
    "draft_prompt_id": "string?",
    "tags": "string[]" // Categorization tags
  }
}
```

### Skip/Label/Move
```json
{
  "type": "skip",
  "options": {
    "reason": "string", // "out_of_scope", "spam", "auto_reply"
    "add_label": "string?", // IMAP label to add
    "move_to_folder": "string?", // Move to specific folder
    "log_decision": "boolean" // Default: true
  }
}
```

## Evaluation Order & Logic

### Rule Priority System
1. **Priority Number**: Lower numbers = higher priority (1 = highest)
2. **Tie Breaking**: If same priority, use `created_at` (older first)
3. **Account Specificity**: Account-specific rules override global rules
4. **Stop on Match**: First matching rule wins (no fall-through)

### Evaluation Algorithm
```
1. Load active rules for account (or global) sorted by priority
2. For each rule in priority order:
   a. Check throttle limits (skip if exceeded)
   b. Evaluate conditions against email
   c. If match:
      - Log decision trace
      - Execute action (if not dry-run)
      - Update rule statistics
      - STOP evaluation
3. If no rules match:
   - Log "no_match" decision
   - Apply default action (usually "queue_human")
```

### Condition Evaluation
```
1. Parse condition tree (AND/OR operators)
2. For each leaf condition:
   a. Extract field value from email
   b. Apply operator with expected value
   c. Record result and timing
3. Combine results using boolean logic
4. Return final boolean + trace
```

### Recursion Guards
- **Max Depth**: Condition nesting limited to 5 levels
- **Max Conditions**: Maximum 50 conditions per rule
- **Timeout**: Rule evaluation timeout at 5 seconds
- **Infinite Loop**: Track rule execution history per thread

## Throttle Mathematics

### Starter Batch Logic
```javascript
// Check if rule is still in starter batch phase
function isInStarterBatch(rule, stats) {
  return stats.matched_count < rule.throttle.starter_batch;
}

// Starter batch allows higher risk tolerance
function getEffectiveThresholds(rule, stats) {
  if (isInStarterBatch(rule, stats)) {
    return {
      failure_threshold: Math.max(rule.throttle.failure_threshold, 3),
      bounce_threshold: Math.max(rule.throttle.bounce_threshold, 20)
    };
  }
  return rule.throttle;
}
```

### Daily Limit Tracking
```javascript
// Check daily limit (resets at midnight in account timezone)
function checkDailyLimit(rule, accountTimezone) {
  const today = moment().tz(accountTimezone).startOf('day');
  const todayCount = getTodayExecutionCount(rule.id, today);
  
  return todayCount < rule.throttle.daily_limit;
}
```

### Failure Threshold
```javascript
// Check consecutive failures
function checkFailureThreshold(rule, stats) {
  const recentFailures = getRecentFailures(rule.id, '1 hour');
  const consecutiveFailures = getConsecutiveFailures(rule.id);
  
  return consecutiveFailures < rule.throttle.failure_threshold;
}
```

### Bounce Rate Calculation
```javascript
// Calculate bounce rate over last 24 hours
function calculateBounceRate(rule) {
  const period = '24 hours';
  const sent = getSentCount(rule.id, period);
  const bounced = getBounceCount(rule.id, period);
  
  if (sent === 0) return 0;
  return (bounced / sent) * 100;
}

function checkBounceThreshold(rule) {
  const bounceRate = calculateBounceRate(rule);
  return bounceRate < rule.throttle.bounce_threshold;
}
```

### Auto-Pause Logic
```javascript
function shouldPauseRule(rule, stats) {
  const reasons = [];
  
  if (!checkFailureThreshold(rule, stats)) {
    reasons.push('failure_threshold_exceeded');
  }
  
  if (!checkBounceThreshold(rule)) {
    reasons.push('bounce_rate_exceeded');
  }
  
  if (!checkDailyLimit(rule, stats.account_timezone)) {
    reasons.push('daily_limit_reached');
  }
  
  return {
    should_pause: reasons.length > 0,
    reasons: reasons,
    auto_resume_at: calculateResumeTime(reasons)
  };
}
```

## Data Schemas

### rules
```sql
CREATE TABLE rules (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  account_id VARCHAR(36), -- NULL = global rule
  
  priority INT NOT NULL DEFAULT 100, -- 1-1000, lower = higher priority
  status ENUM('active', 'inactive', 'draft', 'paused') DEFAULT 'draft',
  
  -- Rule logic
  conditions_json JSON NOT NULL,
  action_type ENUM('auto_send', 'auto_draft', 'queue_human', 'skip', 'label', 'move') NOT NULL,
  action_options_json JSON NOT NULL,
  
  -- Throttling
  starter_batch INT DEFAULT 10,
  daily_limit INT DEFAULT 100,
  failure_threshold INT DEFAULT 5,
  bounce_threshold DECIMAL(5,2) DEFAULT 10.00, -- Percentage
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by VARCHAR(36) NOT NULL,
  
  -- Statistics (updated by triggers)
  matched_count INT DEFAULT 0,
  success_count INT DEFAULT 0,
  failure_count INT DEFAULT 0,
  last_matched TIMESTAMP NULL,
  last_success TIMESTAMP NULL,
  last_failure TIMESTAMP NULL,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  
  INDEX idx_priority (account_id, status, priority),
  INDEX idx_status (status),
  INDEX idx_stats (matched_count, success_count, failure_count),
  INDEX idx_last_matched (last_matched)
);
```

### decision_trace
```sql
CREATE TABLE decision_trace (
  id VARCHAR(36) PRIMARY KEY,
  processed_email_id VARCHAR(36) NOT NULL,
  
  -- Rule evaluation
  rule_id VARCHAR(36), -- NULL if no rule matched
  rule_version INT, -- Rule version at time of evaluation
  rule_priority INT,
  
  -- Decision details
  matched BOOLEAN NOT NULL,
  evaluation_time_ms INT NOT NULL,
  conditions_evaluated INT NOT NULL,
  
  -- Action taken
  action_type VARCHAR(50),
  action_executed BOOLEAN DEFAULT FALSE,
  action_blocked_reason VARCHAR(100), -- throttle, error, etc.
  
  -- Trace data
  condition_results_json JSON, -- Detailed evaluation trace
  email_fields_json JSON, -- Email fields at evaluation time
  throttle_status_json JSON, -- Throttle state
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  account_id VARCHAR(36) NOT NULL,
  
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE CASCADE,
  FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE SET NULL,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  
  INDEX idx_processed_email (processed_email_id),
  INDEX idx_rule_performance (rule_id, matched, created_at),
  INDEX idx_account_decisions (account_id, created_at),
  INDEX idx_action_type (action_type, created_at)
);
```

### rule_execution_stats
```sql
CREATE TABLE rule_execution_stats (
  id VARCHAR(36) PRIMARY KEY,
  rule_id VARCHAR(36) NOT NULL,
  
  -- Time period (daily buckets)
  stat_date DATE NOT NULL,
  account_id VARCHAR(36) NOT NULL,
  
  -- Counters
  matched_count INT DEFAULT 0,
  executed_count INT DEFAULT 0,
  success_count INT DEFAULT 0,
  failure_count INT DEFAULT 0,
  bounce_count INT DEFAULT 0,
  throttled_count INT DEFAULT 0,
  
  -- Performance
  avg_evaluation_time_ms DECIMAL(8,2),
  max_evaluation_time_ms INT,
  
  -- Timestamps
  first_execution TIMESTAMP NULL,
  last_execution TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_rule_date (rule_id, stat_date, account_id),
  INDEX idx_performance (rule_id, stat_date),
  INDEX idx_account_stats (account_id, stat_date)
);
```

## Edge Cases & Error Handling

### Overlapping Rules
- **Priority Resolution**: Lower priority number wins
- **Specificity**: Account-specific beats global
- **Logging**: Log all evaluated rules, not just winner
- **Testing**: Preview mode shows all matching rules

### Timezone Handling
- **Account Timezone**: Stored in account settings
- **Business Hours**: Calculated in account timezone
- **Daily Limits**: Reset at midnight in account timezone
- **Arrival Time**: Converted to account timezone for evaluation

### LLM Classification Failures
- **Missing Fields**: Treat as null/empty for evaluation
- **Low Confidence**: Rules can check confidence threshold
- **Timeout**: Default to "unknown" values
- **Fallback**: Rules without LLM fields still work

### Rule Modification During Execution
- **Version Tracking**: Decision trace records rule version
- **Active Changes**: New rules take effect immediately
- **Priority Changes**: Re-sort rule cache
- **Condition Changes**: Clear evaluation cache

## Test Checklist

### Determinism Tests
- **Same Input**: Same email + rule = same result
- **Order Independence**: Rule priority determines outcome
- **Timezone Consistency**: Business hours work across timezones
- **Concurrent Evaluation**: Thread-safe rule evaluation

### Priority Tie Scenarios
- **Same Priority**: Older rule wins
- **Account vs Global**: Account-specific wins
- **Multiple Matches**: First match stops evaluation
- **Priority Updates**: New priority takes effect immediately

### Mass-Labeling Safety
- **Batch Limits**: Maximum 1000 emails per batch
- **Rate Limiting**: 10 evaluations per second per account
- **Memory Limits**: Clear evaluation cache after 10k evaluations
- **Timeout Protection**: Kill evaluation after 30 seconds

### Throttle Trip & Auto-Pause
- **Failure Threshold**: Pause after N consecutive failures
- **Bounce Rate**: Pause when bounce rate exceeds threshold
- **Daily Limit**: Stop at midnight, resume next day
- **Manual Override**: Admin can force-resume paused rules

### Performance Tests
- **Large Rule Sets**: 1000+ rules per account
- **Complex Conditions**: Deeply nested AND/OR logic
- **High Volume**: 10k emails/hour evaluation
- **Memory Usage**: Stable memory under load

## Failure Modes & Recovery

### Rule Evaluation Failures
- **Syntax Errors**: Invalid regex, malformed JSON
- **Field Errors**: Missing or invalid email fields
- **Timeout**: Evaluation takes too long
- **Memory**: Out of memory during evaluation

**Recovery:**
- Log error with full context
- Mark rule as "error" status
- Continue with next rule
- Alert admin if error rate > 5%

### Throttle State Corruption
- **Counter Drift**: Statistics out of sync
- **Time Skew**: Server time changes
- **Database Locks**: Deadlocks on stats updates

**Recovery:**
- Rebuild stats from decision_trace table
- Use database transactions for counter updates
- Implement retry logic with exponential backoff

### Action Execution Failures
- **SMTP Errors**: Cannot send auto-reply
- **Queue Errors**: Cannot create escalation
- **Storage Errors**: Cannot save draft

**Recovery:**
- Retry with exponential backoff
- Fall back to "queue_human" action
- Log failure for manual review
- Update rule failure counters

---

## Pragmatic Review

### ✅ Interface Completeness
- CRUD operations with proper filtering and pagination
- Preview endpoint for rule testing
- Comprehensive request/response schemas
- Error handling and validation

### ✅ Condition Grammar
- Rich field set covering email, temporal, LLM, and context data
- Comprehensive operator set for all data types
- Complex nested conditions with AND/OR logic
- Clear examples and validation rules

### ✅ Evaluation Order
- Clear priority system with tie-breaking
- Stop-on-first-match semantics
- Account-specific vs global rule precedence
- Recursion and timeout protection

### ✅ Throttle Mathematics
- Starter batch for safe testing
- Daily limits with timezone awareness
- Failure and bounce rate thresholds
- Auto-pause and resume logic

### ✅ Decision Traceability
- Complete evaluation trace storage
- Rule version tracking
- Performance metrics
- Audit trail for compliance

### ✅ Error Path Coverage
- Rule evaluation failures
- Throttle state corruption
- Action execution errors
- Recovery and fallback strategies

### ✅ Test Matrix
- Determinism and consistency tests
- Priority and tie-breaking scenarios
- Safety limits and performance tests
- Failure mode recovery

### Potential Issues Identified:
1. **Rule Cache**: No caching strategy for high-volume evaluation
2. **Statistics Lag**: Real-time stats updates may cause contention
3. **Condition Complexity**: Very complex rules may be hard to debug
4. **Memory Usage**: Large rule sets may consume significant memory

### Recommended Fixes:
1. Add Redis-based rule cache with TTL
2. Use async statistics updates with eventual consistency
3. Add rule complexity scoring and warnings
4. Implement rule compilation for better performance