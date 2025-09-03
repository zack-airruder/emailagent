# Part 8: Human Agent Escalation

## Overview
Design for human agent escalation queue system. Handles cases where automated processing requires human review, approval, or intervention. Includes comprehensive workflow management, conflict resolution, SLA tracking, and audit trails.

## Goals
- **Rich Queue Items**: Preview, LLM draft, rationale, and recommended actions
- **Workflow Management**: Edit, Approve & Send, Return to Draft, Reassign capabilities
- **Conflict Resolution**: Handle double approvals and concurrent edits
- **SLA Compliance**: Track and alert on service level agreements
- **Audit Trail**: Complete history of all escalation actions
- **Performance Monitoring**: Queue metrics and agent productivity

## HTTP Interfaces

### Queue Management

#### GET /escalations
**Get Escalation Queue:**

**Query Parameters:**
```
# Filtering
status=pending,in_review,approved  # Comma-separated
assigned_to=user_id
account_id=uuid
priority=high,medium,low
escalation_reason=safety_check,rule_confidence,manual_review
sla_status=on_time,at_risk,breached

# Time filtering
created_after=2024-01-01T00:00:00Z
created_before=2024-01-31T23:59:59Z
due_before=2024-01-15T17:00:00Z

# Content filtering
subject_contains=urgent
from_email=user@domain.com
intent=complaint,inquiry
risk_level=high,medium

# Sorting
sort_by=created_at,due_at,priority,sla_remaining  # Default: priority desc, due_at asc
sort_order=asc,desc

# Pagination
page=1
page_size=50

# Data inclusion
include_preview=true
include_draft=true
include_history=false
```

**Response:**
```json
{
  "escalations": [
    {
      "id": "string",
      "created_at": "string",
      "updated_at": "string",
      "due_at": "string",
      
      "status": "string", // "pending", "in_review", "approved", "rejected", "returned", "cancelled"
      "priority": "string", // "high", "medium", "low"
      "escalation_reason": "string",
      "escalation_source": "string", // "auto_rule", "safety_check", "manual", "error_recovery"
      
      "assigned_to": {
        "user_id": "string",
        "name": "string",
        "email": "string",
        "assigned_at": "string"
      },
      
      "sla": {
        "target_response_minutes": "number",
        "target_resolution_minutes": "number",
        "time_remaining_minutes": "number",
        "status": "string", // "on_time", "at_risk", "breached"
        "breach_severity": "string?" // "minor", "major", "critical"
      },
      
      "original_email": {
        "id": "string",
        "account_id": "string",
        "account_name": "string",
        "message_id": "string",
        "subject": "string",
        "from_email": "string",
        "from_name": "string",
        "to_emails": ["string"],
        "received_at": "string",
        "has_attachments": "boolean",
        "thread_id": "string?"
      },
      
      "classification": {
        "intent": "string",
        "intent_confidence": "number",
        "category": "string",
        "sentiment": "string",
        "urgency": "string",
        "risk_level": "string",
        "risk_factors": ["string"]
      },
      
      "rule_context": {
        "matched_rule_id": "string?",
        "matched_rule_name": "string?",
        "match_confidence": "number?",
        "why_escalated": "string", // Human-readable explanation
        "alternative_rules": [
          {
            "rule_id": "string",
            "rule_name": "string",
            "match_score": "number",
            "would_trigger": "boolean"
          }
        ]
      },
      
      "recommended_action": {
        "action_type": "string", // "send_draft", "create_new_draft", "skip", "escalate_further"
        "confidence": "number",
        "reasoning": "string",
        "alternative_actions": [
          {
            "action_type": "string",
            "confidence": "number",
            "reasoning": "string"
          }
        ]
      },
      
      "preview": {
        "email_preview": {
          "body_text_preview": "string", // First 500 chars
          "body_html_preview": "string?",
          "key_phrases": ["string"],
          "entities_detected": [
            {
              "type": "string",
              "value": "string",
              "confidence": "number"
            }
          ]
        },
        "context_summary": "string", // AI-generated summary
        "urgency_indicators": ["string"],
        "risk_indicators": ["string"]
      },
      
      "draft_response": {
        "id": "string",
        "subject": "string",
        "body_text": "string",
        "body_html": "string?",
        "tone": "string",
        "confidence_score": "number",
        "template_used": "string?",
        "generated_at": "string",
        "generation_time_ms": "number",
        "safety_score": "number",
        "requires_review_reasons": ["string"]
      },
      
      "current_lock": {
        "locked": "boolean",
        "locked_by": "string?",
        "locked_at": "string?",
        "lock_expires_at": "string?",
        "lock_reason": "string?" // "editing", "reviewing", "approving"
      },
      
      "metrics": {
        "time_in_queue_minutes": "number",
        "time_since_assignment_minutes": "number?",
        "view_count": "number",
        "edit_count": "number"
      }
    }
  ],
  
  "queue_summary": {
    "total_items": "number",
    "by_status": {
      "pending": "number",
      "in_review": "number",
      "approved": "number",
      "overdue": "number"
    },
    "by_priority": {
      "high": "number",
      "medium": "number",
      "low": "number"
    },
    "sla_metrics": {
      "on_time": "number",
      "at_risk": "number",
      "breached": "number",
      "avg_response_time_minutes": "number",
      "avg_resolution_time_minutes": "number"
    }
  },
  
  "pagination": {
    "current_page": "number",
    "page_size": "number",
    "total_pages": "number",
    "total_records": "number",
    "has_next": "boolean",
    "has_previous": "boolean"
  }
}
```

