# Part 7: Email Processed & Send Logs

## Overview
Design for comprehensive email processing logs and traceability system. Provides end-to-end visibility from email classification through rule matching, action execution, and send results. Includes advanced filtering, correlation, and export capabilities with proper PII handling.

## Goals
- **End-to-End Traceability**: Complete audit trail from classify → rule match → action → send result
- **Advanced Filtering**: Date ranges, accounts, rules, outcomes with export capabilities
- **Service Correlation**: Link data across classification, rules, and sending services
- **PII Protection**: Automatic redaction of sensitive information
- **Performance**: Efficient pagination and querying for large volumes
- **Data Retention**: Configurable retention policies with automated cleanup

## HTTP Interfaces

### Log Retrieval

#### GET /logs
**Get Processed Email Logs with Filtering:**

**Query Parameters:**
```
# Time filtering
start_date=2024-01-01T00:00:00Z
end_date=2024-01-31T23:59:59Z
relative_time=7d  # Alternative: 1h, 24h, 7d, 30d

# Entity filtering
account_id=uuid
rule_id=uuid
user_id=uuid

# Status filtering
processing_status=completed,failed  # Comma-separated
rule_outcome=auto_send,queue_human,skip
send_status=sent,failed,bounced

# Content filtering
subject_contains=urgent
from_email=user@domain.com
to_email=recipient@domain.com
has_attachments=true

# Classification filtering
intent=complaint,inquiry,request
category=billing,support,sales
sentiment=positive,negative,neutral
urgency=high,medium,low
risk_level=high,medium,low

# Pagination
page=1
page_size=50  # Max 1000
sort_by=processed_at  # processed_at, rule_match_score, send_time
sort_order=desc  # asc, desc

# Data inclusion
include_content=false  # Include email content (with PII redaction)
include_llm_details=true  # Include LLM classification details
include_rule_trace=true  # Include rule evaluation trace
include_send_details=true  # Include send attempt details

# Export options
export_format=json  # json, csv, xlsx
export_fields=basic,full,custom
custom_fields=processed_at,subject,rule_name,outcome
```

**Response:**
```json
{
  "logs": [
    {
      "id": "string",
      "processed_at": "string",
      "account_id": "string",
      "account_name": "string",
      
      "email": {
        "id": "string",
        "message_id": "string",
        "subject": "string", // PII redacted if needed
        "from_email": "string",
        "from_name": "string",
        "to_emails": ["string"],
        "received_at": "string",
        "folder": "string",
        "has_attachments": "boolean",
        "size_bytes": "number",
        "thread_id": "string?"
      },
      
      "classification": {
        "llm_request_id": "string",
        "provider": "string",
        "model": "string",
        "processing_time_ms": "number",
        "intent": "string",
        "intent_confidence": "number",
        "category": "string",
        "category_confidence": "number",
        "sentiment": "string",
        "sentiment_score": "number",
        "urgency": "string",
        "urgency_score": "number",
        "risk_assessment": {
          "level": "string",
          "score": "number",
          "factors": ["string"]
        },
        "key_entities": [
          {
            "type": "string",
            "value": "string", // PII redacted
            "confidence": "number"
          }
        ]
      },
      
      "rule_evaluation": {
        "total_rules_evaluated": "number",
        "evaluation_time_ms": "number",
        "matched_rule": {
          "id": "string",
          "name": "string",
          "version": "number",
          "match_score": "number",
          "conditions_met": [
            {
              "condition": "string",
              "field": "string",
              "operator": "string",
              "expected_value": "string",
              "actual_value": "string", // PII redacted
              "matched": "boolean"
            }
          ]
        },
        "other_matches": [
          {
            "rule_id": "string",
            "rule_name": "string",
            "match_score": "number",
            "skipped_reason": "string" // "lower_priority", "throttled"
          }
        ],
        "throttle_status": {
          "throttled": "boolean",
          "throttle_key": "string",
          "current_count": "number",
          "limit": "number",
          "reset_time": "string"
        }
      },
      
      "action_execution": {
        "action_type": "string", // "auto_send", "auto_draft", "queue_human", "skip", "label", "move"
        "outcome": "string", // "success", "failed", "skipped"
        "execution_time_ms": "number",
        "details": {
          // Action-specific details
          "draft_generated": "boolean?",
          "prompt_used": "string?",
          "send_initiated": "boolean?",
          "queue_assigned_to": "string?",
          "labels_applied": ["string"]?,
          "moved_to_folder": "string?"
        },
        "error_message": "string?"
      },
      
      "send_details": {
        "send_log_id": "string?",
        "compose_id": "string?",
        "status": "string?", // "queued", "sent", "failed", "cancelled"
        "recipients": [
          {
            "email": "string",
            "status": "string",
            "delivered_at": "string?",
            "bounce_type": "string?",
            "error": "string?"
          }
        ],
        "total_send_time_ms": "number?",
        "tracking_enabled": "boolean?",
        "safety_score": "number?"
      },
      
      "metadata": {
        "processing_version": "string",
        "correlation_id": "string",
        "user_id": "string?",
        "client_info": {
          "user_agent": "string?",
          "ip_address": "string?" // Anonymized
        }
      }
    }
  ],
  
  "pagination": {
    "current_page": "number",
    "page_size": "number",
    "total_pages": "number",
    "total_records": "number",
    "has_next": "boolean",
    "has_previous": "boolean",
    "next_cursor": "string?",
    "previous_cursor": "string?"
  },
  
  "aggregations": {
    "by_outcome": {
      "auto_send": "number",
      "auto_draft": "number",
      "queue_human": "number",
      "skip": "number"
    },
    "by_status": {
      "completed": "number",
      "failed": "number",
      "in_progress": "number"
    },
    "by_account": [
      {
        "account_id": "string",
        "account_name": "string",
        "count": "number"
      }
    ],
    "by_rule": [
      {
        "rule_id": "string",
        "rule_name": "string",
        "count": "number",
        "success_rate": "number"
      }
    ]
  },
  
  "query_performance": {
    "query_time_ms": "number",
    "records_scanned": "number",
    "indexes_used": ["string"]
  }
}
```

