# Part 3: Prompt Library

## Overview
Design for the Prompt Library that manages LLM prompt templates with versioning, variable substitution, intent/category mapping, and audit traceability.

## HTTP Interfaces

### Prompt CRUD Operations

#### GET /prompts
**Query Parameters:**
```
?category=support|sales|billing|general
&intent=question|complaint|request|praise
&status=active|inactive|draft|archived
&version=latest|all
&account_id=uuid
&search=string
&limit=50
&offset=0
&sort=name|updated_at|usage_count
&order=asc|desc
```

**Response:**
```json
{
  "prompts": [
    {
      "id": "string",
      "name": "string",
      "description": "string",
      "category": "string",
      "intent": "string[]", // Multiple intents supported
      "version": "number",
      "status": "string",
      "account_id": "string?", // null = global prompt
      
      "template": {
        "system_prompt": "string",
        "user_prompt": "string",
        "variables": [
          {
            "name": "string",
            "type": "string", // "string", "number", "boolean", "email", "date"
            "required": "boolean",
            "default_value": "any?",
            "description": "string",
            "validation": {
              "min_length": "number?",
              "max_length": "number?",
              "pattern": "string?", // regex
              "allowed_values": "string[]?"
            }
          }
        ],
        "output_format": {
          "type": "json|text|markdown",
          "schema": "object?", // JSON schema for structured output
          "max_tokens": "number",
          "temperature": "number", // 0.0-2.0
          "stop_sequences": "string[]?"
        }
      },
      
      "metadata": {
        "created_at": "string",
        "updated_at": "string",
        "created_by": "string",
        "parent_version_id": "string?", // For version tracking
        "tags": "string[]",
        "usage_count": "number",
        "success_rate": "number", // 0.0-1.0
        "avg_response_time_ms": "number",
        "last_used": "string?"
      },
      
      "audit": {
        "version_notes": "string?",
        "approval_status": "string", // "pending", "approved", "rejected"
        "approved_by": "string?",
        "approved_at": "string?",
        "test_results": {
          "passed": "number",
          "failed": "number",
          "last_test_at": "string?"
        }
      }
    }
  ],
  "total": "number",
  "has_more": "boolean"
}
```

#### POST /prompts
```json
{
  "name": "string",
  "description": "string?",
  "category": "string",
  "intent": "string[]",
  "account_id": "string?",
  
  "template": {
    "system_prompt": "string",
    "user_prompt": "string",
    "variables": [
      {
        "name": "string",
        "type": "string",
        "required": "boolean",
        "default_value": "any?",
        "description": "string",
        "validation": "object?"
      }
    ],
    "output_format": {
      "type": "string",
      "schema": "object?",
      "max_tokens": "number",
      "temperature": "number",
      "stop_sequences": "string[]?"
    }
  },
  
  "tags": "string[]?",
  "version_notes": "string?",
  "status": "string?" // Default: "draft"
}
```

#### PUT /prompts/:id
```json
{
  "name": "string?",
  "description": "string?",
  "category": "string?",
  "intent": "string[]?",
  "template": "object?",
  "tags": "string[]?",
  "status": "string?",
  "version_notes": "string?"
}
```

#### POST /prompts/:id/versions
**Create New Version:**
```json
{
  "template": "object", // New template content
  "version_notes": "string",
  "copy_from_version": "number?", // Base version to copy from
  "status": "string?" // Default: "draft"
}
```

#### GET /prompts/:id/versions
**List All Versions:**
```json
{
  "versions": [
    {
      "version": "number",
      "status": "string",
      "created_at": "string",
      "created_by": "string",
      "version_notes": "string",
      "usage_count": "number",
      "is_current": "boolean"
    }
  ]
}
```

### Prompt Testing & Preview

#### POST /prompts/preview
```json
{
  "template": {
    "system_prompt": "string",
    "user_prompt": "string",
    "variables": "object[]",
    "output_format": "object"
  },
  "test_data": {
    "email_context": {
      "sender": "string",
      "subject": "string",
      "body": "string",
      "thread_history": "string?"
    },
    "variable_values": "object", // Variable name -> value mapping
    "llm_provider": "string?" // "openai", "gemini"
  },
  "options": {
    "dry_run": "boolean", // Don't actually call LLM
    "show_compiled_prompt": "boolean",
    "validate_only": "boolean"
  }
}
```