#### POST /escalations
**Create Manual Escalation:**

```json
{
  "processed_email_id": "string",
  "escalation_reason": "string", // "manual_review", "complex_case", "policy_exception"
  "priority": "string", // "high", "medium", "low"
  "assign_to": "string?", // User ID
  "notes": "string?",
  "due_at": "string?", // ISO timestamp
  "context": {
    "requester_notes": "string?",
    "special_instructions": "string?",
    "related_cases": ["string"]?, // Other escalation IDs
    "customer_tier": "string?", // "vip", "enterprise", "standard"
    "business_impact": "string?" // "high", "medium", "low"
  }
}
```

**Response:**
```json
{
  "escalation_id": "string",
  "status": "pending",
  "created_at": "string",
  "due_at": "string",
  "assigned_to": {
    "user_id": "string",
    "name": "string"
  },
  "queue_position": "number"
}
```

### Individual Escalation Management

#### GET /escalations/:id
**Get Detailed Escalation:**

```json
{
  "escalation": {
    // Full escalation details (same as queue item but with additional fields)
    "full_email_content": {
      "body_text": "string",
      "body_html": "string?",
      "attachments": [
        {
          "filename": "string",
          "content_type": "string",
          "size_bytes": "number",
          "download_url": "string"
        }
      ]
    },
    
    "conversation_history": [
      {
        "email_id": "string",
        "direction": "string", // "inbound", "outbound"
        "subject": "string",
        "from_email": "string",
        "sent_at": "string",
        "preview": "string"
      }
    ],
    
    "related_escalations": [
      {
        "escalation_id": "string",
        "relationship": "string", // "duplicate", "related", "follow_up"
        "status": "string",
        "created_at": "string"
      }
    ],
    
    "activity_log": [
      {
        "timestamp": "string",
        "user_id": "string",
        "user_name": "string",
        "action": "string",
        "details": "object",
        "ip_address": "string"
      }
    ]
  }
}
```

#### PUT /escalations/:id/assign
**Assign/Reassign Escalation:**

```json
{
  "assign_to": "string", // User ID
  "reason": "string?",
  "notes": "string?",
  "priority": "string?", // Update priority if needed
  "due_at": "string?" // Update due date if needed
}
```

#### PUT /escalations/:id/lock
**Lock Escalation for Editing:**

```json
{
  "lock_reason": "string", // "editing", "reviewing", "approving"
  "duration_minutes": "number?" // Default: 30 minutes
}
```

**Response:**
```json
{
  "locked": "boolean",
  "lock_id": "string",
  "locked_until": "string",
  "can_extend": "boolean"
}
```

#### DELETE /escalations/:id/lock
**Release Lock:**

```json
{
  "lock_id": "string"
}
```

### Action Execution

#### POST /escalations/:id/edit-draft
**Edit Draft Response:**

```json
{
  "lock_id": "string", // Required for concurrency control
  "draft_updates": {
    "subject": "string?",
    "body_text": "string?",
    "body_html": "string?",
    "tone": "string?", // "professional", "friendly", "formal"
    "template_id": "string?"
  },
  "edit_notes": "string?",
  "regenerate_sections": {
    "greeting": "boolean?",
    "body": "boolean?",
    "closing": "boolean?"
  }
}
```

**Response:**
```json
{
  "draft_id": "string",
  "updated_at": "string",
  "version": "number",
  "changes_summary": {
    "fields_modified": ["string"],
    "character_changes": "number",
    "tone_changed": "boolean"
  },
  "validation": {
    "safety_score": "number",
    "warnings": ["string"],
    "requires_additional_review": "boolean"
  }
}
```

#### POST /escalations/:id/approve-send
**Approve and Send Response:**

```json
{
  "lock_id": "string",
  "approval_notes": "string?",
  "send_options": {
    "priority": "string?", // "immediate", "normal", "batch"
    "schedule_send": "string?", // ISO timestamp for scheduled send
    "copy_to_sent": "boolean?",
    "tracking_enabled": "boolean?"
  },
  "final_review": {
    "content_approved": "boolean",
    "tone_appropriate": "boolean",
    "addresses_inquiry": "boolean",
    "follows_policy": "boolean"
  }
}
```

**Response:**
```json
{
  "approval_id": "string",
  "approved_at": "string",
  "send_status": "string", // "queued", "sending", "sent", "failed"
  "send_log_id": "string?",
  "message_id": "string?",
  "estimated_delivery": "string?",
  "escalation_status": "approved", // Escalation is now closed
  "resolution_time_minutes": "number"
}
```