#### GET /logs/:id
**Get Detailed Log Entry:**

**Response:**
```json
{
  "log": {
    // Same structure as above but with additional details
    "email_content": {
      "body_text": "string", // PII redacted
      "body_html": "string?", // PII redacted
      "attachments": [
        {
          "filename": "string",
          "content_type": "string",
          "size_bytes": "number",
          "virus_scan_result": "string?"
        }
      ]
    },
    
    "llm_raw_response": {
      "request_payload": "object", // Sanitized
      "response_payload": "object",
      "tokens_used": "number",
      "cost_estimate": "number"
    },
    
    "rule_evaluation_trace": [
      {
        "step": "number",
        "rule_id": "string",
        "rule_name": "string",
        "evaluation_result": "boolean",
        "conditions_evaluated": [
          {
            "condition_id": "string",
            "field_path": "string",
            "operator": "string",
            "expected_value": "any",
            "actual_value": "any", // PII redacted
            "result": "boolean",
            "evaluation_time_ms": "number"
          }
        ],
        "short_circuit": "boolean",
        "execution_time_ms": "number"
      }
    ],
    
    "send_trace": {
      "compose_details": {
        "template_used": "string?",
        "variables_resolved": "object", // PII redacted
        "safety_checks": [
          {
            "check_type": "string",
            "passed": "boolean",
            "score": "number",
            "details": "string"
          }
        ]
      },
      "smtp_trace": [
        {
          "timestamp": "string",
          "event": "string",
          "details": "string",
          "smtp_response": "string?"
        }
      ]
    },
    
    "audit_trail": [
      {
        "timestamp": "string",
        "event": "string",
        "user_id": "string?",
        "details": "object"
      }
    ]
  }
}
```

### Export Operations

#### POST /logs/export
**Export Logs to File:**

```json
{
  "filters": {
    // Same filter options as GET /logs
  },
  "export_config": {
    "format": "csv", // "csv", "xlsx", "json"
    "fields": "custom", // "basic", "full", "custom"
    "custom_fields": [
      "processed_at",
      "account_name",
      "subject",
      "from_email",
      "classification.intent",
      "rule_evaluation.matched_rule.name",
      "action_execution.outcome",
      "send_details.status"
    ],
    "pii_handling": "redact", // "redact", "hash", "remove"
    "max_records": 10000,
    "include_headers": true,
    "date_format": "iso8601" // "iso8601", "excel", "unix"
  },
  "delivery": {
    "method": "download", // "download", "email", "webhook"
    "email_to": "user@domain.com?",
    "webhook_url": "https://api.example.com/webhook?",
    "expires_in_hours": 24
  }
}
```

**Response:**
```json
{
  "export_id": "string",
  "status": "queued", // "queued", "processing", "completed", "failed"
  "estimated_completion": "string",
  "download_url": "string?", // Available when completed
  "record_count": "number?",
  "file_size_bytes": "number?"
}
```

#### GET /logs/export/:id/status
**Check Export Status:**

```json
{
  "export_id": "string",
  "status": "completed",
  "created_at": "string",
  "completed_at": "string",
  "progress": {
    "records_processed": "number",
    "total_records": "number",
    "percentage": "number"
  },
  "result": {
    "download_url": "string",
    "expires_at": "string",
    "file_size_bytes": "number",
    "record_count": "number"
  },
  "error": "string?"
}
```

### Analytics and Aggregations

#### GET /logs/analytics
**Get Processing Analytics:**