**Response:**
```json
{
  "compiled_prompt": {
    "system_prompt": "string",
    "user_prompt": "string",
    "total_tokens": "number",
    "estimated_cost": "number"
  },
  "validation": {
    "valid": "boolean",
    "errors": [
      {
        "field": "string",
        "message": "string",
        "severity": "error|warning"
      }
    ],
    "warnings": "string[]"
  },
  "llm_response": {
    "content": "string",
    "tokens_used": "number",
    "response_time_ms": "number",
    "provider": "string",
    "model": "string",
    "cost": "number"
  }?,
  "parsed_output": "object?", // If JSON output format
  "quality_score": "number?", // 0.0-1.0 based on heuristics
}
```

#### POST /prompts/:id/test
```json
{
  "test_cases": [
    {
      "name": "string",
      "email_context": "object",
      "variable_values": "object",
      "expected_output": {
        "contains": "string[]?", // Response should contain these strings
        "not_contains": "string[]?", // Response should not contain these
        "json_schema": "object?", // Validate JSON structure
        "sentiment": "positive|neutral|negative?",
        "max_tokens": "number?",
        "min_quality_score": "number?"
      }
    }
  ],
  "options": {
    "llm_provider": "string?",
    "parallel_execution": "boolean", // Default: false
    "save_results": "boolean" // Default: true
  }
}
```

**Response:**
```json
{
  "test_run_id": "string",
  "results": [
    {
      "test_case_name": "string",
      "passed": "boolean",
      "llm_response": "string",
      "execution_time_ms": "number",
      "cost": "number",
      "assertions": [
        {
          "type": "string",
          "expected": "any",
          "actual": "any",
          "passed": "boolean",
          "message": "string?"
        }
      ]
    }
  ],
  "summary": {
    "total_tests": "number",
    "passed": "number",
    "failed": "number",
    "total_cost": "number",
    "avg_response_time_ms": "number"
  }
}
```

### Intent & Category Mapping

#### GET /prompts/mapping
**Get Intent/Category to Prompt Mapping:**
```json
{
  "mappings": {
    "support": {
      "question": [
        {
          "prompt_id": "string",
          "prompt_name": "string",
          "priority": "number",
          "conditions": {
            "llm_confidence_min": "number?",
            "sender_domain_whitelist": "string[]?",
            "business_hours_only": "boolean?"
          }
        }
      ],
      "complaint": [...],
      "request": [...]
    },
    "sales": {
      "inquiry": [...],
      "demo_request": [...]
    }
  },
  "fallback_prompts": {
    "support": "prompt_id",
    "sales": "prompt_id",
    "general": "prompt_id"
  }
}
```

#### POST /prompts/mapping
**Update Mapping:**
```json
{
  "category": "string",
  "intent": "string",
  "prompt_mappings": [
    {
      "prompt_id": "string",
      "priority": "number", // 1-100, lower = higher priority
      "conditions": {
        "llm_confidence_min": "number?",
        "sender_domain_whitelist": "string[]?",
        "business_hours_only": "boolean?",
        "account_ids": "string[]?", // Account-specific mapping
        "custom_conditions": "object?"
      }
    }
  ]
}
```

## Template Specification

### Variable System