#### POST /escalations/:id/return-to-draft
**Return to Draft Status:**

```json
{
  "lock_id": "string",
  "return_reason": "string", // "needs_revision", "policy_violation", "tone_adjustment"
  "feedback": "string",
  "reassign_to": "string?", // User ID
  "priority_adjustment": "string?", // "increase", "decrease", "maintain"
  "new_due_date": "string?"
}
```

#### POST /escalations/:id/reject
**Reject Escalation:**

```json
{
  "lock_id": "string",
  "rejection_reason": "string", // "no_response_needed", "duplicate", "spam", "policy_violation"
  "rejection_notes": "string",
  "alternative_action": {
    "action_type": "string?", // "auto_close", "forward", "escalate_further"
    "action_details": "object?"
  }
}
```

#### POST /escalations/:id/escalate-further
**Escalate to Higher Level:**

```json
{
  "lock_id": "string",
  "escalation_level": "string", // "supervisor", "manager", "executive"
  "escalation_reason": "string",
  "urgency_justification": "string",
  "business_impact": "string",
  "requested_resolution_time": "string?"
}
```

### Bulk Operations

#### POST /escalations/bulk/assign
**Bulk Assignment:**

```json
{
  "escalation_ids": ["string"],
  "assign_to": "string",
  "reason": "string",
  "update_priority": "string?",
  "update_due_date": "string?"
}
```

#### POST /escalations/bulk/update-priority
**Bulk Priority Update:**

```json
{
  "escalation_ids": ["string"],
  "new_priority": "string",
  "reason": "string",
  "adjust_due_dates": "boolean?" // Adjust due dates based on new priority
}
```

### Analytics and Reporting

#### GET /escalations/analytics
**Get Escalation Analytics:**

**Query Parameters:**
```
start_date=2024-01-01T00:00:00Z
end_date=2024-01-31T23:59:59Z
granularity=day  # hour, day, week, month
group_by=agent,reason,priority  # Comma-separated
include_trends=true
```

**Response:**
```json
{
  "summary_metrics": {
    "total_escalations": "number",
    "avg_resolution_time_minutes": "number",
    "sla_compliance_rate": "number",
    "approval_rate": "number",
    "rejection_rate": "number",
    "return_to_draft_rate": "number"
  },
  
  "time_series": [
    {
      "timestamp": "string",
      "metrics": {
        "created": "number",
        "resolved": "number",
        "avg_resolution_time": "number",
        "sla_breaches": "number"
      }
    }
  ],
  
  "agent_performance": [
    {
      "agent_id": "string",
      "agent_name": "string",
      "total_handled": "number",
      "avg_resolution_time_minutes": "number",
      "approval_rate": "number",
      "sla_compliance_rate": "number",
      "quality_score": "number"
    }
  ],
  
  "escalation_reasons": [
    {
      "reason": "string",
      "count": "number",
      "avg_resolution_time": "number",
      "approval_rate": "number"
    }
  ],
  
  "trends": {
    "volume_trend": "string", // "increasing", "decreasing", "stable"
    "resolution_time_trend": "string",
    "sla_compliance_trend": "string"
  }
}
```

## State Transitions

### Escalation State Machine

#### States and Transitions
```javascript
const ESCALATION_STATES = {
  PENDING: 'pending',
  ASSIGNED: 'assigned',
  IN_REVIEW: 'in_review',
  DRAFT_EDITING: 'draft_editing',
  AWAITING_APPROVAL: 'awaiting_approval',
  APPROVED: 'approved',
  REJECTED: 'rejected',
  RETURNED: 'returned',
  ESCALATED_FURTHER: 'escalated_further',
  CANCELLED: 'cancelled',
  EXPIRED: 'expired'
};

const STATE_TRANSITIONS = {
  [ESCALATION_STATES.PENDING]: [
    ESCALATION_STATES.ASSIGNED,
    ESCALATION_STATES.CANCELLED,
    ESCALATION_STATES.EXPIRED
  ],
  
  [ESCALATION_STATES.ASSIGNED]: [
    ESCALATION_STATES.IN_REVIEW,
    ESCALATION_STATES.PENDING, // Reassignment
    ESCALATION_STATES.CANCELLED,
    ESCALATION_STATES.EXPIRED
  ],
  
  [ESCALATION_STATES.IN_REVIEW]: [
    ESCALATION_STATES.DRAFT_EDITING,
    ESCALATION_STATES.AWAITING_APPROVAL,
    ESCALATION_STATES.APPROVED, // Direct approval
    ESCALATION_STATES.REJECTED,
    ESCALATION_STATES.ESCALATED_FURTHER,
    ESCALATION_STATES.ASSIGNED // Reassignment
  ],
  
  [ESCALATION_STATES.DRAFT_EDITING]: [
    ESCALATION_STATES.AWAITING_APPROVAL,
    ESCALATION_STATES.IN_REVIEW,
    ESCALATION_STATES.ASSIGNED // Reassignment
  ],
  
  [ESCALATION_STATES.AWAITING_APPROVAL]: [
    ESCALATION_STATES.APPROVED,
    ESCALATION_STATES.RETURNED,
    ESCALATION_STATES.REJECTED,
    ESCALATION_STATES.DRAFT_EDITING
  ],
  
  [ESCALATION_STATES.RETURNED]: [
    ESCALATION_STATES.DRAFT_EDITING,
    ESCALATION_STATES.ASSIGNED,
    ESCALATION_STATES.CANCELLED
  ],
  
  // Terminal states
  [ESCALATION_STATES.APPROVED]: [],
  [ESCALATION_STATES.REJECTED]: [],
  [ESCALATION_STATES.ESCALATED_FURTHER]: [],
  [ESCALATION_STATES.CANCELLED]: [],
  [ESCALATION_STATES.EXPIRED]: []
};
```