**Query Parameters:**
```
start_date=2024-01-01T00:00:00Z
end_date=2024-01-31T23:59:59Z
granularity=day  # hour, day, week, month
account_id=uuid  # Optional filter
group_by=rule,outcome,account  # Comma-separated
```

**Response:**
```json
{
  "time_series": [
    {
      "timestamp": "string",
      "metrics": {
        "total_processed": "number",
        "auto_send_count": "number",
        "auto_draft_count": "number",
        "queue_human_count": "number",
        "skip_count": "number",
        "failed_count": "number",
        "avg_processing_time_ms": "number",
        "avg_llm_time_ms": "number",
        "avg_rule_eval_time_ms": "number"
      }
    }
  ],
  
  "aggregations": {
    "by_rule": [
      {
        "rule_id": "string",
        "rule_name": "string",
        "total_matches": "number",
        "success_rate": "number",
        "avg_match_score": "number",
        "outcomes": {
          "auto_send": "number",
          "auto_draft": "number",
          "queue_human": "number",
          "skip": "number"
        }
      }
    ],
    
    "by_classification": {
      "intent_distribution": {
        "complaint": "number",
        "inquiry": "number",
        "request": "number",
        "other": "number"
      },
      "sentiment_distribution": {
        "positive": "number",
        "neutral": "number",
        "negative": "number"
      },
      "urgency_distribution": {
        "high": "number",
        "medium": "number",
        "low": "number"
      }
    },
    
    "performance_metrics": {
      "avg_end_to_end_time_ms": "number",
      "p95_processing_time_ms": "number",
      "p99_processing_time_ms": "number",
      "error_rate": "number",
      "throughput_per_hour": "number"
    }
  }
}
```

## Aggregation Queries

### Time-Based Aggregations

#### Hourly Processing Volume
```sql
SELECT 
  DATE_FORMAT(processed_at, '%Y-%m-%d %H:00:00') as hour_bucket,
  COUNT(*) as total_processed,
  COUNT(CASE WHEN action_outcome = 'auto_send' THEN 1 END) as auto_send_count,
  COUNT(CASE WHEN action_outcome = 'auto_draft' THEN 1 END) as auto_draft_count,
  COUNT(CASE WHEN action_outcome = 'queue_human' THEN 1 END) as queue_human_count,
  COUNT(CASE WHEN action_outcome = 'skip' THEN 1 END) as skip_count,
  COUNT(CASE WHEN processing_status = 'failed' THEN 1 END) as failed_count,
  AVG(total_processing_time_ms) as avg_processing_time,
  AVG(llm_processing_time_ms) as avg_llm_time,
  AVG(rule_evaluation_time_ms) as avg_rule_time
FROM processed_emails 
WHERE processed_at >= ? AND processed_at < ?
GROUP BY hour_bucket
ORDER BY hour_bucket;
```

#### Daily Success Rates by Rule
```sql
SELECT 
  DATE(processed_at) as date,
  r.id as rule_id,
  r.name as rule_name,
  COUNT(*) as total_matches,
  COUNT(CASE WHEN action_outcome IN ('auto_send', 'auto_draft') AND processing_status = 'completed' THEN 1 END) as successful_actions,
  ROUND(COUNT(CASE WHEN action_outcome IN ('auto_send', 'auto_draft') AND processing_status = 'completed' THEN 1 END) * 100.0 / COUNT(*), 2) as success_rate,
  AVG(dt.match_score) as avg_match_score
FROM processed_emails pe
JOIN decision_trace dt ON pe.id = dt.processed_email_id
JOIN rules r ON dt.matched_rule_id = r.id
WHERE pe.processed_at >= ? AND pe.processed_at < ?
GROUP BY date, r.id, r.name
HAVING total_matches >= 5  -- Only include rules with significant volume
ORDER BY date DESC, success_rate DESC;
```

### Performance Analysis

#### Processing Time Percentiles
```sql
SELECT 
  account_id,
  a.name as account_name,
  COUNT(*) as total_processed,
  AVG(total_processing_time_ms) as avg_time,
  PERCENTILE_CONT(0.50) WITHIN GROUP (ORDER BY total_processing_time_ms) as p50_time,
  PERCENTILE_CONT(0.95) WITHIN GROUP (ORDER BY total_processing_time_ms) as p95_time,
  PERCENTILE_CONT(0.99) WITHIN GROUP (ORDER BY total_processing_time_ms) as p99_time,
  MAX(total_processing_time_ms) as max_time
FROM processed_emails pe
JOIN accounts a ON pe.account_id = a.id
WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 7 DAY)
GROUP BY account_id, a.name
ORDER BY avg_time DESC;
```