#### Built-in Variables
```json
{
  "email_variables": {
    "{{sender_name}}": "Display name of sender",
    "{{sender_email}}": "Email address of sender",
    "{{sender_domain}}": "Domain part of sender email",
    "{{recipient_name}}": "Display name of recipient",
    "{{recipient_email}}": "Email address of recipient",
    "{{subject}}": "Email subject line",
    "{{body}}": "Email body content",
    "{{body_preview}}": "First 500 characters of body",
    "{{thread_history}}": "Previous emails in thread",
    "{{attachment_count}}": "Number of attachments",
    "{{attachment_names}}": "List of attachment filenames"
  },
  "temporal_variables": {
    "{{current_date}}": "Current date (YYYY-MM-DD)",
    "{{current_time}}": "Current time (HH:MM)",
    "{{current_datetime}}": "Current datetime (ISO 8601)",
    "{{business_hours}}": "true/false if current time is business hours",
    "{{timezone}}": "Account timezone",
    "{{day_of_week}}": "Monday, Tuesday, etc.",
    "{{is_weekend}}": "true/false if weekend"
  },
  "account_variables": {
    "{{account_name}}": "Account/company name",
    "{{account_domain}}": "Primary domain for account",
    "{{support_email}}": "Support email address",
    "{{support_phone}}": "Support phone number",
    "{{website_url}}": "Company website URL",
    "{{signature}}": "Email signature template"
  },
  "llm_variables": {
    "{{llm_intent}}": "Classified intent",
    "{{llm_category}}": "Classified category",
    "{{llm_confidence}}": "Classification confidence (0.0-1.0)",
    "{{llm_sentiment}}": "Detected sentiment",
    "{{llm_urgency}}": "Detected urgency level",
    "{{llm_language}}": "Detected language",
    "{{llm_summary}}": "Brief summary of email content"
  }
}
```

#### Custom Variables
```json
{
  "variable_definition": {
    "name": "customer_tier",
    "type": "string",
    "required": true,
    "default_value": "standard",
    "description": "Customer service tier level",
    "validation": {
      "allowed_values": ["basic", "standard", "premium", "enterprise"]
    },
    "source": {
      "type": "database_lookup", // "static", "database_lookup", "api_call", "computed"
      "query": "SELECT tier FROM customers WHERE email = {{sender_email}}",
      "fallback_value": "standard",
      "cache_ttl_seconds": 3600
    }
  }
}
```

### Template Syntax

#### Basic Substitution
```
System Prompt:
You are a helpful customer support assistant for {{account_name}}. 
The current time is {{current_datetime}} ({{timezone}}).
Respond professionally and helpfully.

User Prompt:
Customer {{sender_name}} ({{sender_email}}) sent this email:

Subject: {{subject}}
Body: {{body}}

The email was classified as: {{llm_category}}/{{llm_intent}} with {{llm_confidence}} confidence.

Please draft a helpful response.
```

#### Conditional Logic
```
{{#if business_hours}}
Thank you for contacting us during business hours. We'll respond promptly.
{{else}}
Thank you for your email. While our office is currently closed, we'll respond first thing tomorrow.
{{/if}}

{{#if llm_urgency == "high"}}
We understand this is urgent and will prioritize your request.
{{/if}}

{{#unless thread_history}}
Welcome! This appears to be your first email to us.
{{/unless}}
```

#### Loops & Arrays
```
{{#if attachment_count > 0}}
We received the following attachments:
{{#each attachment_names}}
- {{this}}
{{/each}}
{{/if}}

{{#if customer_tier == "premium"}}
As a premium customer, you have access to:
{{#each premium_features}}
- {{name}}: {{description}}
{{/each}}
{{/if}}
```

#### Filters & Formatting
```
Customer since: {{customer_since | date:"MMMM YYYY"}}
Email received: {{current_datetime | date:"h:mm A on MMMM Do, YYYY"}}
Sender name: {{sender_name | title_case}}
Body preview: {{body | truncate:200 | strip_html}}
Confidence: {{llm_confidence | percentage:1}}
```

### Output Format Specifications

#### JSON Output
```json
{
  "output_format": {
    "type": "json",
    "schema": {
      "type": "object",
      "properties": {
        "response_text": {
          "type": "string",
          "description": "The email response content"
        },
        "confidence": {
          "type": "number",
          "minimum": 0,
          "maximum": 1,
          "description": "Confidence in the response quality"
        },
        "requires_human_review": {
          "type": "boolean",
          "description": "Whether human review is recommended"
        },
        "suggested_actions": {
          "type": "array",
          "items": {
            "type": "string",
            "enum": ["send_immediately", "schedule_followup", "escalate_to_manager", "add_to_knowledge_base"]
          }
        },
        "metadata": {
          "type": "object",
          "properties": {
            "tone": {"type": "string"},
            "estimated_resolution_time": {"type": "string"},
            "related_articles": {
              "type": "array",
              "items": {"type": "string"}
            }
          }
        }
      },
      "required": ["response_text", "confidence"]
    },
    "max_tokens": 1000,
    "temperature": 0.7
  }
}
```