#### State Machine Implementation
```javascript
class EscalationStateMachine {
  constructor(escalation) {
    this.escalation = escalation;
    this.currentState = escalation.status;
  }
  
  canTransitionTo(newState) {
    const allowedTransitions = STATE_TRANSITIONS[this.currentState] || [];
    return allowedTransitions.includes(newState);
  }
  
  async transitionTo(newState, context = {}) {
    if (!this.canTransitionTo(newState)) {
      throw new Error(`Invalid transition from ${this.currentState} to ${newState}`);
    }
    
    // Validate transition conditions
    await this.validateTransitionConditions(newState, context);
    
    // Execute pre-transition hooks
    await this.executePreTransitionHooks(newState, context);
    
    // Update state
    const previousState = this.currentState;
    this.currentState = newState;
    
    // Persist state change
    await this.persistStateChange(previousState, newState, context);
    
    // Execute post-transition hooks
    await this.executePostTransitionHooks(previousState, newState, context);
    
    return {
      previous_state: previousState,
      new_state: newState,
      transitioned_at: new Date().toISOString()
    };
  }
  
  async validateTransitionConditions(newState, context) {
    switch (newState) {
      case ESCALATION_STATES.APPROVED:
        if (!context.lock_id || !this.isValidLock(context.lock_id)) {
          throw new Error('Valid lock required for approval');
        }
        if (!context.final_review) {
          throw new Error('Final review required for approval');
        }
        break;
        
      case ESCALATION_STATES.DRAFT_EDITING:
        if (!context.lock_id) {
          throw new Error('Lock required for draft editing');
        }
        break;
        
      case ESCALATION_STATES.ASSIGNED:
        if (!context.assign_to) {
          throw new Error('Assignee required for assignment');
        }
        break;
    }
  }
  
  async executePreTransitionHooks(newState, context) {
    switch (newState) {
      case ESCALATION_STATES.APPROVED:
        // Validate draft content
        await this.validateDraftContent();
        // Check safety scores
        await this.validateSafetyScores();
        break;
        
      case ESCALATION_STATES.ASSIGNED:
        // Check agent availability
        await this.checkAgentAvailability(context.assign_to);
        // Update workload metrics
        await this.updateAgentWorkload(context.assign_to, 1);
        break;
        
      case ESCALATION_STATES.EXPIRED:
        // Send expiration notifications
        await this.sendExpirationNotifications();
        break;
    }
  }
  
  async executePostTransitionHooks(previousState, newState, context) {
    // Update SLA tracking
    await this.updateSLATracking(newState);
    
    // Send notifications
    await this.sendStateChangeNotifications(previousState, newState, context);
    
    // Update metrics
    await this.updateMetrics(previousState, newState);
    
    // Release locks if terminal state
    if (this.isTerminalState(newState)) {
      await this.releaseAllLocks();
    }
  }
  
  isTerminalState(state) {
    return [
      ESCALATION_STATES.APPROVED,
      ESCALATION_STATES.REJECTED,
      ESCALATION_STATES.ESCALATED_FURTHER,
      ESCALATION_STATES.CANCELLED,
      ESCALATION_STATES.EXPIRED
    ].includes(state);
  }
}
```

## Locking Model

### Optimistic Locking with Timeouts