#### Error Analysis
```sql
SELECT 
  error_type,
  error_stage, -- 'classification', 'rule_evaluation', 'action_execution'
  COUNT(*) as error_count,
  COUNT(DISTINCT account_id) as affected_accounts,
  MIN(processed_at) as first_occurrence,
  MAX(processed_at) as last_occurrence,
  AVG(retry_count) as avg_retries
FROM processed_emails 
WHERE processing_status = 'failed'
  AND processed_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)
GROUP BY error_type, error_stage
ORDER BY error_count DESC;
```

### Classification Analytics

#### Intent Distribution Over Time
```sql
SELECT 
  DATE(processed_at) as date,
  classification_intent,
  COUNT(*) as count,
  AVG(classification_intent_confidence) as avg_confidence,
  COUNT(*) * 100.0 / SUM(COUNT(*)) OVER (PARTITION BY DATE(processed_at)) as percentage
FROM processed_emails 
WHERE processed_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
  AND classification_intent IS NOT NULL
GROUP BY date, classification_intent
ORDER BY date DESC, count DESC;
```

#### Rule Effectiveness Analysis
```sql
SELECT 
  r.id,
  r.name,
  r.priority,
  COUNT(dt.id) as total_evaluations,
  COUNT(CASE WHEN dt.matched = true THEN 1 END) as matches,
  ROUND(COUNT(CASE WHEN dt.matched = true THEN 1 END) * 100.0 / COUNT(dt.id), 2) as match_rate,
  AVG(CASE WHEN dt.matched = true THEN dt.match_score END) as avg_match_score,
  COUNT(CASE WHEN dt.matched = true AND pe.action_outcome = 'auto_send' THEN 1 END) as auto_send_count,
  COUNT(CASE WHEN dt.matched = true AND pe.processing_status = 'failed' THEN 1 END) as failure_count
FROM rules r
LEFT JOIN decision_trace dt ON r.id = dt.rule_id
LEFT JOIN processed_emails pe ON dt.processed_email_id = pe.id
WHERE r.active = true
  AND (dt.created_at IS NULL OR dt.created_at >= DATE_SUB(NOW(), INTERVAL 7 DAY))
GROUP BY r.id, r.name, r.priority
ORDER BY match_rate DESC, total_evaluations DESC;
```

## Pagination Strategy

### Cursor-Based Pagination

#### Implementation
```javascript
class LogPagination {
  constructor() {
    this.defaultPageSize = 50;
    this.maxPageSize = 1000;
  }
  
  async paginateResults(query, options = {}) {
    const pageSize = Math.min(options.pageSize || this.defaultPageSize, this.maxPageSize);
    const sortField = options.sortBy || 'processed_at';
    const sortOrder = options.sortOrder || 'desc';
    
    // Build base query
    let baseQuery = this.buildBaseQuery(query);
    
    // Add cursor conditions
    if (options.cursor) {
      const cursorData = this.decodeCursor(options.cursor);
      baseQuery = this.applyCursorConditions(baseQuery, cursorData, sortField, sortOrder);
    }
    
    // Add sorting and limit
    baseQuery = baseQuery
      .orderBy(sortField, sortOrder)
      .limit(pageSize + 1); // +1 to check if there are more results
    
    const results = await baseQuery;
    
    // Check if there are more results
    const hasMore = results.length > pageSize;
    if (hasMore) {
      results.pop(); // Remove the extra result
    }
    
    // Generate cursors
    const nextCursor = hasMore && results.length > 0 ? 
      this.encodeCursor(results[results.length - 1], sortField) : null;
    
    const previousCursor = options.cursor && results.length > 0 ? 
      this.encodeCursor(results[0], sortField, true) : null;
    
    return {
      data: results,
      pagination: {
        page_size: pageSize,
        has_next: hasMore,
        has_previous: !!options.cursor,
        next_cursor: nextCursor,
        previous_cursor: previousCursor
      }
    };
  }
  
  encodeCursor(record, sortField, reverse = false) {
    const cursorData = {
      id: record.id,
      sort_value: record[sortField],
      reverse: reverse
    };
    
    return Buffer.from(JSON.stringify(cursorData)).toString('base64');
  }
  
  decodeCursor(cursor) {
    try {
      const decoded = Buffer.from(cursor, 'base64').toString('utf8');
      return JSON.parse(decoded);
    } catch (error) {
      throw new Error('Invalid cursor format');
    }
  }
  
  applyCursorConditions(query, cursorData, sortField, sortOrder) {
    const { id, sort_value, reverse } = cursorData;
    
    if (reverse) {
      // For previous page
      if (sortOrder === 'desc') {
        return query.where(function() {
          this.where(sortField, '>', sort_value)
            .orWhere(function() {
              this.where(sortField, '=', sort_value)
                .andWhere('id', '>', id);
            });
        });
      } else {
        return query.where(function() {
          this.where(sortField, '<', sort_value)
            .orWhere(function() {
              this.where(sortField, '=', sort_value)
                .andWhere('id', '<', id);
            });
        });
      }
    } else {
      // For next page
      if (sortOrder === 'desc') {
        return query.where(function() {
          this.where(sortField, '<', sort_value)
            .orWhere(function() {
              this.where(sortField, '=', sort_value)
                .andWhere('id', '<', id);
            });
        });
      } else {
        return query.where(function() {
          this.where(sortField, '>', sort_value)
            .orWhere(function() {
              this.where(sortField, '=', sort_value)
                .andWhere('id', '>', id);
            });
        });
      }
    }
  }
}
```