#### Text Output
```json
{
  "output_format": {
    "type": "text",
    "max_tokens": 500,
    "temperature": 0.8,
    "stop_sequences": ["\n\n---\n", "Best regards,"],
    "post_processing": {
      "trim_whitespace": true,
      "remove_empty_lines": true,
      "add_signature": true,
      "format_as_html": false
    }
  }
}
```

## Versioning System

### Version Strategy
- **Semantic Versioning**: Major.Minor.Patch (e.g., 2.1.3)
- **Auto-increment**: System auto-increments based on change type
- **Change Detection**: Automatic classification of changes
- **Rollback Support**: Can revert to any previous version

### Change Classification
```javascript
function classifyChange(oldTemplate, newTemplate) {
  const changes = {
    major: false,    // Breaking changes
    minor: false,    // New features
    patch: false     // Bug fixes
  };
  
  // Major changes (breaking)
  if (variablesRemoved(oldTemplate, newTemplate)) changes.major = true;
  if (outputFormatChanged(oldTemplate, newTemplate)) changes.major = true;
  if (requiredVariablesAdded(oldTemplate, newTemplate)) changes.major = true;
  
  // Minor changes (additive)
  if (variablesAdded(oldTemplate, newTemplate)) changes.minor = true;
  if (newConditionalLogic(oldTemplate, newTemplate)) changes.minor = true;
  if (enhancedValidation(oldTemplate, newTemplate)) changes.minor = true;
  
  // Patch changes (fixes)
  if (typosFixed(oldTemplate, newTemplate)) changes.patch = true;
  if (formattingImproved(oldTemplate, newTemplate)) changes.patch = true;
  
  return changes;
}
```

### Version Lifecycle
```
Draft → Testing → Approved → Active → Deprecated → Archived
  ↓       ↓         ↓         ↓         ↓          ↓
 Edit   Test     Review    Deploy   Sunset    Delete
```

### Deployment Strategy
- **Gradual Rollout**: New versions start with 5% traffic
- **A/B Testing**: Compare performance against previous version
- **Auto-rollback**: Revert if error rate > 10% or quality drops
- **Manual Override**: Admins can force immediate deployment

## Data Schemas

### prompts
```sql
CREATE TABLE prompts (
  id VARCHAR(36) PRIMARY KEY,
  name VARCHAR(255) NOT NULL,
  description TEXT,
  
  -- Classification
  category ENUM('support', 'sales', 'billing', 'general', 'custom') NOT NULL,
  intent_json JSON, -- Array of supported intents
  account_id VARCHAR(36), -- NULL = global prompt
  
  -- Versioning
  version_major INT NOT NULL DEFAULT 1,
  version_minor INT NOT NULL DEFAULT 0,
  version_patch INT NOT NULL DEFAULT 0,
  parent_version_id VARCHAR(36), -- Previous version
  
  -- Status
  status ENUM('draft', 'testing', 'approved', 'active', 'deprecated', 'archived') DEFAULT 'draft',
  
  -- Template content
  system_prompt TEXT NOT NULL,
  user_prompt TEXT NOT NULL,
  variables_json JSON, -- Variable definitions
  output_format_json JSON, -- Output format specification
  
  -- Configuration
  max_tokens INT DEFAULT 1000,
  temperature DECIMAL(3,2) DEFAULT 0.70,
  stop_sequences_json JSON,
  
  -- Metadata
  tags_json JSON, -- Array of tags
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by VARCHAR(36) NOT NULL,
  
  -- Audit
  version_notes TEXT,
  approved_by VARCHAR(36),
  approved_at TIMESTAMP NULL,
  
  -- Statistics (updated by triggers)
  usage_count INT DEFAULT 0,
  success_count INT DEFAULT 0,
  failure_count INT DEFAULT 0,
  avg_response_time_ms DECIMAL(8,2),
  avg_cost DECIMAL(10,6),
  last_used TIMESTAMP NULL,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  FOREIGN KEY (approved_by) REFERENCES users(id),
  FOREIGN KEY (parent_version_id) REFERENCES prompts(id),
  
  UNIQUE KEY unique_name_account_version (name, account_id, version_major, version_minor, version_patch),
  INDEX idx_category_intent (category, intent_json(50)),
  INDEX idx_status_usage (status, usage_count),
  INDEX idx_account_active (account_id, status),
  INDEX idx_version_tree (parent_version_id, version_major, version_minor, version_patch)
);
```