#### Lock Manager
```javascript
class EscalationLockManager {
  constructor() {
    this.defaultLockDuration = 30 * 60 * 1000; // 30 minutes
    this.maxLockDuration = 2 * 60 * 60 * 1000; // 2 hours
    this.lockExtensionDuration = 15 * 60 * 1000; // 15 minutes
  }
  
  async acquireLock(escalationId, userId, reason, durationMs = null) {
    const lockDuration = Math.min(
      durationMs || this.defaultLockDuration,
      this.maxLockDuration
    );
    
    const lockId = this.generateLockId();
    const expiresAt = new Date(Date.now() + lockDuration);
    
    try {
      // Attempt to acquire lock atomically
      const result = await this.db.transaction(async (trx) => {
        // Check if already locked
        const existingLock = await trx('escalation_locks')
          .where('escalation_id', escalationId)
          .where('expires_at', '>', new Date())
          .first();
        
        if (existingLock) {
          throw new Error(`Escalation is locked by ${existingLock.locked_by} until ${existingLock.expires_at}`);
        }
        
        // Clean up expired locks
        await trx('escalation_locks')
          .where('escalation_id', escalationId)
          .where('expires_at', '<=', new Date())
          .del();
        
        // Create new lock
        await trx('escalation_locks').insert({
          id: lockId,
          escalation_id: escalationId,
          locked_by: userId,
          lock_reason: reason,
          locked_at: new Date(),
          expires_at: expiresAt
        });
        
        return {
          lock_id: lockId,
          expires_at: expiresAt,
          duration_ms: lockDuration
        };
      });
      
      // Schedule automatic cleanup
      this.scheduleAutomaticCleanup(lockId, lockDuration);
      
      return result;
      
    } catch (error) {
      if (error.message.includes('locked by')) {
        throw new ConflictError(error.message);
      }
      throw error;
    }
  }
  
  async extendLock(lockId, userId, extensionMs = null) {
    const extension = Math.min(
      extensionMs || this.lockExtensionDuration,
      this.maxLockDuration
    );
    
    const result = await this.db('escalation_locks')
      .where('id', lockId)
      .where('locked_by', userId)
      .where('expires_at', '>', new Date())
      .update({
        expires_at: this.db.raw('DATE_ADD(expires_at, INTERVAL ? MILLISECOND)', [extension]),
        extended_at: new Date()
      });
    
    if (result === 0) {
      throw new Error('Lock not found, expired, or not owned by user');
    }
    
    return {
      extended: true,
      new_expires_at: new Date(Date.now() + extension)
    };
  }
  
  async releaseLock(lockId, userId) {
    const result = await this.db('escalation_locks')
      .where('id', lockId)
      .where('locked_by', userId)
      .del();
    
    if (result === 0) {
      throw new Error('Lock not found or not owned by user');
    }
    
    return { released: true };
  }
  
  async validateLock(lockId, userId, escalationId) {
    const lock = await this.db('escalation_locks')
      .where('id', lockId)
      .where('escalation_id', escalationId)
      .where('locked_by', userId)
      .where('expires_at', '>', new Date())
      .first();
    
    if (!lock) {
      throw new Error('Invalid, expired, or unauthorized lock');
    }
    
    return {
      valid: true,
      expires_at: lock.expires_at,
      time_remaining_ms: new Date(lock.expires_at) - new Date()
    };
  }
  
  async forceReleaseLock(escalationId, adminUserId, reason) {
    const existingLock = await this.db('escalation_locks')
      .where('escalation_id', escalationId)
      .first();
    
    if (!existingLock) {
      return { released: false, reason: 'No lock found' };
    }
    
    // Log the force release
    await this.logLockEvent({
      escalation_id: escalationId,
      event_type: 'force_release',
      admin_user_id: adminUserId,
      original_lock_owner: existingLock.locked_by,
      reason: reason
    });
    
    await this.db('escalation_locks')
      .where('escalation_id', escalationId)
      .del();
    
    return {
      released: true,
      original_owner: existingLock.locked_by,
      reason: reason
    };
  }
  
  generateLockId() {
    return `lock_${Date.now()}_${Math.random().toString(36).substring(2)}`;
  }
}
```

### Conflict Resolution

#### Conflict Detector
```javascript
class ConflictDetector {
  async detectConflicts(escalationId, action, userId) {
    const conflicts = [];
    
    // Check for concurrent edits
    const recentEdits = await this.getRecentEdits(escalationId, 5 * 60 * 1000); // 5 minutes
    const concurrentEdits = recentEdits.filter(edit => 
      edit.user_id !== userId && 
      edit.action_type === 'edit_draft'
    );
    
    if (concurrentEdits.length > 0) {
      conflicts.push({
        type: 'concurrent_edit',
        severity: 'medium',
        description: `${concurrentEdits.length} other user(s) have edited this escalation recently`,
        conflicting_users: concurrentEdits.map(edit => edit.user_id),
        resolution_options: ['merge_changes', 'overwrite', 'cancel']
      });
    }
    
    // Check for double approvals
    if (action === 'approve') {
      const recentApprovals = await this.getRecentApprovals(escalationId, 1 * 60 * 1000); // 1 minute
      if (recentApprovals.length > 0) {
        conflicts.push({
          type: 'double_approval',
          severity: 'high',
          description: 'Another user has already approved this escalation',
          conflicting_users: recentApprovals.map(approval => approval.user_id),
          resolution_options: ['cancel', 'proceed_anyway']
        });
      }
    }
    
    // Check for state changes
    const currentState = await this.getCurrentState(escalationId);
    const expectedStates = this.getValidStatesForAction(action);
    
    if (!expectedStates.includes(currentState)) {
      conflicts.push({
        type: 'invalid_state',
        severity: 'high',
        description: `Escalation is in ${currentState} state, but action requires one of: ${expectedStates.join(', ')}`,
        current_state: currentState,
        expected_states: expectedStates,
        resolution_options: ['refresh', 'cancel']
      });
    }
    
    return conflicts;
  }
  
  async resolveConflict(escalationId, conflictType, resolution, userId) {
    switch (conflictType) {
      case 'concurrent_edit':
        return await this.resolveConcurrentEdit(escalationId, resolution, userId);
      case 'double_approval':
        return await this.resolveDoubleApproval(escalationId, resolution, userId);
      case 'invalid_state':
        return await this.resolveInvalidState(escalationId, resolution, userId);
      default:
        throw new Error(`Unknown conflict type: ${conflictType}`);
    }
  }
  
  async resolveConcurrentEdit(escalationId, resolution, userId) {
    switch (resolution) {
      case 'merge_changes':
        return await this.mergeChanges(escalationId, userId);
      case 'overwrite':
        return await this.overwriteChanges(escalationId, userId);
      case 'cancel':
        return { action: 'cancelled', reason: 'User chose to cancel due to conflicts' };
      default:
        throw new Error(`Invalid resolution for concurrent_edit: ${resolution}`);
    }
  }
  
  async mergeChanges(escalationId, userId) {
    // Implement three-way merge logic
    const baseVersion = await this.getBaseVersion(escalationId);
    const userChanges = await this.getUserChanges(escalationId, userId);
    const otherChanges = await this.getOtherChanges(escalationId, userId);
    
    const mergedContent = await this.performThreeWayMerge(
      baseVersion,
      userChanges,
      otherChanges
    );
    
    return {
      action: 'merged',
      merged_content: mergedContent,
      conflicts_resolved: true
    };
  }
}
```