### Offset-Based Pagination (for Analytics)

#### Implementation for Aggregated Data
```javascript
class AnalyticsPagination {
  async paginateAggregatedResults(query, options = {}) {
    const page = Math.max(1, options.page || 1);
    const pageSize = Math.min(options.pageSize || 50, 1000);
    const offset = (page - 1) * pageSize;
    
    // Get total count (cached for performance)
    const totalCount = await this.getCachedCount(query, options);
    
    // Get paginated results
    const results = await query
      .offset(offset)
      .limit(pageSize);
    
    const totalPages = Math.ceil(totalCount / pageSize);
    
    return {
      data: results,
      pagination: {
        current_page: page,
        page_size: pageSize,
        total_pages: totalPages,
        total_records: totalCount,
        has_next: page < totalPages,
        has_previous: page > 1
      }
    };
  }
  
  async getCachedCount(query, options) {
    const cacheKey = this.generateCountCacheKey(query, options);
    
    // Try to get from cache first
    let count = await this.cache.get(cacheKey);
    
    if (count === null) {
      // Calculate count and cache it
      const countQuery = query.clone().clearSelect().clearOrder().count('* as total');
      const result = await countQuery.first();
      count = result.total;
      
      // Cache for 5 minutes
      await this.cache.set(cacheKey, count, 300);
    }
    
    return count;
  }
}
```

## Retention Policy

### Retention Configuration

#### Retention Rules
```javascript
const RETENTION_POLICIES = {
  processed_emails: {
    default: '2 years',
    by_outcome: {
      'auto_send': '2 years',
      'auto_draft': '1 year',
      'queue_human': '3 years', // Longer for compliance
      'skip': '6 months',
      'failed': '1 year' // Keep failures longer for debugging
    },
    by_account_tier: {
      'enterprise': '5 years',
      'professional': '3 years',
      'basic': '1 year'
    }
  },
  
  decision_trace: {
    default: '1 year',
    high_value: '3 years' // Rules with high business impact
  },
  
  send_logs: {
    default: '2 years',
    bounced: '5 years', // Keep bounce data longer for reputation
    failed: '3 years'
  },
  
  llm_requests: {
    default: '6 months',
    errors: '1 year'
  },
  
  audit_logs: {
    default: '7 years' // Compliance requirement
  }
};
```

#### Retention Manager
```javascript
class RetentionManager {
  constructor() {
    this.policies = RETENTION_POLICIES;
    this.batchSize = 10000;
  }
  
  async executeRetentionPolicy() {
    console.log('Starting retention policy execution...');
    
    try {
      // Process each table
      await this.cleanupProcessedEmails();
      await this.cleanupDecisionTrace();
      await this.cleanupSendLogs();
      await this.cleanupLLMRequests();
      
      // Archive before deletion (optional)
      await this.archiveOldData();
      
      console.log('Retention policy execution completed');
    } catch (error) {
      console.error('Retention policy execution failed:', error);
      throw error;
    }
  }
  
  async cleanupProcessedEmails() {
    const cutoffDates = this.calculateCutoffDates('processed_emails');
    
    for (const [outcome, cutoffDate] of Object.entries(cutoffDates.by_outcome)) {
      const deletedCount = await this.deleteInBatches(
        'processed_emails',
        {
          action_outcome: outcome,
          processed_at: { '<': cutoffDate }
        }
      );
      
      console.log(`Deleted ${deletedCount} processed_emails with outcome ${outcome}`);
    }
  }
  
  async deleteInBatches(tableName, conditions) {
    let totalDeleted = 0;
    let hasMore = true;
    
    while (hasMore) {
      const batch = await this.db(tableName)
        .where(conditions)
        .limit(this.batchSize)
        .select('id');
      
      if (batch.length === 0) {
        hasMore = false;
        break;
      }
      
      const ids = batch.map(row => row.id);
      
      // Delete related data first (foreign key constraints)
      await this.deleteRelatedData(tableName, ids);
      
      // Delete main records
      const deleted = await this.db(tableName)
        .whereIn('id', ids)
        .del();
      
      totalDeleted += deleted;
      
      // Small delay to avoid overwhelming the database
      await this.sleep(100);
    }
    
    return totalDeleted;
  }
  
  async deleteRelatedData(tableName, parentIds) {
    if (tableName === 'processed_emails') {
      // Delete decision traces
      await this.db('decision_trace')
        .whereIn('processed_email_id', parentIds)
        .del();
      
      // Delete LLM requests
      await this.db('llm_requests')
        .whereIn('processed_email_id', parentIds)
        .del();
    }
  }
  
  calculateCutoffDates(tableName) {
    const policy = this.policies[tableName];
    const now = new Date();
    
    const cutoffDates = {
      default: this.subtractTime(now, policy.default)
    };
    
    if (policy.by_outcome) {
      cutoffDates.by_outcome = {};
      for (const [outcome, retention] of Object.entries(policy.by_outcome)) {
        cutoffDates.by_outcome[outcome] = this.subtractTime(now, retention);
      }
    }
    
    return cutoffDates;
  }
  
  subtractTime(date, timeString) {
    const [amount, unit] = timeString.split(' ');
    const num = parseInt(amount);
    
    const result = new Date(date);
    
    switch (unit) {
      case 'months':
      case 'month':
        result.setMonth(result.getMonth() - num);
        break;
      case 'years':
      case 'year':
        result.setFullYear(result.getFullYear() - num);
        break;
      case 'days':
      case 'day':
        result.setDate(result.getDate() - num);
        break;
    }
    
    return result;
  }
}
```