### prompt_mappings
```sql
CREATE TABLE prompt_mappings (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Mapping key
  category VARCHAR(50) NOT NULL,
  intent VARCHAR(50) NOT NULL,
  account_id VARCHAR(36), -- NULL = global mapping
  
  -- Target prompt
  prompt_id VARCHAR(36) NOT NULL,
  priority INT NOT NULL DEFAULT 100, -- Lower = higher priority
  
  -- Conditions
  conditions_json JSON, -- When this mapping applies
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  created_by VARCHAR(36) NOT NULL,
  
  -- Statistics
  usage_count INT DEFAULT 0,
  success_rate DECIMAL(5,4), -- 0.0000-1.0000
  
  FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  
  UNIQUE KEY unique_mapping (category, intent, account_id, prompt_id),
  INDEX idx_category_intent_priority (category, intent, account_id, priority),
  INDEX idx_prompt_usage (prompt_id, usage_count)
);
```

### prompt_test_runs
```sql
CREATE TABLE prompt_test_runs (
  id VARCHAR(36) PRIMARY KEY,
  prompt_id VARCHAR(36) NOT NULL,
  prompt_version VARCHAR(20) NOT NULL, -- "1.2.3"
  
  -- Test configuration
  test_name VARCHAR(255),
  test_cases_json JSON, -- Array of test cases
  llm_provider VARCHAR(50),
  
  -- Results
  total_tests INT NOT NULL,
  passed_tests INT NOT NULL,
  failed_tests INT NOT NULL,
  total_cost DECIMAL(10,6),
  avg_response_time_ms DECIMAL(8,2),
  
  -- Detailed results
  results_json JSON, -- Full test results
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  created_by VARCHAR(36) NOT NULL,
  
  FOREIGN KEY (prompt_id) REFERENCES prompts(id) ON DELETE CASCADE,
  FOREIGN KEY (created_by) REFERENCES users(id),
  
  INDEX idx_prompt_tests (prompt_id, created_at),
  INDEX idx_test_results (passed_tests, failed_tests, created_at)
);
```

### prompt_usage_logs
```sql
CREATE TABLE prompt_usage_logs (
  id VARCHAR(36) PRIMARY KEY,
  prompt_id VARCHAR(36) NOT NULL,
  
  -- Context
  processed_email_id VARCHAR(36), -- Link to email that triggered this
  rule_id VARCHAR(36), -- Rule that selected this prompt
  
  -- Execution details
  compiled_prompt_hash VARCHAR(64), -- SHA256 of final compiled prompt
  variable_values_json JSON, -- Variable values used
  llm_provider VARCHAR(50),
  llm_model VARCHAR(100),
  
  -- Results
  response_text TEXT,
  response_json JSON, -- If structured output
  tokens_used INT,
  response_time_ms INT,
  cost DECIMAL(10,6),
  
  -- Quality metrics
  success BOOLEAN, -- Whether execution succeeded
  error_message TEXT,
  quality_score DECIMAL(3,2), -- 0.00-1.00
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  account_id VARCHAR(36) NOT NULL,
  
  FOREIGN KEY (prompt_id) REFERENCES prompts(id),
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE SET NULL,
  FOREIGN KEY (rule_id) REFERENCES rules(id) ON DELETE SET NULL,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  
  INDEX idx_prompt_performance (prompt_id, success, created_at),
  INDEX idx_email_prompts (processed_email_id),
  INDEX idx_account_usage (account_id, created_at),
  INDEX idx_cost_analysis (llm_provider, cost, created_at)
);
```

## Variable Policy & Security