## Audit Fields and Logging

### Comprehensive Audit Trail

#### Audit Logger
```javascript
class EscalationAuditLogger {
  async logAction(escalationId, action, userId, details = {}) {
    const auditEntry = {
      id: this.generateAuditId(),
      escalation_id: escalationId,
      user_id: userId,
      action: action,
      timestamp: new Date(),
      details: JSON.stringify(details),
      ip_address: details.ip_address,
      user_agent: details.user_agent,
      session_id: details.session_id
    };
    
    await this.db('escalation_audit_log').insert(auditEntry);
    
    // Also log to external audit system if configured
    if (this.externalAuditEnabled) {
      await this.sendToExternalAudit(auditEntry);
    }
    
    return auditEntry.id;
  }
  
  async logStateChange(escalationId, fromState, toState, userId, context = {}) {
    return await this.logAction(escalationId, 'state_change', userId, {
      from_state: fromState,
      to_state: toState,
      transition_context: context,
      timestamp: new Date().toISOString()
    });
  }
  
  async logDraftEdit(escalationId, userId, changes, version) {
    return await this.logAction(escalationId, 'draft_edit', userId, {
      changes: changes,
      version: version,
      fields_modified: Object.keys(changes),
      character_count_change: this.calculateCharacterChange(changes)
    });
  }
  
  async logApproval(escalationId, userId, approvalDetails) {
    return await this.logAction(escalationId, 'approval', userId, {
      approval_notes: approvalDetails.notes,
      final_review: approvalDetails.final_review,
      send_options: approvalDetails.send_options,
      safety_score: approvalDetails.safety_score
    });
  }
  
  async logLockOperation(escalationId, operation, userId, lockDetails) {
    return await this.logAction(escalationId, `lock_${operation}`, userId, {
      lock_id: lockDetails.lock_id,
      lock_reason: lockDetails.reason,
      duration_ms: lockDetails.duration_ms,
      expires_at: lockDetails.expires_at
    });
  }
  
  async getAuditTrail(escalationId, options = {}) {
    let query = this.db('escalation_audit_log')
      .where('escalation_id', escalationId)
      .orderBy('timestamp', 'desc');
    
    if (options.since) {
      query = query.where('timestamp', '>=', options.since);
    }
    
    if (options.actions) {
      query = query.whereIn('action', options.actions);
    }
    
    if (options.user_id) {
      query = query.where('user_id', options.user_id);
    }
    
    const entries = await query.limit(options.limit || 100);
    
    return entries.map(entry => ({
      ...entry,
      details: JSON.parse(entry.details || '{}')
    }));
  }
}
```

## Data Schemas