### Archival Strategy

#### Cold Storage Archive
```javascript
class DataArchiver {
  async archiveOldData(tableName, cutoffDate) {
    const archiveTable = `${tableName}_archive`;
    
    // Create archive table if it doesn't exist
    await this.createArchiveTable(tableName, archiveTable);
    
    // Move data to archive
    const movedCount = await this.moveToArchive(tableName, archiveTable, cutoffDate);
    
    // Compress archive data
    await this.compressArchiveData(archiveTable);
    
    return movedCount;
  }
  
  async createArchiveTable(sourceTable, archiveTable) {
    const exists = await this.db.schema.hasTable(archiveTable);
    
    if (!exists) {
      await this.db.schema.createTable(archiveTable, (table) => {
        // Copy structure from source table
        table.increments('archive_id').primary();
        table.timestamp('archived_at').defaultTo(this.db.fn.now());
        
        // Add all columns from source table
        // This would be dynamically generated based on source schema
      });
      
      // Add compression
      await this.db.raw(`ALTER TABLE ${archiveTable} ROW_FORMAT=COMPRESSED`);
    }
  }
  
  async moveToArchive(sourceTable, archiveTable, cutoffDate) {
    // Insert into archive
    const insertQuery = `
      INSERT INTO ${archiveTable} 
      SELECT *, NOW() as archived_at 
      FROM ${sourceTable} 
      WHERE processed_at < ?
    `;
    
    await this.db.raw(insertQuery, [cutoffDate]);
    
    // Delete from source
    const deletedCount = await this.db(sourceTable)
      .where('processed_at', '<', cutoffDate)
      .del();
    
    return deletedCount;
  }
}
```

## PII Redaction

### PII Detection and Redaction

#### PII Redactor
```javascript
class PIIRedactor {
  constructor() {
    this.patterns = {
      email: /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/g,
      phone: /\b(?:\+?1[-.]?)?\(?([0-9]{3})\)?[-.]?([0-9]{3})[-.]?([0-9]{4})\b/g,
      ssn: /\b\d{3}-?\d{2}-?\d{4}\b/g,
      credit_card: /\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3[0-9]{13}|6(?:011|5[0-9]{2})[0-9]{12})\b/g,
      ip_address: /\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/g,
      name: null // Would use NLP for name detection
    };
    
    this.redactionMethods = {
      mask: (text, pattern) => text.replace(pattern, (match) => '*'.repeat(match.length)),
      hash: (text, pattern) => text.replace(pattern, (match) => this.hashValue(match)),
      remove: (text, pattern) => text.replace(pattern, '[REDACTED]'),
      partial: (text, pattern) => text.replace(pattern, (match) => this.partialRedact(match))
    };
  }
  
  redactContent(content, options = {}) {
    const method = options.method || 'mask';
    const types = options.types || Object.keys(this.patterns);
    
    let redactedContent = content;
    
    for (const type of types) {
      const pattern = this.patterns[type];
      if (pattern) {
        redactedContent = this.redactionMethods[method](redactedContent, pattern);
      }
    }
    
    return redactedContent;
  }
  
  redactLogEntry(logEntry, options = {}) {
    const redacted = JSON.parse(JSON.stringify(logEntry)); // Deep clone
    
    // Redact email content
    if (redacted.email) {
      if (redacted.email.subject) {
        redacted.email.subject = this.redactContent(redacted.email.subject, options);
      }
    }
    
    // Redact classification entities
    if (redacted.classification?.key_entities) {
      redacted.classification.key_entities = redacted.classification.key_entities.map(entity => ({
        ...entity,
        value: this.redactContent(entity.value, options)
      }));
    }
    
    // Redact rule evaluation values
    if (redacted.rule_evaluation?.matched_rule?.conditions_met) {
      redacted.rule_evaluation.matched_rule.conditions_met = 
        redacted.rule_evaluation.matched_rule.conditions_met.map(condition => ({
          ...condition,
          actual_value: this.redactContent(String(condition.actual_value), options)
        }));
    }
    
    // Redact IP addresses
    if (redacted.metadata?.client_info?.ip_address) {
      redacted.metadata.client_info.ip_address = this.anonymizeIP(redacted.metadata.client_info.ip_address);
    }
    
    return redacted;
  }
  
  hashValue(value) {
    const crypto = require('crypto');
    return crypto.createHash('sha256').update(value).digest('hex').substring(0, 8);
  }
  
  partialRedact(value) {
    if (value.includes('@')) {
      // Email: show first 2 chars and domain
      const [local, domain] = value.split('@');
      return `${local.substring(0, 2)}***@${domain}`;
    }
    
    // Default: show first and last 2 chars
    if (value.length <= 4) return '*'.repeat(value.length);
    return `${value.substring(0, 2)}***${value.substring(value.length - 2)}`;
  }
  
  anonymizeIP(ip) {
    const parts = ip.split('.');
    if (parts.length === 4) {
      // IPv4: zero out last octet
      return `${parts[0]}.${parts[1]}.${parts[2]}.0`;
    }
    return '[REDACTED]';
  }
}
```