### Variable Sources
```json
{
  "source_types": {
    "static": {
      "description": "Hard-coded values in template",
      "security_level": "safe",
      "examples": ["company_name", "support_hours"]
    },
    "email_context": {
      "description": "Extracted from current email",
      "security_level": "safe",
      "sanitization": "html_escape",
      "examples": ["sender_name", "subject", "body"]
    },
    "database_lookup": {
      "description": "Query internal database",
      "security_level": "medium",
      "restrictions": ["read_only", "parameterized_queries", "timeout_5s"],
      "examples": ["customer_tier", "account_balance"]
    },
    "api_call": {
      "description": "External API request",
      "security_level": "high",
      "restrictions": ["whitelist_domains", "timeout_3s", "rate_limit"],
      "examples": ["weather_data", "stock_price"]
    },
    "computed": {
      "description": "Calculated from other variables",
      "security_level": "safe",
      "examples": ["days_since_signup", "response_urgency"]
    }
  }
}
```

### Sanitization Rules
```javascript
const sanitizationRules = {
  // HTML content sanitization
  html_escape: (value) => {
    return value
      .replace(/&/g, '&amp;')
      .replace(/</g, '&lt;')
      .replace(/>/g, '&gt;')
      .replace(/"/g, '&quot;')
      .replace(/'/g, '&#x27;');
  },
  
  // Remove potentially dangerous content
  strip_scripts: (value) => {
    return value.replace(/<script[^>]*>.*?<\/script>/gi, '');
  },
  
  // Limit length to prevent prompt injection
  truncate_safe: (value, maxLength = 1000) => {
    if (value.length <= maxLength) return value;
    return value.substring(0, maxLength - 3) + '...';
  },
  
  // Remove prompt injection attempts
  strip_injection: (value) => {
    const dangerousPatterns = [
      /ignore\s+previous\s+instructions/gi,
      /system\s*:\s*you\s+are/gi,
      /\[\s*system\s*\]/gi,
      /\{\{.*\}\}/g // Nested template variables
    ];
    
    let cleaned = value;
    dangerousPatterns.forEach(pattern => {
      cleaned = cleaned.replace(pattern, '[FILTERED]');
    });
    return cleaned;
  }
};
```

### Access Control
```json
{
  "variable_permissions": {
    "public": {
      "description": "Available to all prompts",
      "variables": ["sender_name", "current_date", "account_name"]
    },
    "internal": {
      "description": "Account-specific prompts only",
      "variables": ["customer_tier", "account_balance", "internal_notes"]
    },
    "sensitive": {
      "description": "Requires explicit approval",
      "variables": ["customer_ssn", "payment_info", "private_data"],
      "approval_required": true,
      "audit_log": true
    },
    "admin_only": {
      "description": "System administrators only",
      "variables": ["system_config", "debug_info", "user_passwords"]
    }
  }
}
```

## Audit Linkage

### Audit Trail Requirements
1. **Prompt Usage**: Every prompt execution logged with context
2. **Version Changes**: All template modifications tracked
3. **Variable Access**: Sensitive variable usage audited
4. **Performance Impact**: Response quality and cost tracking
5. **Compliance**: GDPR/SOX audit trail support

### Audit Queries
```sql
-- Prompt performance over time
SELECT 
  p.name,
  DATE(pul.created_at) as date,
  COUNT(*) as usage_count,
  AVG(pul.quality_score) as avg_quality,
  AVG(pul.response_time_ms) as avg_response_time,
  SUM(pul.cost) as total_cost
FROM prompt_usage_logs pul
JOIN prompts p ON pul.prompt_id = p.id
WHERE pul.created_at >= DATE_SUB(NOW(), INTERVAL 30 DAY)
GROUP BY p.id, DATE(pul.created_at)
ORDER BY date DESC;

-- Version adoption tracking
SELECT 
  p.name,
  CONCAT(p.version_major, '.', p.version_minor, '.', p.version_patch) as version,
  p.status,
  COUNT(pul.id) as usage_count,
  p.created_at as version_created
FROM prompts p
LEFT JOIN prompt_usage_logs pul ON p.id = pul.prompt_id
WHERE p.created_at >= DATE_SUB(NOW(), INTERVAL 90 DAY)
GROUP BY p.id
ORDER BY p.name, p.version_major DESC, p.version_minor DESC, p.version_patch DESC;

-- Sensitive variable usage audit
SELECT 
  pul.created_at,
  p.name as prompt_name,
  pul.account_id,
  JSON_EXTRACT(pul.variable_values_json, '$.sensitive_vars') as sensitive_data_used,
  pe.sender as email_sender
FROM prompt_usage_logs pul
JOIN prompts p ON pul.prompt_id = p.id
JOIN processed_emails pe ON pul.processed_email_id = pe.id
WHERE JSON_EXTRACT(pul.variable_values_json, '$.sensitive_vars') IS NOT NULL
ORDER BY pul.created_at DESC;
```