### escalations
```sql
CREATE TABLE escalations (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Source information
  processed_email_id VARCHAR(36) NOT NULL,
  account_id VARCHAR(36) NOT NULL,
  
  -- Escalation metadata
  escalation_reason VARCHAR(100) NOT NULL,
  escalation_source VARCHAR(50) NOT NULL, -- 'auto_rule', 'safety_check', 'manual', 'error_recovery'
  priority ENUM('high', 'medium', 'low') NOT NULL,
  
  -- Status and workflow
  status ENUM('pending', 'assigned', 'in_review', 'draft_editing', 'awaiting_approval', 'approved', 'rejected', 'returned', 'escalated_further', 'cancelled', 'expired') NOT NULL,
  
  -- Assignment
  assigned_to VARCHAR(36),
  assigned_at TIMESTAMP,
  assigned_by VARCHAR(36),
  
  -- SLA tracking
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  due_at TIMESTAMP NOT NULL,
  target_response_minutes INT NOT NULL,
  target_resolution_minutes INT NOT NULL,
  first_response_at TIMESTAMP,
  resolved_at TIMESTAMP,
  
  -- Content and context
  escalation_notes TEXT,
  context_data JSON, -- Additional context like customer tier, business impact
  
  -- Draft management
  current_draft_id VARCHAR(36),
  draft_version INT DEFAULT 1,
  
  -- Metrics
  view_count INT DEFAULT 0,
  edit_count INT DEFAULT 0,
  reassignment_count INT DEFAULT 0,
  
  -- Audit fields
  created_by VARCHAR(36),
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  updated_by VARCHAR(36),
  
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (assigned_to) REFERENCES users(id) ON DELETE SET NULL,
  FOREIGN KEY (current_draft_id) REFERENCES escalation_drafts(id) ON DELETE SET NULL,
  
  INDEX idx_status_priority (status, priority, due_at),
  INDEX idx_assigned_agent (assigned_to, status, due_at),
  INDEX idx_account_escalations (account_id, created_at),
  INDEX idx_sla_monitoring (due_at, status),
  INDEX idx_escalation_reason (escalation_reason, created_at),
  INDEX idx_performance (created_at, resolved_at)
);
```

### escalation_drafts
```sql
CREATE TABLE escalation_drafts (
  id VARCHAR(36) PRIMARY KEY,
  escalation_id VARCHAR(36) NOT NULL,
  
  -- Version control
  version INT NOT NULL,
  parent_version INT, -- For tracking edit history
  
  -- Draft content
  subject VARCHAR(500) NOT NULL,
  body_text TEXT NOT NULL,
  body_html TEXT,
  tone VARCHAR(50), -- 'professional', 'friendly', 'formal'
  
  -- Generation metadata
  template_used VARCHAR(36),
  prompt_used VARCHAR(36),
  llm_provider VARCHAR(50),
  llm_model VARCHAR(100),
  generation_time_ms INT,
  
  -- Quality metrics
  confidence_score DECIMAL(4,3),
  safety_score DECIMAL(4,3),
  
  -- Validation
  validation_status ENUM('pending', 'passed', 'failed', 'warning') DEFAULT 'pending',
  validation_issues JSON,
  requires_review_reasons JSON,
  
  -- Edit tracking
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(36) NOT NULL,
  edit_notes TEXT,
  
  -- Status
  is_current BOOLEAN DEFAULT FALSE,
  is_approved BOOLEAN DEFAULT FALSE,
  approved_at TIMESTAMP,
  approved_by VARCHAR(36),
  
  FOREIGN KEY (escalation_id) REFERENCES escalations(id) ON DELETE CASCADE,
  FOREIGN KEY (template_used) REFERENCES prompts(id) ON DELETE SET NULL,
  FOREIGN KEY (created_by) REFERENCES users(id) ON DELETE RESTRICT,
  FOREIGN KEY (approved_by) REFERENCES users(id) ON DELETE SET NULL,
  
  UNIQUE KEY unique_current_draft (escalation_id, is_current),
  INDEX idx_version_history (escalation_id, version),
  INDEX idx_approval_queue (validation_status, requires_review_reasons),
  INDEX idx_quality_metrics (confidence_score, safety_score)
);
```

### escalation_locks
```sql
CREATE TABLE escalation_locks (
  id VARCHAR(100) PRIMARY KEY,
  escalation_id VARCHAR(36) NOT NULL,
  
  -- Lock details
  locked_by VARCHAR(36) NOT NULL,
  lock_reason VARCHAR(50) NOT NULL, -- 'editing', 'reviewing', 'approving'
  
  -- Timing
  locked_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP NOT NULL,
  extended_at TIMESTAMP,
  
  -- Metadata
  client_info JSON, -- Browser, IP, etc.
  
  FOREIGN KEY (escalation_id) REFERENCES escalations(id) ON DELETE CASCADE,
  FOREIGN KEY (locked_by) REFERENCES users(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_escalation_lock (escalation_id),
  INDEX idx_expiration_cleanup (expires_at),
  INDEX idx_user_locks (locked_by, expires_at)
);
```

### escalation_audit_log
```sql
CREATE TABLE escalation_audit_log (
  id VARCHAR(36) PRIMARY KEY,
  escalation_id VARCHAR(36) NOT NULL,
  
  -- Action details
  user_id VARCHAR(36) NOT NULL,
  action VARCHAR(100) NOT NULL,
  timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Context
  details JSON,
  
  -- Session information
  ip_address VARCHAR(45),
  user_agent VARCHAR(1000),
  session_id VARCHAR(100),
  
  -- Compliance
  retention_until DATE, -- For compliance-based retention
  
  FOREIGN KEY (escalation_id) REFERENCES escalations(id) ON DELETE CASCADE,
  FOREIGN KEY (user_id) REFERENCES users(id) ON DELETE RESTRICT,
  
  INDEX idx_escalation_audit (escalation_id, timestamp),
  INDEX idx_user_activity (user_id, timestamp),
  INDEX idx_action_analysis (action, timestamp),
  INDEX idx_retention_cleanup (retention_until)
);
```