## Data Schemas

### processed_emails (Enhanced)
```sql
CREATE TABLE processed_emails (
  id VARCHAR(36) PRIMARY KEY,
  account_id VARCHAR(36) NOT NULL,
  
  -- Email identification
  email_id VARCHAR(36) NOT NULL, -- From emails table
  message_id VARCHAR(255) NOT NULL,
  thread_id VARCHAR(100),
  
  -- Processing metadata
  processed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  processing_version VARCHAR(20) NOT NULL,
  correlation_id VARCHAR(36) NOT NULL,
  
  -- Processing status
  processing_status ENUM('pending', 'in_progress', 'completed', 'failed', 'retrying') NOT NULL,
  
  -- Timing metrics
  total_processing_time_ms INT,
  llm_processing_time_ms INT,
  rule_evaluation_time_ms INT,
  action_execution_time_ms INT,
  
  -- Classification results
  classification_intent VARCHAR(50),
  classification_intent_confidence DECIMAL(4,3),
  classification_category VARCHAR(50),
  classification_category_confidence DECIMAL(4,3),
  classification_sentiment VARCHAR(20),
  classification_sentiment_score DECIMAL(4,3),
  classification_urgency VARCHAR(20),
  classification_urgency_score DECIMAL(4,3),
  classification_risk_level VARCHAR(20),
  classification_risk_score DECIMAL(4,3),
  
  -- Rule evaluation results
  matched_rule_id VARCHAR(36),
  rule_match_score DECIMAL(4,3),
  total_rules_evaluated INT,
  
  -- Action execution
  action_type VARCHAR(50), -- 'auto_send', 'auto_draft', 'queue_human', 'skip', 'label', 'move'
  action_outcome VARCHAR(50), -- 'success', 'failed', 'skipped'
  
  -- Error handling
  error_type VARCHAR(100),
  error_stage VARCHAR(50), -- 'classification', 'rule_evaluation', 'action_execution'
  error_message TEXT,
  retry_count INT DEFAULT 0,
  
  -- Audit fields
  user_id VARCHAR(36),
  client_ip VARCHAR(45),
  user_agent VARCHAR(500),
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (email_id) REFERENCES emails(id) ON DELETE CASCADE,
  FOREIGN KEY (matched_rule_id) REFERENCES rules(id) ON DELETE SET NULL,
  
  INDEX idx_processing_status (processing_status, processed_at),
  INDEX idx_account_processing (account_id, processed_at),
  INDEX idx_correlation (correlation_id),
  INDEX idx_classification (classification_intent, classification_category),
  INDEX idx_rule_matching (matched_rule_id, rule_match_score),
  INDEX idx_performance (total_processing_time_ms),
  INDEX idx_error_analysis (error_type, error_stage, processed_at)
);
```

### processing_trace (New)
```sql
CREATE TABLE processing_trace (
  id VARCHAR(36) PRIMARY KEY,
  processed_email_id VARCHAR(36) NOT NULL,
  
  -- Trace details
  stage VARCHAR(50) NOT NULL, -- 'classification', 'rule_evaluation', 'action_execution'
  step_name VARCHAR(100) NOT NULL,
  step_order INT NOT NULL,
  
  -- Timing
  started_at TIMESTAMP NOT NULL,
  completed_at TIMESTAMP,
  duration_ms INT,
  
  -- Status
  status ENUM('started', 'completed', 'failed', 'skipped') NOT NULL,
  
  -- Data
  input_data JSON,
  output_data JSON,
  error_data JSON,
  
  -- Metadata
  service_name VARCHAR(100),
  service_version VARCHAR(20),
  
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE CASCADE,
  
  INDEX idx_email_trace (processed_email_id, step_order),
  INDEX idx_stage_performance (stage, duration_ms),
  INDEX idx_error_tracking (status, stage)
);
```