## Edge Cases & Error Handling

### Template Compilation Errors
- **Syntax Errors**: Invalid Handlebars syntax
- **Missing Variables**: Referenced but not defined
- **Circular References**: Variable depends on itself
- **Type Mismatches**: String used where number expected

**Recovery:**
- Validate template syntax before saving
- Show preview with error highlighting
- Fall back to previous working version
- Alert template author of issues

### Variable Resolution Failures
- **Database Timeouts**: Lookup queries take too long
- **API Failures**: External service unavailable
- **Missing Data**: Required field not found
- **Permission Denied**: Access to sensitive data blocked

**Recovery:**
- Use default values when available
- Graceful degradation (skip optional variables)
- Cache successful lookups
- Log failures for debugging

### Version Conflicts
- **Concurrent Edits**: Two users modify same template
- **Deployment Race**: Version deployed while being edited
- **Rollback Issues**: Cannot revert to previous version

**Recovery:**
- Optimistic locking with version numbers
- Merge conflict resolution UI
- Atomic deployments with rollback capability
- Version history preservation

### Performance Issues
- **Large Templates**: Compilation takes too long
- **Complex Logic**: Nested conditions slow evaluation
- **Variable Explosion**: Too many database lookups
- **Memory Usage**: Large variable values

**Recovery:**
- Template complexity scoring and warnings
- Compilation caching
- Variable lookup batching
- Memory limits and cleanup

## Test Checklist

### Template Validation
- **Syntax Checking**: Valid Handlebars syntax
- **Variable Resolution**: All variables can be resolved
- **Output Format**: Matches specified schema
- **Security**: No injection vulnerabilities

### Version Management
- **Semantic Versioning**: Correct version increments
- **Rollback**: Can revert to any previous version
- **Deployment**: Gradual rollout works correctly
- **Conflict Resolution**: Handles concurrent edits

### Performance Testing
- **Compilation Speed**: Templates compile within 100ms
- **Variable Lookup**: Database queries under 50ms
- **Memory Usage**: Stable under load
- **Concurrent Access**: Thread-safe operations

### Integration Testing
- **Rule Engine**: Prompts selected correctly
- **LLM Service**: Compiled prompts work with providers
- **Audit Trail**: All usage properly logged
- **Error Handling**: Graceful failure modes

---

## Pragmatic Review

### ✅ Interface Completeness
- Comprehensive CRUD operations with filtering and search
- Version management with creation and listing endpoints
- Testing and preview capabilities with validation
- Intent/category mapping for automated selection

### ✅ Template Specification
- Rich variable system with built-in and custom variables
- Flexible template syntax with conditionals and loops
- Multiple output formats (JSON, text, markdown)
- Security-focused sanitization and access control

### ✅ Versioning System
- Semantic versioning with automatic change classification
- Complete version lifecycle management
- Gradual deployment with A/B testing
- Rollback capabilities and audit trails

### ✅ Data Schema Design
- Normalized schema with proper relationships
- Performance indexes for common queries
- Audit logging with detailed usage tracking
- Statistics and metrics collection

### ✅ Security & Policy
- Variable source classification and restrictions
- Content sanitization and injection prevention
- Access control with permission levels
- Audit trail for compliance requirements

### ✅ Error Handling
- Template compilation error recovery
- Variable resolution failure handling
- Version conflict resolution
- Performance issue mitigation

### Potential Issues Identified:
1. **Template Complexity**: Very complex templates may be hard to debug
2. **Variable Performance**: Many database lookups could slow compilation
3. **Version Proliferation**: Too many versions may clutter interface
4. **Cache Invalidation**: Variable caching may serve stale data

### Recommended Fixes:
1. Add template complexity scoring and warnings
2. Implement variable lookup batching and caching
3. Add version cleanup policies (archive old versions)
4. Use cache TTL and invalidation strategies