### escalation_sla_tracking
```sql
CREATE TABLE escalation_sla_tracking (
  id VARCHAR(36) PRIMARY KEY,
  escalation_id VARCHAR(36) NOT NULL,
  
  -- SLA definitions
  sla_tier VARCHAR(50) NOT NULL, -- 'standard', 'priority', 'vip'
  target_response_minutes INT NOT NULL,
  target_resolution_minutes INT NOT NULL,
  
  -- Timing milestones
  created_at TIMESTAMP NOT NULL,
  first_viewed_at TIMESTAMP,
  first_response_at TIMESTAMP,
  resolved_at TIMESTAMP,
  
  -- SLA status
  response_sla_status ENUM('on_time', 'at_risk', 'breached') DEFAULT 'on_time',
  resolution_sla_status ENUM('on_time', 'at_risk', 'breached') DEFAULT 'on_time',
  
  -- Breach details
  response_breach_minutes INT DEFAULT 0,
  resolution_breach_minutes INT DEFAULT 0,
  breach_reason VARCHAR(200),
  
  -- Notifications
  at_risk_notification_sent BOOLEAN DEFAULT FALSE,
  breach_notification_sent BOOLEAN DEFAULT FALSE,
  
  FOREIGN KEY (escalation_id) REFERENCES escalations(id) ON DELETE CASCADE,
  
  INDEX idx_sla_monitoring (response_sla_status, resolution_sla_status),
  INDEX idx_breach_analysis (response_breach_minutes, resolution_breach_minutes),
  INDEX idx_notification_queue (at_risk_notification_sent, breach_notification_sent)
);
```

## Edge Cases

### Stale Draft Handling
- **Auto-save conflicts**: Handle multiple users editing simultaneously
- **Version conflicts**: Merge or reject conflicting changes
- **Lock expiration**: Graceful handling of expired locks during editing
- **Network interruptions**: Recovery from connection losses during edits

### SLA Breach Scenarios
- **Timezone handling**: Proper SLA calculation across time zones
- **Holiday adjustments**: Business hours and holiday considerations
- **Escalation chains**: SLA inheritance when escalating further
- **Retroactive adjustments**: Handling SLA changes for existing escalations

### Conflict Resolution
- **Double approvals**: Prevent multiple simultaneous approvals
- **Concurrent assignments**: Handle race conditions in assignment
- **State synchronization**: Ensure UI reflects current state
- **Lock stealing**: Admin override capabilities

## Test Checklist

### State Machine Testing
- **Valid transitions**: Test all allowed state transitions
- **Invalid transitions**: Ensure blocked transitions throw errors
- **Concurrent state changes**: Handle race conditions
- **State persistence**: Verify state changes are properly saved

### Locking Mechanism
- **Lock acquisition**: Test successful lock creation
- **Lock conflicts**: Verify conflict detection and resolution
- **Lock expiration**: Automatic cleanup of expired locks
- **Lock extension**: Proper extension of active locks
- **Force release**: Admin override functionality

### Conflict Resolution
- **Concurrent edits**: Detection and resolution of edit conflicts
- **Double approvals**: Prevention of duplicate approvals
- **Version conflicts**: Proper handling of version mismatches
- **Merge algorithms**: Three-way merge functionality

### SLA Compliance
- **SLA calculation**: Accurate time tracking and breach detection
- **Notification timing**: Proper at-risk and breach notifications
- **Business hours**: Correct handling of business vs. calendar time
- **SLA adjustments**: Dynamic SLA updates

### Audit Trail
- **Complete logging**: All actions properly logged
- **Data integrity**: Audit log consistency and completeness
- **Performance impact**: Minimal overhead from audit logging
- **Retention compliance**: Proper audit log retention and cleanup

---

## Pragmatic Review

### ✅ Comprehensive Workflow Management
- Complete state machine with proper transition validation
- Rich escalation queue with preview, drafts, and recommendations
- Flexible assignment and reassignment capabilities
- SLA tracking with proactive breach prevention

### ✅ Robust Conflict Resolution
- Optimistic locking with timeout protection
- Conflict detection for concurrent operations
- Multiple resolution strategies (merge, overwrite, cancel)
- Admin override capabilities for emergency situations

### ✅ Detailed Audit and Compliance
- Comprehensive audit trail for all actions
- Complete state change tracking
- User activity monitoring
- Compliance-ready data retention

### ✅ Performance and Scalability
- Efficient querying with proper indexing
- Pagination for large queues
- Lock cleanup and maintenance
- Metrics and analytics for optimization

### Potential Issues Identified:
1. **Lock Contention**: High-traffic scenarios could cause lock conflicts
2. **State Synchronization**: Real-time updates across multiple clients
3. **Draft Storage**: Large drafts could impact database performance
4. **Notification Overhead**: High-volume SLA notifications could be expensive

### Recommended Fixes:
1. Implement lock queuing system for high contention
2. Add WebSocket support for real-time state updates
3. Consider separate storage for large draft content
4. Batch and throttle SLA notifications