### log_exports (New)
```sql
CREATE TABLE log_exports (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Export details
  requested_by VARCHAR(36) NOT NULL,
  requested_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Export configuration
  filters JSON NOT NULL,
  export_format ENUM('csv', 'xlsx', 'json') NOT NULL,
  fields_config JSON NOT NULL,
  pii_handling ENUM('redact', 'hash', 'remove') NOT NULL,
  
  -- Status
  status ENUM('queued', 'processing', 'completed', 'failed', 'expired') NOT NULL,
  
  -- Progress
  total_records INT,
  processed_records INT DEFAULT 0,
  
  -- Results
  file_path VARCHAR(500),
  file_size_bytes BIGINT,
  download_url VARCHAR(1000),
  expires_at TIMESTAMP,
  
  -- Timing
  started_at TIMESTAMP,
  completed_at TIMESTAMP,
  
  -- Error handling
  error_message TEXT,
  
  INDEX idx_user_exports (requested_by, requested_at),
  INDEX idx_processing_queue (status, requested_at),
  INDEX idx_cleanup (expires_at, status)
);
```

## Edge Cases

### Large Volume Handling
- **Query Optimization**: Proper indexing and query planning for millions of records
- **Memory Management**: Streaming results for large exports
- **Rate Limiting**: Prevent abuse of export functionality
- **Resource Limits**: Timeout protection for long-running queries

### Data Consistency
- **Cross-Service Correlation**: Handle cases where related data is missing
- **Partial Failures**: Log incomplete processing chains
- **Race Conditions**: Handle concurrent processing of same email
- **Schema Evolution**: Backward compatibility for different processing versions

### PII Compliance
- **Dynamic PII Detection**: Handle new PII patterns
- **Redaction Levels**: Different redaction for different user roles
- **Audit Trail**: Track who accessed what data
- **Right to Deletion**: Support for GDPR deletion requests

## Test Checklist

### Correlation Testing
- **End-to-End Tracing**: Verify complete processing chain visibility
- **Cross-Service Data**: Ensure all related data is properly linked
- **Missing Data Handling**: Graceful degradation when related data is unavailable
- **Timing Correlation**: Verify timestamp consistency across services

### PII Redaction Testing
- **Pattern Detection**: Test various PII formats and edge cases
- **Redaction Methods**: Verify mask, hash, remove, and partial methods
- **Nested Data**: Ensure PII in JSON fields is properly redacted
- **Performance Impact**: Measure redaction overhead

### Pagination Performance
- **Large Datasets**: Test with millions of records
- **Complex Filters**: Performance with multiple filter combinations
- **Cursor Stability**: Ensure cursors remain valid during data changes
- **Memory Usage**: Monitor memory consumption during pagination

### Export Functionality
- **Format Validation**: Test CSV, Excel, and JSON exports
- **Large Exports**: Handle exports with 100k+ records
- **Timeout Handling**: Proper cleanup of failed exports
- **File Security**: Ensure exported files are properly secured

### Retention Policy
- **Policy Execution**: Verify correct data deletion based on rules
- **Foreign Key Handling**: Ensure related data is properly cleaned up
- **Archive Process**: Test data archival before deletion
- **Recovery Testing**: Verify ability to restore archived data

---

## Pragmatic Review

### ✅ Comprehensive Traceability
- Complete end-to-end visibility from classification to send results
- Detailed correlation across all processing services
- Rich filtering and search capabilities
- Performance metrics and analytics

### ✅ Scalable Architecture
- Cursor-based pagination for large datasets
- Efficient aggregation queries with proper indexing
- Configurable retention policies with automated cleanup
- Export functionality with multiple formats

### ✅ Privacy and Compliance
- Comprehensive PII detection and redaction
- Multiple redaction methods (mask, hash, remove, partial)
- Audit trail for data access
- GDPR-compliant data handling

### ✅ Operational Excellence
- Performance monitoring and optimization
- Error analysis and debugging capabilities
- Resource management and rate limiting
- Automated maintenance and cleanup

### Potential Issues Identified:
1. **Query Performance**: Complex joins across multiple tables could be slow
2. **Storage Growth**: Detailed logging could consume significant storage
3. **Export Security**: Downloaded files need proper access controls
4. **Real-time Updates**: No real-time notification system for log updates

### Recommended Fixes:
1. Implement read replicas for analytics queries
2. Add data compression and tiered storage
3. Implement signed URLs with expiration for exports
4. Add WebSocket support for real-time log streaming