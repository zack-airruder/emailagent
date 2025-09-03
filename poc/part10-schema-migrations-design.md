# Part 10: Schema & Migrations

## Overview
Comprehensive database schema design and migration strategy for the Email Agent system. Includes all entities, relationships, indexes, foreign keys, retention policies, and seed data requirements. Provides DDL specifications, migration ordering, and rollback procedures for production-safe deployment.

## Database Architecture

### Technology Stack
- **Primary Database**: PostgreSQL 15+
- **Migration Tool**: Flyway or custom migration system
- **Connection Pooling**: PgBouncer
- **Backup Strategy**: Point-in-time recovery with WAL archiving
- **Monitoring**: pg_stat_statements, pg_stat_activity

### Schema Organization
```sql
-- Schema structure
CREATE SCHEMA IF NOT EXISTS email_agent;
CREATE SCHEMA IF NOT EXISTS audit;
CREATE SCHEMA IF NOT EXISTS analytics;

-- Set default schema
SET search_path TO email_agent, public;
```

## Core Entity Schemas

### 1. Accounts Management

#### accounts
```sql
CREATE TABLE accounts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Basic account info
  email VARCHAR(255) NOT NULL UNIQUE,
  display_name VARCHAR(255),
  
  -- IMAP configuration
  imap_host VARCHAR(255) NOT NULL,
  imap_port INTEGER NOT NULL DEFAULT 993,
  imap_security VARCHAR(20) NOT NULL DEFAULT 'tls' CHECK (imap_security IN ('tls', 'starttls', 'none')),
  imap_username VARCHAR(255),
  
  -- SMTP configuration
  smtp_host VARCHAR(255) NOT NULL,
  smtp_port INTEGER NOT NULL DEFAULT 587,
  smtp_security VARCHAR(20) NOT NULL DEFAULT 'starttls' CHECK (smtp_security IN ('tls', 'starttls', 'none')),
  smtp_username VARCHAR(255),
  
  -- Account status and health
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'suspended', 'disabled', 'error')),
  health_status VARCHAR(20) NOT NULL DEFAULT 'unknown' CHECK (health_status IN ('healthy', 'degraded', 'unhealthy', 'unknown')),
  last_health_check TIMESTAMP WITH TIME ZONE,
  
  -- Connection state
  imap_connected BOOLEAN DEFAULT FALSE,
  smtp_connected BOOLEAN DEFAULT FALSE,
  last_imap_connection TIMESTAMP WITH TIME ZONE,
  last_smtp_connection TIMESTAMP WITH TIME ZONE,
  
  -- Error tracking
  consecutive_failures INTEGER DEFAULT 0,
  last_error TEXT,
  last_error_at TIMESTAMP WITH TIME ZONE,
  
  -- Automation settings
  auto_processing_enabled BOOLEAN DEFAULT TRUE,
  auto_send_enabled BOOLEAN DEFAULT TRUE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  created_by UUID,
  
  -- Constraints
  CONSTRAINT valid_imap_port CHECK (imap_port BETWEEN 1 AND 65535),
  CONSTRAINT valid_smtp_port CHECK (smtp_port BETWEEN 1 AND 65535)
);

-- Indexes
CREATE INDEX idx_accounts_email ON accounts(email);
CREATE INDEX idx_accounts_status ON accounts(status) WHERE status != 'active';
CREATE INDEX idx_accounts_health ON accounts(health_status, last_health_check);
CREATE INDEX idx_accounts_errors ON accounts(consecutive_failures) WHERE consecutive_failures > 0;

-- Triggers
CREATE OR REPLACE FUNCTION update_updated_at_column()
RETURNS TRIGGER AS $$
BEGIN
    NEW.updated_at = CURRENT_TIMESTAMP;
    RETURN NEW;
END;
$$ language 'plpgsql';

CREATE TRIGGER update_accounts_updated_at BEFORE UPDATE ON accounts
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### account_folders
```sql
CREATE TABLE account_folders (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  
  -- Folder identification
  folder_name VARCHAR(255) NOT NULL,
  folder_path VARCHAR(500) NOT NULL, -- Full IMAP path
  folder_delimiter VARCHAR(10) DEFAULT '/',
  
  -- Folder attributes
  folder_type VARCHAR(50) DEFAULT 'custom' CHECK (folder_type IN ('inbox', 'sent', 'drafts', 'trash', 'spam', 'custom')),
  is_selectable BOOLEAN DEFAULT TRUE,
  is_subscribed BOOLEAN DEFAULT TRUE,
  
  -- IMAP specific
  uid_validity INTEGER,
  uid_next INTEGER,
  message_count INTEGER DEFAULT 0,
  unseen_count INTEGER DEFAULT 0,
  
  -- Sync status
  sync_enabled BOOLEAN DEFAULT TRUE,
  last_sync_at TIMESTAMP WITH TIME ZONE,
  last_sync_uid INTEGER,
  sync_status VARCHAR(20) DEFAULT 'pending' CHECK (sync_status IN ('pending', 'syncing', 'completed', 'error')),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE(account_id, folder_path)
);

-- Indexes
CREATE INDEX idx_account_folders_account ON account_folders(account_id);
CREATE INDEX idx_account_folders_type ON account_folders(account_id, folder_type);
CREATE INDEX idx_account_folders_sync ON account_folders(sync_enabled, last_sync_at) WHERE sync_enabled = TRUE;
CREATE INDEX idx_account_folders_status ON account_folders(sync_status) WHERE sync_status != 'completed';

-- Trigger
CREATE TRIGGER update_account_folders_updated_at BEFORE UPDATE ON account_folders
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### 2. Rules and Automation

#### rules
```sql
CREATE TABLE rules (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  
  -- Rule identification
  name VARCHAR(255) NOT NULL,
  description TEXT,
  
  -- Rule configuration
  conditions JSONB NOT NULL,
  actions JSONB NOT NULL,
  
  -- Execution settings
  priority INTEGER DEFAULT 100,
  execution_order INTEGER DEFAULT 0,
  
  -- Status and lifecycle
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'testing', 'error')),
  version INTEGER DEFAULT 1,
  
  -- Safety and limits
  safety_limits JSONB DEFAULT '{}',
  max_executions_per_hour INTEGER DEFAULT 100,
  max_executions_per_day INTEGER DEFAULT 1000,
  cooldown_seconds INTEGER DEFAULT 0,
  
  -- Execution tracking
  total_executions INTEGER DEFAULT 0,
  successful_executions INTEGER DEFAULT 0,
  failed_executions INTEGER DEFAULT 0,
  last_execution_at TIMESTAMP WITH TIME ZONE,
  
  -- Error handling
  consecutive_failures INTEGER DEFAULT 0,
  last_error TEXT,
  last_error_at TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  created_by UUID,
  
  -- Constraints
  CONSTRAINT valid_priority CHECK (priority BETWEEN 1 AND 1000),
  CONSTRAINT valid_cooldown CHECK (cooldown_seconds >= 0),
  CONSTRAINT valid_limits CHECK (max_executions_per_hour > 0 AND max_executions_per_day > 0)
);

-- Indexes
CREATE INDEX idx_rules_account ON rules(account_id);
CREATE INDEX idx_rules_status ON rules(status) WHERE status = 'active';
CREATE INDEX idx_rules_execution_order ON rules(account_id, execution_order, priority);
CREATE INDEX idx_rules_conditions ON rules USING GIN(conditions);
CREATE INDEX idx_rules_actions ON rules USING GIN(actions);
CREATE INDEX idx_rules_errors ON rules(consecutive_failures) WHERE consecutive_failures > 0;

-- Trigger
CREATE TRIGGER update_rules_updated_at BEFORE UPDATE ON rules
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### decision_trace
```sql
CREATE TABLE decision_trace (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Email and rule context
  email_id UUID NOT NULL, -- References processed_emails(id)
  rule_id UUID REFERENCES rules(id) ON DELETE SET NULL,
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  
  -- Decision details
  rule_matched BOOLEAN NOT NULL,
  conditions_evaluated JSONB NOT NULL,
  actions_taken JSONB,
  
  -- Execution context
  execution_time_ms INTEGER,
  evaluation_order INTEGER,
  
  -- Results
  decision_outcome VARCHAR(50) NOT NULL CHECK (decision_outcome IN ('no_action', 'auto_send', 'auto_draft', 'escalate', 'skip', 'error')),
  confidence_score DECIMAL(3,2) CHECK (confidence_score BETWEEN 0 AND 1),
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key (for time-based partitioning)
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_decision_trace_email ON decision_trace(email_id);
CREATE INDEX idx_decision_trace_rule ON decision_trace(rule_id) WHERE rule_id IS NOT NULL;
CREATE INDEX idx_decision_trace_account_date ON decision_trace(account_id, partition_date);
CREATE INDEX idx_decision_trace_outcome ON decision_trace(decision_outcome, created_at);
CREATE INDEX idx_decision_trace_errors ON decision_trace(error_code) WHERE error_code IS NOT NULL;
```

#### rule_execution_stats
```sql
CREATE TABLE rule_execution_stats (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  rule_id UUID NOT NULL REFERENCES rules(id) ON DELETE CASCADE,
  
  -- Time window
  window_start TIMESTAMP WITH TIME ZONE NOT NULL,
  window_end TIMESTAMP WITH TIME ZONE NOT NULL,
  window_type VARCHAR(20) NOT NULL CHECK (window_type IN ('hour', 'day', 'week', 'month')),
  
  -- Execution metrics
  total_evaluations INTEGER DEFAULT 0,
  successful_matches INTEGER DEFAULT 0,
  failed_evaluations INTEGER DEFAULT 0,
  
  -- Performance metrics
  avg_execution_time_ms DECIMAL(10,2),
  max_execution_time_ms INTEGER,
  min_execution_time_ms INTEGER,
  
  -- Outcome distribution
  auto_send_count INTEGER DEFAULT 0,
  auto_draft_count INTEGER DEFAULT 0,
  escalation_count INTEGER DEFAULT 0,
  skip_count INTEGER DEFAULT 0,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE(rule_id, window_start, window_type)
);

-- Indexes
CREATE INDEX idx_rule_stats_rule_window ON rule_execution_stats(rule_id, window_start);
CREATE INDEX idx_rule_stats_window_type ON rule_execution_stats(window_type, window_start);

-- Trigger
CREATE TRIGGER update_rule_execution_stats_updated_at BEFORE UPDATE ON rule_execution_stats
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### 3. Prompt Library

#### prompts
```sql
CREATE TABLE prompts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Prompt identification
  name VARCHAR(255) NOT NULL,
  description TEXT,
  category VARCHAR(100) NOT NULL,
  intent VARCHAR(100),
  
  -- Template content
  template_content TEXT NOT NULL,
  template_format VARCHAR(20) DEFAULT 'handlebars' CHECK (template_format IN ('handlebars', 'jinja2', 'mustache')),
  
  -- Variables and schema
  required_variables JSONB DEFAULT '[]',
  optional_variables JSONB DEFAULT '[]',
  variable_schema JSONB,
  
  -- Output configuration
  output_format VARCHAR(20) DEFAULT 'text' CHECK (output_format IN ('text', 'json', 'html', 'markdown')),
  output_schema JSONB,
  
  -- Versioning
  version VARCHAR(20) NOT NULL DEFAULT '1.0.0',
  parent_version_id UUID REFERENCES prompts(id),
  is_latest BOOLEAN DEFAULT TRUE,
  
  -- Lifecycle
  status VARCHAR(20) NOT NULL DEFAULT 'draft' CHECK (status IN ('draft', 'testing', 'active', 'deprecated', 'archived')),
  published_at TIMESTAMP WITH TIME ZONE,
  deprecated_at TIMESTAMP WITH TIME ZONE,
  
  -- Usage and performance
  usage_count INTEGER DEFAULT 0,
  success_rate DECIMAL(5,2),
  avg_response_time_ms INTEGER,
  
  -- Quality metrics
  quality_score DECIMAL(3,2) CHECK (quality_score BETWEEN 0 AND 1),
  last_quality_check TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  created_by UUID,
  
  -- Constraints
  UNIQUE(name, version)
);

-- Indexes
CREATE INDEX idx_prompts_category ON prompts(category, status);
CREATE INDEX idx_prompts_intent ON prompts(intent) WHERE intent IS NOT NULL;
CREATE INDEX idx_prompts_status ON prompts(status) WHERE status = 'active';
CREATE INDEX idx_prompts_latest ON prompts(name, is_latest) WHERE is_latest = TRUE;
CREATE INDEX idx_prompts_usage ON prompts(usage_count DESC, success_rate DESC);
CREATE INDEX idx_prompts_variables ON prompts USING GIN(required_variables);

-- Trigger
CREATE TRIGGER update_prompts_updated_at BEFORE UPDATE ON prompts
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### prompt_mappings
```sql
CREATE TABLE prompt_mappings (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Mapping configuration
  account_id UUID REFERENCES accounts(id) ON DELETE CASCADE,
  rule_id UUID REFERENCES rules(id) ON DELETE CASCADE,
  prompt_id UUID NOT NULL REFERENCES prompts(id) ON DELETE CASCADE,
  
  -- Mapping context
  mapping_type VARCHAR(50) NOT NULL CHECK (mapping_type IN ('classification', 'response_generation', 'sentiment_analysis', 'urgency_detection')),
  conditions JSONB,
  
  -- Configuration
  priority INTEGER DEFAULT 100,
  is_active BOOLEAN DEFAULT TRUE,
  
  -- Usage tracking
  usage_count INTEGER DEFAULT 0,
  last_used_at TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  CHECK ((account_id IS NOT NULL) OR (rule_id IS NOT NULL)) -- At least one must be specified
);

-- Indexes
CREATE INDEX idx_prompt_mappings_account ON prompt_mappings(account_id, mapping_type) WHERE account_id IS NOT NULL;
CREATE INDEX idx_prompt_mappings_rule ON prompt_mappings(rule_id, mapping_type) WHERE rule_id IS NOT NULL;
CREATE INDEX idx_prompt_mappings_prompt ON prompt_mappings(prompt_id);
CREATE INDEX idx_prompt_mappings_active ON prompt_mappings(is_active, priority) WHERE is_active = TRUE;

-- Trigger
CREATE TRIGGER update_prompt_mappings_updated_at BEFORE UPDATE ON prompt_mappings
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### prompt_test_runs
```sql
CREATE TABLE prompt_test_runs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  prompt_id UUID NOT NULL REFERENCES prompts(id) ON DELETE CASCADE,
  
  -- Test configuration
  test_name VARCHAR(255) NOT NULL,
  test_type VARCHAR(50) NOT NULL CHECK (test_type IN ('unit', 'integration', 'performance', 'quality')),
  
  -- Test data
  input_variables JSONB NOT NULL,
  expected_output JSONB,
  
  -- Results
  actual_output JSONB,
  test_passed BOOLEAN,
  
  -- Performance metrics
  execution_time_ms INTEGER,
  token_count INTEGER,
  cost_estimate DECIMAL(10,4),
  
  -- Quality metrics
  similarity_score DECIMAL(3,2),
  quality_metrics JSONB,
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  created_by UUID
);

-- Indexes
CREATE INDEX idx_prompt_test_runs_prompt ON prompt_test_runs(prompt_id, created_at);
CREATE INDEX idx_prompt_test_runs_type ON prompt_test_runs(test_type, test_passed);
CREATE INDEX idx_prompt_test_runs_performance ON prompt_test_runs(execution_time_ms) WHERE execution_time_ms IS NOT NULL;
```

#### prompt_usage_logs
```sql
CREATE TABLE prompt_usage_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Context
  prompt_id UUID NOT NULL REFERENCES prompts(id) ON DELETE CASCADE,
  account_id UUID REFERENCES accounts(id) ON DELETE CASCADE,
  email_id UUID, -- References processed_emails(id)
  
  -- Usage details
  input_variables JSONB NOT NULL,
  output_content TEXT,
  
  -- Performance
  execution_time_ms INTEGER,
  token_count INTEGER,
  cost_estimate DECIMAL(10,4),
  
  -- Quality and success
  success BOOLEAN NOT NULL,
  quality_score DECIMAL(3,2),
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_prompt_usage_logs_prompt_date ON prompt_usage_logs(prompt_id, partition_date);
CREATE INDEX idx_prompt_usage_logs_account_date ON prompt_usage_logs(account_id, partition_date) WHERE account_id IS NOT NULL;
CREATE INDEX idx_prompt_usage_logs_success ON prompt_usage_logs(success, created_at);
CREATE INDEX idx_prompt_usage_logs_performance ON prompt_usage_logs(execution_time_ms, token_count);
```

### 4. LLM Providers

#### llm_providers
```sql
CREATE TABLE llm_providers (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Provider identification
  name VARCHAR(100) NOT NULL UNIQUE,
  provider_type VARCHAR(50) NOT NULL CHECK (provider_type IN ('openai', 'gemini', 'anthropic', 'custom')),
  
  -- Configuration
  base_url VARCHAR(500),
  api_version VARCHAR(20),
  
  -- Model configuration
  available_models JSONB NOT NULL DEFAULT '[]',
  default_model VARCHAR(100),
  model_mappings JSONB DEFAULT '{}',
  
  -- Capabilities
  supports_classification BOOLEAN DEFAULT FALSE,
  supports_generation BOOLEAN DEFAULT FALSE,
  supports_streaming BOOLEAN DEFAULT FALSE,
  
  -- Rate limiting
  requests_per_minute INTEGER DEFAULT 60,
  tokens_per_minute INTEGER,
  
  -- Status and health
  status VARCHAR(20) NOT NULL DEFAULT 'active' CHECK (status IN ('active', 'inactive', 'maintenance', 'error')),
  health_status VARCHAR(20) DEFAULT 'unknown' CHECK (health_status IN ('healthy', 'degraded', 'unhealthy', 'unknown')),
  last_health_check TIMESTAMP WITH TIME ZONE,
  
  -- Error tracking
  consecutive_failures INTEGER DEFAULT 0,
  last_error TEXT,
  last_error_at TIMESTAMP WITH TIME ZONE,
  
  -- Priority and fallback
  priority INTEGER DEFAULT 100,
  is_fallback BOOLEAN DEFAULT FALSE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  CONSTRAINT valid_priority CHECK (priority BETWEEN 1 AND 1000)
);

-- Indexes
CREATE INDEX idx_llm_providers_status ON llm_providers(status, priority) WHERE status = 'active';
CREATE INDEX idx_llm_providers_type ON llm_providers(provider_type);
CREATE INDEX idx_llm_providers_capabilities ON llm_providers(supports_classification, supports_generation);
CREATE INDEX idx_llm_providers_health ON llm_providers(health_status, last_health_check);

-- Trigger
CREATE TRIGGER update_llm_providers_updated_at BEFORE UPDATE ON llm_providers
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### llm_requests
```sql
CREATE TABLE llm_requests (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Request context
  provider_id UUID NOT NULL REFERENCES llm_providers(id) ON DELETE CASCADE,
  account_id UUID REFERENCES accounts(id) ON DELETE CASCADE,
  email_id UUID, -- References processed_emails(id)
  prompt_id UUID REFERENCES prompts(id) ON DELETE SET NULL,
  
  -- Request details
  request_type VARCHAR(50) NOT NULL CHECK (request_type IN ('classification', 'generation', 'analysis')),
  model_used VARCHAR(100) NOT NULL,
  
  -- Input/Output
  input_content TEXT NOT NULL,
  input_tokens INTEGER,
  output_content TEXT,
  output_tokens INTEGER,
  
  -- Performance
  response_time_ms INTEGER,
  queue_time_ms INTEGER,
  
  -- Cost tracking
  cost_estimate DECIMAL(10,6),
  
  -- Quality and success
  success BOOLEAN NOT NULL,
  confidence_score DECIMAL(3,2),
  quality_score DECIMAL(3,2),
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  retry_count INTEGER DEFAULT 0,
  
  -- Safety and content filtering
  safety_check_passed BOOLEAN,
  content_filtered BOOLEAN DEFAULT FALSE,
  safety_scores JSONB,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_llm_requests_provider_date ON llm_requests(provider_id, partition_date);
CREATE INDEX idx_llm_requests_account_date ON llm_requests(account_id, partition_date) WHERE account_id IS NOT NULL;
CREATE INDEX idx_llm_requests_type ON llm_requests(request_type, created_at);
CREATE INDEX idx_llm_requests_success ON llm_requests(success, created_at);
CREATE INDEX idx_llm_requests_performance ON llm_requests(response_time_ms, input_tokens, output_tokens);
CREATE INDEX idx_llm_requests_errors ON llm_requests(error_code) WHERE error_code IS NOT NULL;
```

#### llm_rate_limits
```sql
CREATE TABLE llm_rate_limits (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  provider_id UUID NOT NULL REFERENCES llm_providers(id) ON DELETE CASCADE,
  
  -- Rate limit window
  window_start TIMESTAMP WITH TIME ZONE NOT NULL,
  window_duration_seconds INTEGER NOT NULL,
  
  -- Usage tracking
  requests_count INTEGER DEFAULT 0,
  tokens_used INTEGER DEFAULT 0,
  
  -- Limits
  max_requests INTEGER NOT NULL,
  max_tokens INTEGER,
  
  -- Status
  is_exceeded BOOLEAN DEFAULT FALSE,
  reset_at TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE(provider_id, window_start)
);

-- Indexes
CREATE INDEX idx_llm_rate_limits_provider_window ON llm_rate_limits(provider_id, window_start);
CREATE INDEX idx_llm_rate_limits_exceeded ON llm_rate_limits(is_exceeded, reset_at) WHERE is_exceeded = TRUE;

-- Trigger
CREATE TRIGGER update_llm_rate_limits_updated_at BEFORE UPDATE ON llm_rate_limits
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### 5. Email Processing

#### processed_emails
```sql
CREATE TABLE processed_emails (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Email identification
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  folder_id UUID REFERENCES account_folders(id) ON DELETE SET NULL,
  
  -- IMAP details
  message_id VARCHAR(500), -- Email Message-ID header
  uid INTEGER, -- IMAP UID
  sequence_number INTEGER,
  
  -- Email headers
  subject TEXT,
  from_address VARCHAR(500),
  to_addresses TEXT[], -- Array of recipient addresses
  cc_addresses TEXT[],
  bcc_addresses TEXT[],
  reply_to VARCHAR(500),
  
  -- Content
  body_text TEXT,
  body_html TEXT,
  content_hash VARCHAR(64), -- SHA-256 hash for deduplication
  
  -- Attachments
  has_attachments BOOLEAN DEFAULT FALSE,
  attachment_count INTEGER DEFAULT 0,
  attachment_info JSONB,
  
  -- Email metadata
  email_date TIMESTAMP WITH TIME ZONE,
  received_date TIMESTAMP WITH TIME ZONE,
  size_bytes INTEGER,
  
  -- Flags and status
  is_seen BOOLEAN DEFAULT FALSE,
  is_flagged BOOLEAN DEFAULT FALSE,
  is_answered BOOLEAN DEFAULT FALSE,
  is_draft BOOLEAN DEFAULT FALSE,
  is_deleted BOOLEAN DEFAULT FALSE,
  
  -- Processing status
  processing_status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (processing_status IN ('pending', 'processing', 'completed', 'error', 'skipped')),
  processed_at TIMESTAMP WITH TIME ZONE,
  
  -- Classification results
  intent VARCHAR(100),
  category VARCHAR(100),
  sentiment VARCHAR(20) CHECK (sentiment IN ('positive', 'neutral', 'negative')),
  urgency VARCHAR(20) CHECK (urgency IN ('low', 'medium', 'high', 'critical')),
  confidence_scores JSONB,
  
  -- Risk assessment
  risk_level VARCHAR(20) DEFAULT 'low' CHECK (risk_level IN ('low', 'medium', 'high', 'critical')),
  risk_factors JSONB,
  
  -- Processing metadata
  processing_time_ms INTEGER,
  rules_evaluated INTEGER DEFAULT 0,
  rules_matched INTEGER DEFAULT 0,
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  retry_count INTEGER DEFAULT 0,
  
  -- PII and privacy
  contains_pii BOOLEAN DEFAULT FALSE,
  pii_types TEXT[],
  redaction_applied BOOLEAN DEFAULT FALSE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED,
  
  -- Constraints
  UNIQUE(account_id, message_id), -- Prevent duplicate processing
  UNIQUE(account_id, folder_id, uid) -- IMAP UID uniqueness per folder
);

-- Indexes
CREATE INDEX idx_processed_emails_account_date ON processed_emails(account_id, partition_date);
CREATE INDEX idx_processed_emails_status ON processed_emails(processing_status, created_at);
CREATE INDEX idx_processed_emails_classification ON processed_emails(intent, category, urgency);
CREATE INDEX idx_processed_emails_content_hash ON processed_emails(content_hash);
CREATE INDEX idx_processed_emails_message_id ON processed_emails(message_id);
CREATE INDEX idx_processed_emails_folder_uid ON processed_emails(folder_id, uid) WHERE folder_id IS NOT NULL;
CREATE INDEX idx_processed_emails_email_date ON processed_emails(email_date);
CREATE INDEX idx_processed_emails_risk ON processed_emails(risk_level) WHERE risk_level != 'low';
CREATE INDEX idx_processed_emails_pii ON processed_emails(contains_pii) WHERE contains_pii = TRUE;

-- Full-text search index
CREATE INDEX idx_processed_emails_fts ON processed_emails USING GIN(to_tsvector('english', COALESCE(subject, '') || ' ' || COALESCE(body_text, '')));

-- Trigger
CREATE TRIGGER update_processed_emails_updated_at BEFORE UPDATE ON processed_emails
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### processing_trace
```sql
CREATE TABLE processing_trace (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  email_id UUID NOT NULL, -- References processed_emails(id)
  
  -- Processing step
  step_name VARCHAR(100) NOT NULL,
  step_order INTEGER NOT NULL,
  
  -- Step details
  component VARCHAR(50) NOT NULL, -- 'imap_ingest', 'llm_classify', 'rule_engine', 'smtp_send'
  operation VARCHAR(100) NOT NULL,
  
  -- Input/Output
  input_data JSONB,
  output_data JSONB,
  
  -- Performance
  start_time TIMESTAMP WITH TIME ZONE NOT NULL,
  end_time TIMESTAMP WITH TIME ZONE,
  duration_ms INTEGER,
  
  -- Status
  status VARCHAR(20) NOT NULL CHECK (status IN ('started', 'completed', 'error', 'skipped')),
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_processing_trace_email ON processing_trace(email_id, step_order);
CREATE INDEX idx_processing_trace_component ON processing_trace(component, status, partition_date);
CREATE INDEX idx_processing_trace_performance ON processing_trace(duration_ms) WHERE duration_ms IS NOT NULL;
CREATE INDEX idx_processing_trace_errors ON processing_trace(error_code) WHERE error_code IS NOT NULL;
```

### 6. Email Sending

#### send_logs
```sql
CREATE TABLE send_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Context
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  original_email_id UUID, -- References processed_emails(id) if this is a response
  rule_id UUID REFERENCES rules(id) ON DELETE SET NULL,
  escalation_id UUID, -- References escalations(id) if sent via escalation
  
  -- Email details
  message_id VARCHAR(500), -- Generated Message-ID
  subject TEXT NOT NULL,
  from_address VARCHAR(500) NOT NULL,
  to_addresses TEXT[] NOT NULL,
  cc_addresses TEXT[],
  bcc_addresses TEXT[],
  reply_to VARCHAR(500),
  
  -- Content
  body_text TEXT,
  body_html TEXT,
  content_hash VARCHAR(64),
  
  -- Attachments
  has_attachments BOOLEAN DEFAULT FALSE,
  attachment_count INTEGER DEFAULT 0,
  attachment_info JSONB,
  
  -- Sending details
  send_method VARCHAR(20) NOT NULL CHECK (send_method IN ('auto', 'manual', 'escalation', 'test')),
  smtp_server VARCHAR(255),
  
  -- Status tracking
  send_status VARCHAR(20) NOT NULL DEFAULT 'queued' CHECK (send_status IN ('queued', 'sending', 'sent', 'failed', 'bounced', 'rejected')),
  queued_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  sent_at TIMESTAMP WITH TIME ZONE,
  
  -- Delivery tracking
  delivery_status VARCHAR(20) CHECK (delivery_status IN ('pending', 'delivered', 'bounced', 'rejected', 'deferred')),
  delivery_attempts INTEGER DEFAULT 0,
  last_delivery_attempt TIMESTAMP WITH TIME ZONE,
  
  -- Performance
  queue_time_ms INTEGER,
  send_time_ms INTEGER,
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  smtp_response TEXT,
  
  -- Safety and compliance
  safety_check_passed BOOLEAN DEFAULT TRUE,
  opt_out_checked BOOLEAN DEFAULT FALSE,
  rate_limit_applied BOOLEAN DEFAULT FALSE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_send_logs_account_date ON send_logs(account_id, partition_date);
CREATE INDEX idx_send_logs_status ON send_logs(send_status, queued_at);
CREATE INDEX idx_send_logs_delivery ON send_logs(delivery_status, last_delivery_attempt);
CREATE INDEX idx_send_logs_original_email ON send_logs(original_email_id) WHERE original_email_id IS NOT NULL;
CREATE INDEX idx_send_logs_rule ON send_logs(rule_id) WHERE rule_id IS NOT NULL;
CREATE INDEX idx_send_logs_method ON send_logs(send_method, created_at);
CREATE INDEX idx_send_logs_message_id ON send_logs(message_id);
CREATE INDEX idx_send_logs_errors ON send_logs(error_code) WHERE error_code IS NOT NULL;

-- Trigger
CREATE TRIGGER update_send_logs_updated_at BEFORE UPDATE ON send_logs
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### recipient_delivery_status
```sql
CREATE TABLE recipient_delivery_status (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  send_log_id UUID NOT NULL REFERENCES send_logs(id) ON DELETE CASCADE,
  
  -- Recipient details
  recipient_email VARCHAR(500) NOT NULL,
  recipient_type VARCHAR(10) NOT NULL CHECK (recipient_type IN ('to', 'cc', 'bcc')),
  
  -- Delivery status
  delivery_status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (delivery_status IN ('pending', 'delivered', 'bounced', 'rejected', 'deferred')),
  delivery_attempts INTEGER DEFAULT 0,
  
  -- Timestamps
  first_attempt_at TIMESTAMP WITH TIME ZONE,
  last_attempt_at TIMESTAMP WITH TIME ZONE,
  delivered_at TIMESTAMP WITH TIME ZONE,
  
  -- SMTP responses
  smtp_response TEXT,
  smtp_code INTEGER,
  
  -- Bounce details
  bounce_type VARCHAR(20) CHECK (bounce_type IN ('hard', 'soft', 'complaint')),
  bounce_reason TEXT,
  
  -- Error handling
  error_message TEXT,
  retry_after TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_recipient_delivery_send_log ON recipient_delivery_status(send_log_id);
CREATE INDEX idx_recipient_delivery_email ON recipient_delivery_status(recipient_email, delivery_status);
CREATE INDEX idx_recipient_delivery_status ON recipient_delivery_status(delivery_status, last_attempt_at);
CREATE INDEX idx_recipient_delivery_bounces ON recipient_delivery_status(bounce_type) WHERE bounce_type IS NOT NULL;

-- Trigger
CREATE TRIGGER update_recipient_delivery_status_updated_at BEFORE UPDATE ON recipient_delivery_status
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### bounce_logs
```sql
CREATE TABLE bounce_logs (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Reference to original send
  send_log_id UUID REFERENCES send_logs(id) ON DELETE SET NULL,
  recipient_delivery_id UUID REFERENCES recipient_delivery_status(id) ON DELETE CASCADE,
  
  -- Bounce details
  bounce_type VARCHAR(20) NOT NULL CHECK (bounce_type IN ('hard', 'soft', 'complaint', 'unsubscribe')),
  bounce_subtype VARCHAR(50),
  
  -- Email details
  original_message_id VARCHAR(500),
  bounced_recipient VARCHAR(500) NOT NULL,
  
  -- Bounce message analysis
  bounce_message TEXT,
  smtp_code INTEGER,
  smtp_response TEXT,
  
  -- Categorization
  bounce_reason VARCHAR(100),
  is_permanent BOOLEAN,
  
  -- Processing
  processed BOOLEAN DEFAULT FALSE,
  action_taken VARCHAR(50), -- 'suppressed', 'retry_scheduled', 'manual_review'
  
  -- Metadata
  bounced_at TIMESTAMP WITH TIME ZONE NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_bounce_logs_recipient ON bounce_logs(bounced_recipient, bounce_type);
CREATE INDEX idx_bounce_logs_send_log ON bounce_logs(send_log_id) WHERE send_log_id IS NOT NULL;
CREATE INDEX idx_bounce_logs_type ON bounce_logs(bounce_type, is_permanent);
CREATE INDEX idx_bounce_logs_processing ON bounce_logs(processed, created_at) WHERE processed = FALSE;
```

#### suppression_list
```sql
CREATE TABLE suppression_list (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Suppressed email
  email_address VARCHAR(500) NOT NULL UNIQUE,
  
  -- Suppression details
  suppression_type VARCHAR(20) NOT NULL CHECK (suppression_type IN ('bounce', 'complaint', 'unsubscribe', 'manual')),
  reason TEXT,
  
  -- Source information
  source_send_log_id UUID REFERENCES send_logs(id) ON DELETE SET NULL,
  source_bounce_log_id UUID REFERENCES bounce_logs(id) ON DELETE SET NULL,
  
  -- Suppression scope
  account_id UUID REFERENCES accounts(id) ON DELETE CASCADE, -- NULL means global suppression
  
  -- Status
  is_active BOOLEAN DEFAULT TRUE,
  
  -- Timestamps
  suppressed_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP WITH TIME ZONE, -- For temporary suppressions
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  created_by UUID
);

-- Indexes
CREATE INDEX idx_suppression_list_email ON suppression_list(email_address, is_active);
CREATE INDEX idx_suppression_list_account ON suppression_list(account_id, is_active) WHERE account_id IS NOT NULL;
CREATE INDEX idx_suppression_list_type ON suppression_list(suppression_type, suppressed_at);
CREATE INDEX idx_suppression_list_expiry ON suppression_list(expires_at) WHERE expires_at IS NOT NULL;
```

### 7. Human Escalation

#### escalations
```sql
CREATE TABLE escalations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Context
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  email_id UUID NOT NULL, -- References processed_emails(id)
  rule_id UUID REFERENCES rules(id) ON DELETE SET NULL,
  
  -- Escalation details
  escalation_type VARCHAR(50) NOT NULL CHECK (escalation_type IN ('rule_match', 'high_risk', 'manual', 'error', 'quality_review')),
  priority VARCHAR(20) NOT NULL DEFAULT 'medium' CHECK (priority IN ('low', 'medium', 'high', 'critical')),
  
  -- State management
  status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'assigned', 'in_review', 'approved', 'rejected', 'escalated', 'completed')),
  
  -- Assignment
  assigned_to UUID, -- User ID
  assigned_at TIMESTAMP WITH TIME ZONE,
  
  -- Lock management
  locked_by UUID, -- User ID
  locked_at TIMESTAMP WITH TIME ZONE,
  lock_expires_at TIMESTAMP WITH TIME ZONE,
  
  -- AI recommendations
  recommended_action VARCHAR(50),
  ai_rationale TEXT,
  confidence_score DECIMAL(3,2),
  
  -- SLA tracking
  sla_deadline TIMESTAMP WITH TIME ZONE,
  sla_breached BOOLEAN DEFAULT FALSE,
  
  -- Resolution
  resolution VARCHAR(50) CHECK (resolution IN ('approved_and_sent', 'rejected', 'modified_and_sent', 'escalated_further', 'no_action')),
  resolution_notes TEXT,
  resolved_at TIMESTAMP WITH TIME ZONE,
  resolved_by UUID,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_escalations_account_date ON escalations(account_id, partition_date);
CREATE INDEX idx_escalations_status ON escalations(status, priority, created_at);
CREATE INDEX idx_escalations_assigned ON escalations(assigned_to, status) WHERE assigned_to IS NOT NULL;
CREATE INDEX idx_escalations_locked ON escalations(locked_by, lock_expires_at) WHERE locked_by IS NOT NULL;
CREATE INDEX idx_escalations_sla ON escalations(sla_deadline, sla_breached);
CREATE INDEX idx_escalations_email ON escalations(email_id);
CREATE INDEX idx_escalations_rule ON escalations(rule_id) WHERE rule_id IS NOT NULL;

-- Trigger
CREATE TRIGGER update_escalations_updated_at BEFORE UPDATE ON escalations
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### escalation_drafts
```sql
CREATE TABLE escalation_drafts (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  escalation_id UUID NOT NULL REFERENCES escalations(id) ON DELETE CASCADE,
  
  -- Draft details
  draft_type VARCHAR(20) NOT NULL DEFAULT 'response' CHECK (draft_type IN ('response', 'forward', 'internal_note')),
  
  -- Email content
  subject TEXT,
  body_text TEXT,
  body_html TEXT,
  
  -- Recipients
  to_addresses TEXT[],
  cc_addresses TEXT[],
  bcc_addresses TEXT[],
  
  -- Attachments
  attachment_info JSONB,
  
  -- Version control
  version INTEGER DEFAULT 1,
  is_current BOOLEAN DEFAULT TRUE,
  
  -- Source information
  generated_by VARCHAR(20) CHECK (generated_by IN ('ai', 'human', 'template')),
  source_prompt_id UUID REFERENCES prompts(id) ON DELETE SET NULL,
  
  -- Editing history
  created_by UUID NOT NULL,
  last_modified_by UUID,
  
  -- Status
  status VARCHAR(20) DEFAULT 'draft' CHECK (status IN ('draft', 'ready', 'approved', 'sent', 'rejected')),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_escalation_drafts_escalation ON escalation_drafts(escalation_id, version);
CREATE INDEX idx_escalation_drafts_current ON escalation_drafts(escalation_id, is_current) WHERE is_current = TRUE;
CREATE INDEX idx_escalation_drafts_status ON escalation_drafts(status, created_at);
CREATE INDEX idx_escalation_drafts_created_by ON escalation_drafts(created_by);

-- Trigger
CREATE TRIGGER update_escalation_drafts_updated_at BEFORE UPDATE ON escalation_drafts
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

#### escalation_locks
```sql
CREATE TABLE escalation_locks (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  escalation_id UUID NOT NULL REFERENCES escalations(id) ON DELETE CASCADE,
  
  -- Lock details
  locked_by UUID NOT NULL,
  lock_type VARCHAR(20) NOT NULL DEFAULT 'edit' CHECK (lock_type IN ('edit', 'review', 'approval')),
  
  -- Lock timing
  locked_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  expires_at TIMESTAMP WITH TIME ZONE NOT NULL,
  
  -- Lock status
  is_active BOOLEAN DEFAULT TRUE,
  released_at TIMESTAMP WITH TIME ZONE,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE(escalation_id, lock_type) -- Only one active lock per type per escalation
);

-- Indexes
CREATE INDEX idx_escalation_locks_escalation ON escalation_locks(escalation_id, is_active);
CREATE INDEX idx_escalation_locks_user ON escalation_locks(locked_by, is_active);
CREATE INDEX idx_escalation_locks_expiry ON escalation_locks(expires_at) WHERE is_active = TRUE;
```

#### escalation_audit_log
```sql
CREATE TABLE escalation_audit_log (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  escalation_id UUID NOT NULL REFERENCES escalations(id) ON DELETE CASCADE,
  
  -- Action details
  action VARCHAR(50) NOT NULL,
  actor_id UUID NOT NULL,
  actor_type VARCHAR(20) DEFAULT 'user' CHECK (actor_type IN ('user', 'system', 'api')),
  
  -- State changes
  previous_state JSONB,
  new_state JSONB,
  
  -- Context
  context JSONB,
  ip_address INET,
  user_agent TEXT,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Partitioning key
  partition_date DATE GENERATED ALWAYS AS (DATE(created_at)) STORED
);

-- Indexes
CREATE INDEX idx_escalation_audit_escalation ON escalation_audit_log(escalation_id, created_at);
CREATE INDEX idx_escalation_audit_actor ON escalation_audit_log(actor_id, created_at);
CREATE INDEX idx_escalation_audit_action ON escalation_audit_log(action, partition_date);
```

#### escalation_sla_tracking
```sql
CREATE TABLE escalation_sla_tracking (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  escalation_id UUID NOT NULL REFERENCES escalations(id) ON DELETE CASCADE,
  
  -- SLA configuration
  sla_type VARCHAR(50) NOT NULL, -- 'first_response', 'resolution', 'escalation'
  target_minutes INTEGER NOT NULL,
  
  -- Timing
  started_at TIMESTAMP WITH TIME ZONE NOT NULL,
  deadline TIMESTAMP WITH TIME ZONE NOT NULL,
  completed_at TIMESTAMP WITH TIME ZONE,
  
  -- Status
  status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'met', 'breached', 'paused')),
  breach_reason TEXT,
  
  -- Pause tracking (for business hours SLA)
  total_pause_minutes INTEGER DEFAULT 0,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_escalation_sla_escalation ON escalation_sla_tracking(escalation_id, sla_type);
CREATE INDEX idx_escalation_sla_deadline ON escalation_sla_tracking(deadline, status) WHERE status = 'active';
CREATE INDEX idx_escalation_sla_breached ON escalation_sla_tracking(status, completed_at) WHERE status = 'breached';

-- Trigger
CREATE TRIGGER update_escalation_sla_tracking_updated_at BEFORE UPDATE ON escalation_sla_tracking
    FOR EACH ROW EXECUTE FUNCTION update_updated_at_column();
```

### 8. Security and Audit

#### encrypted_secrets
```sql
CREATE TABLE encrypted_secrets (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Secret identification
  secret_type VARCHAR(50) NOT NULL, -- 'imap_password', 'smtp_password', 'api_key'
  owner_id UUID NOT NULL, -- Account or user ID
  
  -- Encryption details
  key_id VARCHAR(36) NOT NULL,
  nonce VARCHAR(100) NOT NULL,
  encrypted_value TEXT NOT NULL,
  auth_tag VARCHAR(100) NOT NULL,
  encryption_context JSONB,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  last_accessed TIMESTAMP WITH TIME ZONE,
  access_count INTEGER DEFAULT 0,
  
  -- Rotation and expiry
  expires_at TIMESTAMP WITH TIME ZONE,
  rotation_due_at TIMESTAMP WITH TIME ZONE,
  
  -- Constraints
  UNIQUE(owner_id, secret_type)
);

-- Indexes
CREATE INDEX idx_encrypted_secrets_owner_type ON encrypted_secrets(owner_id, secret_type);
CREATE INDEX idx_encrypted_secrets_key_rotation ON encrypted_secrets(key_id, rotation_due_at);
CREATE INDEX idx_encrypted_secrets_expiry_cleanup ON encrypted_secrets(expires_at);
```

#### encryption_keys
```sql
CREATE TABLE encryption_keys (
  key_id VARCHAR(36) PRIMARY KEY,
  
  -- Key material (encrypted with master key)
  encrypted_key TEXT NOT NULL,
  key_algorithm VARCHAR(50) NOT NULL,
  key_size INTEGER NOT NULL,
  
  -- Lifecycle
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  activated_at TIMESTAMP WITH TIME ZONE,
  deactivated_at TIMESTAMP WITH TIME ZONE,
  delete_after TIMESTAMP WITH TIME ZONE,
  
  -- Usage tracking
  usage_count INTEGER DEFAULT 0,
  last_used TIMESTAMP WITH TIME ZONE
);

-- Indexes
CREATE INDEX idx_encryption_keys_lifecycle ON encryption_keys(activated_at, deactivated_at);
CREATE INDEX idx_encryption_keys_cleanup ON encryption_keys(delete_after);
```

### 9. System Operations

#### sync_sessions
```sql
CREATE TABLE sync_sessions (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  
  -- Session details
  session_type VARCHAR(20) NOT NULL CHECK (session_type IN ('full', 'incremental', 'idle', 'manual')),
  
  -- Status
  status VARCHAR(20) NOT NULL DEFAULT 'started' CHECK (status IN ('started', 'running', 'completed', 'error', 'cancelled')),
  
  -- Timing
  started_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP WITH TIME ZONE,
  duration_ms INTEGER,
  
  -- Progress tracking
  folders_to_sync INTEGER,
  folders_synced INTEGER DEFAULT 0,
  emails_processed INTEGER DEFAULT 0,
  
  -- Results
  new_emails INTEGER DEFAULT 0,
  updated_emails INTEGER DEFAULT 0,
  errors_encountered INTEGER DEFAULT 0,
  
  -- Error handling
  error_message TEXT,
  error_code VARCHAR(50),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_sync_sessions_account ON sync_sessions(account_id, started_at);
CREATE INDEX idx_sync_sessions_status ON sync_sessions(status, started_at);
CREATE INDEX idx_sync_sessions_type ON sync_sessions(session_type, completed_at);
```

#### folder_sync_status
```sql
CREATE TABLE folder_sync_status (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  sync_session_id UUID NOT NULL REFERENCES sync_sessions(id) ON DELETE CASCADE,
  folder_id UUID NOT NULL REFERENCES account_folders(id) ON DELETE CASCADE,
  
  -- Sync details
  sync_type VARCHAR(20) NOT NULL CHECK (sync_type IN ('full', 'incremental', 'uid_check')),
  
  -- Status
  status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (status IN ('pending', 'executing', 'completed', 'failed', 'retrying')),
  
  -- Timing
  requested_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  started_at TIMESTAMP WITH TIME ZONE,
  completed_at TIMESTAMP WITH TIME ZONE,
  
  -- Results
  success BOOLEAN,
  result_data JSONB,
  
  -- Error handling
  error_message TEXT,
  retry_count INTEGER DEFAULT 0,
  max_retries INTEGER DEFAULT 3,
  
  -- Idempotency
  idempotency_key VARCHAR(100),
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_imap_operations_account ON imap_operations(account_id, requested_at);
CREATE INDEX idx_imap_operations_status ON imap_operations(status, requested_at);
CREATE INDEX idx_imap_operations_folder ON imap_operations(source_folder_id) WHERE source_folder_id IS NOT NULL;
CREATE INDEX idx_imap_operations_idempotency ON imap_operations(idempotency_key) WHERE idempotency_key IS NOT NULL;
```

#### email_uid_tracking
```sql
CREATE TABLE email_uid_tracking (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  folder_id UUID NOT NULL REFERENCES account_folders(id) ON DELETE CASCADE,
  
  -- UID details
  uid INTEGER NOT NULL,
  message_id VARCHAR(500),
  
  -- Status
  status VARCHAR(20) DEFAULT 'active' CHECK (status IN ('active', 'deleted', 'moved', 'expunged')),
  
  -- Processing
  processed BOOLEAN DEFAULT FALSE,
  processed_email_id UUID, -- References processed_emails(id)
  
  -- Timestamps
  first_seen TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  last_seen TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  
  -- Constraints
  UNIQUE(account_id, folder_id, uid)
);

-- Indexes
CREATE INDEX idx_email_uid_tracking_folder_uid ON email_uid_tracking(folder_id, uid);
CREATE INDEX idx_email_uid_tracking_message_id ON email_uid_tracking(message_id) WHERE message_id IS NOT NULL;
CREATE INDEX idx_email_uid_tracking_processed ON email_uid_tracking(processed, first_seen) WHERE processed = FALSE;
```

### 10. Analytics and Reporting

#### log_exports
```sql
CREATE TABLE log_exports (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  
  -- Export details
  export_type VARCHAR(50) NOT NULL CHECK (export_type IN ('processed_emails', 'send_logs', 'decision_trace', 'escalations')),
  format VARCHAR(20) NOT NULL DEFAULT 'csv' CHECK (format IN ('csv', 'json', 'xlsx')),
  
  -- Filters applied
  filters JSONB NOT NULL,
  date_range_start TIMESTAMP WITH TIME ZONE,
  date_range_end TIMESTAMP WITH TIME ZONE,
  
  -- Export status
  status VARCHAR(20) NOT NULL DEFAULT 'requested' CHECK (status IN ('requested', 'processing', 'completed', 'failed', 'expired')),
  
  -- Results
  record_count INTEGER,
  file_size_bytes BIGINT,
  file_path TEXT,
  download_url TEXT,
  
  -- Expiry
  expires_at TIMESTAMP WITH TIME ZONE,
  
  -- Error handling
  error_message TEXT,
  
  -- Metadata
  requested_by UUID NOT NULL,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP WITH TIME ZONE
);

-- Indexes
CREATE INDEX idx_log_exports_requester ON log_exports(requested_by, created_at);
CREATE INDEX idx_log_exports_status ON log_exports(status, created_at);
CREATE INDEX idx_log_exports_expiry ON log_exports(expires_at) WHERE expires_at IS NOT NULL;
```

## Partitioning Strategy

### Time-Based Partitioning
Large tables with time-series data will use PostgreSQL's native partitioning:

```sql
-- Partition processed_emails by month
CREATE TABLE processed_emails_y2024m01 PARTITION OF processed_emails
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

CREATE TABLE processed_emails_y2024m02 PARTITION OF processed_emails
    FOR VALUES FROM ('2024-02-01') TO ('2024-03-01');

-- Partition decision_trace by month
CREATE TABLE decision_trace_y2024m01 PARTITION OF decision_trace
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition send_logs by month
CREATE TABLE send_logs_y2024m01 PARTITION OF send_logs
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition llm_requests by month
CREATE TABLE llm_requests_y2024m01 PARTITION OF llm_requests
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition prompt_usage_logs by month
CREATE TABLE prompt_usage_logs_y2024m01 PARTITION OF prompt_usage_logs
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition escalations by month
CREATE TABLE escalations_y2024m01 PARTITION OF escalations
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition escalation_audit_log by month
CREATE TABLE escalation_audit_log_y2024m01 PARTITION OF escalation_audit_log
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition bounce_logs by month
CREATE TABLE bounce_logs_y2024m01 PARTITION OF bounce_logs
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');

-- Partition processing_trace by month
CREATE TABLE processing_trace_y2024m01 PARTITION OF processing_trace
    FOR VALUES FROM ('2024-01-01') TO ('2024-02-01');
```

### Automated Partition Management
```sql
-- Function to create monthly partitions
CREATE OR REPLACE FUNCTION create_monthly_partitions(table_name TEXT, months_ahead INTEGER DEFAULT 3)
RETURNS VOID AS $$
DECLARE
    start_date DATE;
    end_date DATE;
    partition_name TEXT;
    i INTEGER;
BEGIN
    FOR i IN 0..months_ahead LOOP
        start_date := DATE_TRUNC('month', CURRENT_DATE + INTERVAL '1 month' * i);
        end_date := start_date + INTERVAL '1 month';
        partition_name := table_name || '_y' || EXTRACT(YEAR FROM start_date) || 'm' || LPAD(EXTRACT(MONTH FROM start_date)::TEXT, 2, '0');
        
        EXECUTE format('CREATE TABLE IF NOT EXISTS %I PARTITION OF %I FOR VALUES FROM (%L) TO (%L)',
                      partition_name, table_name, start_date, end_date);
    END LOOP;
END;
$$ LANGUAGE plpgsql;

-- Schedule partition creation (to be run monthly)
SELECT create_monthly_partitions('processed_emails');
SELECT create_monthly_partitions('decision_trace');
SELECT create_monthly_partitions('send_logs');
SELECT create_monthly_partitions('llm_requests');
SELECT create_monthly_partitions('prompt_usage_logs');
SELECT create_monthly_partitions('escalations');
SELECT create_monthly_partitions('escalation_audit_log');
SELECT create_monthly_partitions('bounce_logs');
SELECT create_monthly_partitions('processing_trace');
```

## Retention Policies

### Data Retention Configuration
```sql
CREATE TABLE data_retention_policies (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  table_name VARCHAR(100) NOT NULL UNIQUE,
  retention_days INTEGER NOT NULL,
  partition_column VARCHAR(100) DEFAULT 'created_at',
  archive_before_delete BOOLEAN DEFAULT TRUE,
  archive_location TEXT,
  is_active BOOLEAN DEFAULT TRUE,
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Insert retention policies
INSERT INTO data_retention_policies (table_name, retention_days, archive_before_delete) VALUES
('processed_emails', 2555, TRUE),  -- 7 years for compliance
('send_logs', 2555, TRUE),         -- 7 years for compliance
('decision_trace', 1095, TRUE),    -- 3 years
('llm_requests', 365, TRUE),       -- 1 year
('prompt_usage_logs', 365, TRUE),  -- 1 year
('escalations', 2555, TRUE),       -- 7 years for audit
('escalation_audit_log', 2555, TRUE), -- 7 years for audit
('bounce_logs', 1095, FALSE),      -- 3 years, no archive
('processing_trace', 90, FALSE),   -- 90 days, no archive
('sync_sessions', 30, FALSE),      -- 30 days
('folder_sync_status', 30, FALSE), -- 30 days
('imap_operations', 30, FALSE),    -- 30 days
('log_exports', 7, FALSE);         -- 7 days
```

### Automated Cleanup Function
```sql
CREATE OR REPLACE FUNCTION cleanup_old_data()
RETURNS VOID AS $$
DECLARE
    policy RECORD;
    cutoff_date TIMESTAMP WITH TIME ZONE;
    archive_sql TEXT;
    delete_sql TEXT;
BEGIN
    FOR policy IN SELECT * FROM data_retention_policies WHERE is_active = TRUE LOOP
        cutoff_date := CURRENT_TIMESTAMP - INTERVAL '1 day' * policy.retention_days;
        
        -- Archive if required
        IF policy.archive_before_delete AND policy.archive_location IS NOT NULL THEN
            archive_sql := format('COPY (SELECT * FROM %I WHERE %I < %L) TO %L WITH CSV HEADER',
                                policy.table_name, policy.partition_column, cutoff_date, policy.archive_location);
            EXECUTE archive_sql;
        END IF;
        
        -- Delete old data
        delete_sql := format('DELETE FROM %I WHERE %I < %L',
                           policy.table_name, policy.partition_column, cutoff_date);
        EXECUTE delete_sql;
        
        RAISE NOTICE 'Cleaned up % records older than % from %', 
                     ROW_COUNT, cutoff_date, policy.table_name;
    END LOOP;
END;
$$ LANGUAGE plpgsql;
```

## Seed Data

### Default LLM Providers
```sql
INSERT INTO llm_providers (name, provider_type, available_models, default_model, supports_classification, supports_generation, requests_per_minute, priority) VALUES
('OpenAI GPT-4', 'openai', '["gpt-4", "gpt-4-turbo", "gpt-3.5-turbo"]', 'gpt-4-turbo', TRUE, TRUE, 500, 100),
('Google Gemini', 'gemini', '["gemini-pro", "gemini-pro-vision"]', 'gemini-pro', TRUE, TRUE, 300, 200),
('OpenAI GPT-3.5 Fallback', 'openai', '["gpt-3.5-turbo"]', 'gpt-3.5-turbo', TRUE, TRUE, 1000, 300);
```

### Default Prompt Templates
```sql
INSERT INTO prompts (name, description, category, intent, template_content, required_variables, status) VALUES
('Email Classification', 'Classify incoming emails by intent and urgency', 'classification', 'classify', 
 'Analyze this email and classify it:\n\nSubject: {{subject}}\nFrom: {{from_address}}\nBody: {{body_text}}\n\nProvide classification in JSON format with intent, category, sentiment, and urgency.', 
 '["subject", "from_address", "body_text"]', 'active'),
 
('Professional Response', 'Generate professional email responses', 'response', 'respond',
 'Generate a professional response to this email:\n\nOriginal Email:\nSubject: {{subject}}\nFrom: {{from_address}}\nBody: {{body_text}}\n\nContext: {{context}}\nTone: {{tone}}\n\nGenerate an appropriate response.',
 '["subject", "from_address", "body_text", "context", "tone"]', 'active'),
 
('Escalation Review', 'Template for human escalation review', 'escalation', 'review',
 'This email requires human review:\n\nReason: {{escalation_reason}}\nRisk Level: {{risk_level}}\nAI Confidence: {{confidence_score}}\n\nOriginal Email:\n{{email_content}}\n\nRecommended Action: {{recommended_action}}',
 '["escalation_reason", "risk_level", "confidence_score", "email_content", "recommended_action"]', 'active');
```

### Default System Rules
```sql
-- Note: These would be inserted per account, shown as template
INSERT INTO rules (account_id, name, description, conditions, actions, priority, status) VALUES
('00000000-0000-0000-0000-000000000000', 'High Priority Keywords', 'Escalate emails with urgent keywords',
 '{"keywords": ["urgent", "emergency", "asap", "critical"], "match_type": "any"}',
 '{"action": "escalate", "priority": "high", "reason": "urgent_keywords"}', 10, 'active'),

('00000000-0000-0000-0000-000000000000', 'Auto-Reply Simple Queries', 'Auto-respond to simple questions',
 '{"intent": ["question", "inquiry"], "confidence_threshold": 0.8, "risk_level": "low"}',
 '{"action": "auto_send", "template": "professional_response", "tone": "helpful"}', 100, 'active'),

('00000000-0000-0000-0000-000000000000', 'Complaint Escalation', 'Escalate all complaints to human review',
 '{"sentiment": "negative", "intent": "complaint"}',
 '{"action": "escalate", "priority": "medium", "reason": "complaint_detected"}', 20, 'active');
```

## Migration Order and Strategy

### Migration Phases

#### Phase 1: Core Infrastructure (V1.0.0)
```sql
-- Migration 001: Create schemas and extensions
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
CREATE EXTENSION IF NOT EXISTS "pg_trgm";
CREATE EXTENSION IF NOT EXISTS "btree_gin";
CREATE SCHEMA IF NOT EXISTS email_agent;
CREATE SCHEMA IF NOT EXISTS audit;
CREATE SCHEMA IF NOT EXISTS analytics;

-- Migration 002: Create base functions
-- (update_updated_at_column function)

-- Migration 003: Create core tables
-- accounts, account_folders, encrypted_secrets, encryption_keys
```

#### Phase 2: Rules and Processing (V1.1.0)
```sql
-- Migration 004: Rules engine
-- rules, decision_trace, rule_execution_stats

-- Migration 005: Email processing
-- processed_emails, processing_trace

-- Migration 006: Sync infrastructure
-- sync_sessions, folder_sync_status, imap_operations, email_uid_tracking
```

#### Phase 3: LLM and Prompts (V1.2.0)
```sql
-- Migration 007: LLM providers
-- llm_providers, llm_requests, llm_rate_limits

-- Migration 008: Prompt library
-- prompts, prompt_mappings, prompt_test_runs, prompt_usage_logs
```

#### Phase 4: Sending and Escalation (V1.3.0)
```sql
-- Migration 009: Email sending
-- send_logs, recipient_delivery_status, bounce_logs, suppression_list

-- Migration 010: Human escalation
-- escalations, escalation_drafts, escalation_locks, escalation_audit_log, escalation_sla_tracking
```

#### Phase 5: Analytics and Operations (V1.4.0)
```sql
-- Migration 011: Analytics and reporting
-- log_exports, data_retention_policies

-- Migration 012: Partitioning setup
-- Create initial partitions and partition management functions

-- Migration 013: Seed data
-- Insert default providers, prompts, and system configurations
```

### Migration Script Template
```sql
-- Migration: V1.0.001_create_core_infrastructure.sql
-- Description: Create schemas, extensions, and base functions
-- Author: System
-- Date: 2024-01-15

BEGIN;

-- Check if migration already applied
DO $$
BEGIN
    IF EXISTS (SELECT 1 FROM schema_migrations WHERE version = '1.0.001') THEN
        RAISE EXCEPTION 'Migration 1.0.001 already applied';
    END IF;
END
$$;

-- Migration content here
CREATE EXTENSION IF NOT EXISTS "uuid-ossp";
-- ... rest of migration

-- Record migration
INSERT INTO schema_migrations (version, description, applied_at) 
VALUES ('1.0.001', 'Create core infrastructure', CURRENT_TIMESTAMP);

COMMIT;
```

### Migration Tracking
```sql
CREATE TABLE schema_migrations (
  version VARCHAR(20) PRIMARY KEY,
  description TEXT NOT NULL,
  applied_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP,
  rollback_sql TEXT,
  checksum VARCHAR(64)
);
```

## Rollback Procedures

### Rollback Strategy
1. **Backward Compatible Changes**: No rollback needed
2. **Schema Changes**: Provide explicit rollback SQL
3. **Data Migrations**: Backup before migration, restore on rollback
4. **Breaking Changes**: Require maintenance window and full backup

### Rollback Templates
```sql
-- Rollback for table creation
DROP TABLE IF EXISTS new_table CASCADE;

-- Rollback for column addition (backward compatible)
-- No action needed - old code will ignore new columns

-- Rollback for column removal (breaking change)
ALTER TABLE table_name ADD COLUMN old_column_name data_type;
UPDATE table_name SET old_column_name = backup_value FROM backup_table;

-- Rollback for data migration
DELETE FROM target_table WHERE migration_batch = 'batch_id';
INSERT INTO source_table SELECT * FROM backup_table WHERE migration_batch = 'batch_id';
```

### Emergency Rollback Procedure
```sql
-- 1. Stop all application services
-- 2. Create point-in-time backup
pg_dump --verbose --no-owner --no-privileges email_agent_db > rollback_backup.sql

-- 3. Execute rollback migration
BEGIN;
-- Rollback SQL here
DELETE FROM schema_migrations WHERE version = 'target_version';
COMMIT;

-- 4. Verify data integrity
SELECT COUNT(*) FROM critical_tables;

-- 5. Restart application services
```

## Performance Considerations

### Index Strategy

#### Composite Indexes for Common Query Patterns
```sql
-- Email processing workflow indexes
CREATE INDEX idx_processed_emails_workflow ON processed_emails(account_id, processing_status, created_at);
CREATE INDEX idx_decision_trace_workflow ON decision_trace(email_id, rule_matched, created_at);
CREATE INDEX idx_send_logs_workflow ON send_logs(account_id, send_status, queued_at);

-- Escalation management indexes
CREATE INDEX idx_escalations_queue ON escalations(status, priority, sla_deadline);
CREATE INDEX idx_escalations_assignment ON escalations(assigned_to, status, updated_at);

-- Analytics and reporting indexes
CREATE INDEX idx_processed_emails_analytics ON processed_emails(account_id, intent, category, partition_date);
CREATE INDEX idx_llm_requests_analytics ON llm_requests(provider_id, request_type, success, partition_date);
```

#### Partial Indexes for Efficiency
```sql
-- Only index active/problematic records
CREATE INDEX idx_accounts_issues ON accounts(health_status, consecutive_failures) 
    WHERE health_status != 'healthy' OR consecutive_failures > 0;

CREATE INDEX idx_rules_active ON rules(account_id, priority, execution_order) 
    WHERE status = 'active';

CREATE INDEX idx_escalations_pending ON escalations(created_at, priority) 
    WHERE status IN ('pending', 'assigned', 'in_review');
```

### Query Optimization

#### Materialized Views for Analytics
```sql
-- Daily email processing summary
CREATE MATERIALIZED VIEW daily_processing_summary AS
SELECT 
    account_id,
    DATE(created_at) as processing_date,
    COUNT(*) as total_emails,
    COUNT(*) FILTER (WHERE processing_status = 'completed') as processed_emails,
    COUNT(*) FILTER (WHERE processing_status = 'error') as failed_emails,
    AVG(processing_time_ms) as avg_processing_time,
    COUNT(DISTINCT intent) as unique_intents
FROM processed_emails 
WHERE created_at >= CURRENT_DATE - INTERVAL '90 days'
GROUP BY account_id, DATE(created_at);

CREATE UNIQUE INDEX idx_daily_processing_summary ON daily_processing_summary(account_id, processing_date);

-- Refresh schedule (run daily)
CREATE OR REPLACE FUNCTION refresh_daily_summaries()
RETURNS VOID AS $$
BEGIN
    REFRESH MATERIALIZED VIEW CONCURRENTLY daily_processing_summary;
END;
$$ LANGUAGE plpgsql;
```

### Connection and Resource Management

#### Connection Pooling Configuration
```ini
# PgBouncer configuration
[databases]
email_agent = host=localhost port=5432 dbname=email_agent_db

[pgbouncer]
pool_mode = transaction
max_client_conn = 1000
default_pool_size = 25
max_db_connections = 100
reserve_pool_size = 5
reserve_pool_timeout = 3
```

#### Resource Limits
```sql
-- Set appropriate work_mem for large operations
SET work_mem = '256MB';

-- Configure maintenance operations
SET maintenance_work_mem = '1GB';
SET max_parallel_workers_per_gather = 4;
```

## Monitoring and Observability

### Database Health Monitoring
```sql
-- Create monitoring views
CREATE VIEW db_health_metrics AS
SELECT 
    'table_sizes' as metric_type,
    schemaname,
    tablename,
    pg_size_pretty(pg_total_relation_size(schemaname||'.'||tablename)) as size,
    pg_total_relation_size(schemaname||'.'||tablename) as size_bytes
FROM pg_tables 
WHERE schemaname = 'email_agent'
UNION ALL
SELECT 
    'index_usage' as metric_type,
    schemaname,
    indexname,
    idx_scan::text,
    idx_tup_read
FROM pg_stat_user_indexes
WHERE schemaname = 'email_agent';

-- Slow query monitoring
CREATE VIEW slow_queries AS
SELECT 
    query,
    calls,
    total_time,
    mean_time,
    rows,
    100.0 * shared_blks_hit / nullif(shared_blks_hit + shared_blks_read, 0) AS hit_percent
FROM pg_stat_statements 
WHERE mean_time > 1000  -- Queries taking more than 1 second
ORDER BY mean_time DESC;
```

### Application Metrics
```sql
-- Processing pipeline health
CREATE VIEW processing_pipeline_health AS
SELECT 
    account_id,
    COUNT(*) FILTER (WHERE created_at >= NOW() - INTERVAL '1 hour') as emails_last_hour,
    COUNT(*) FILTER (WHERE processing_status = 'error' AND created_at >= NOW() - INTERVAL '1 hour') as errors_last_hour,
    AVG(processing_time_ms) FILTER (WHERE created_at >= NOW() - INTERVAL '1 hour') as avg_processing_time,
    MAX(created_at) as last_email_processed
FROM processed_emails
GROUP BY account_id;

-- Rule performance metrics
CREATE VIEW rule_performance_metrics AS
SELECT 
    r.id,
    r.name,
    r.account_id,
    COUNT(dt.*) as total_evaluations,
    COUNT(*) FILTER (WHERE dt.rule_matched = true) as successful_matches,
    AVG(dt.execution_time_ms) as avg_execution_time,
    COUNT(*) FILTER (WHERE dt.error_message IS NOT NULL) as error_count
FROM rules r
LEFT JOIN decision_trace dt ON r.id = dt.rule_id
WHERE dt.created_at >= NOW() - INTERVAL '24 hours'
GROUP BY r.id, r.name, r.account_id;
```

## Security Considerations

### Row Level Security (RLS)
```sql
-- Enable RLS on sensitive tables
ALTER TABLE accounts ENABLE ROW LEVEL SECURITY;
ALTER TABLE processed_emails ENABLE ROW LEVEL SECURITY;
ALTER TABLE send_logs ENABLE ROW LEVEL SECURITY;
ALTER TABLE escalations ENABLE ROW LEVEL SECURITY;

-- Create policies for multi-tenant access
CREATE POLICY account_isolation ON accounts
    FOR ALL TO application_role
    USING (id = current_setting('app.current_account_id')::UUID);

CREATE POLICY email_isolation ON processed_emails
    FOR ALL TO application_role
    USING (account_id = current_setting('app.current_account_id')::UUID);
```

### Audit Logging
```sql
-- Create audit trigger function
CREATE OR REPLACE FUNCTION audit_trigger_function()
RETURNS TRIGGER AS $$
BEGIN
    INSERT INTO audit.audit_log (
        table_name,
        operation,
        old_values,
        new_values,
        changed_by,
        changed_at
    ) VALUES (
        TG_TABLE_NAME,
        TG_OP,
        CASE WHEN TG_OP = 'DELETE' THEN row_to_json(OLD) ELSE NULL END,
        CASE WHEN TG_OP IN ('INSERT', 'UPDATE') THEN row_to_json(NEW) ELSE NULL END,
        current_setting('app.current_user_id', true),
        CURRENT_TIMESTAMP
    );
    
    RETURN CASE WHEN TG_OP = 'DELETE' THEN OLD ELSE NEW END;
END;
$$ LANGUAGE plpgsql;

-- Apply audit triggers to sensitive tables
CREATE TRIGGER audit_accounts AFTER INSERT OR UPDATE OR DELETE ON accounts
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();

CREATE TRIGGER audit_rules AFTER INSERT OR UPDATE OR DELETE ON rules
    FOR EACH ROW EXECUTE FUNCTION audit_trigger_function();
```

## Disaster Recovery

### Backup Strategy
```bash
#!/bin/bash
# Daily backup script

# Full database backup
pg_dump --verbose --no-owner --no-privileges --format=custom \
    --file="/backups/email_agent_$(date +%Y%m%d_%H%M%S).backup" \
    email_agent_db

# WAL archiving for point-in-time recovery
archive_command = 'cp %p /backups/wal_archive/%f'

# Retention: Keep daily backups for 30 days, weekly for 12 weeks
find /backups -name "email_agent_*.backup" -mtime +30 -delete
```

### Recovery Procedures
```sql
-- Point-in-time recovery example
-- 1. Stop PostgreSQL
-- 2. Restore from base backup
psql -c "SELECT pg_start_backup('disaster_recovery');"
tar -xzf /backups/base_backup.tar.gz -C /var/lib/postgresql/data/

-- 3. Configure recovery
echo "restore_command = 'cp /backups/wal_archive/%f %p'" > recovery.conf
echo "recovery_target_time = '2024-01-15 14:30:00'" >> recovery.conf

-- 4. Start PostgreSQL and verify
psql -c "SELECT NOW(), pg_is_in_recovery();"
```

## Summary

This comprehensive schema design provides:

### ✅ **Complete Entity Coverage**
- **25+ core tables** covering all system components
- **Proper relationships** with foreign keys and constraints
- **Comprehensive indexing** for performance optimization
- **Partitioning strategy** for large-scale data management

### ✅ **Production-Ready Features**
- **Time-based partitioning** for high-volume tables
- **Automated retention policies** with configurable cleanup
- **Comprehensive audit trails** for compliance
- **Row-level security** for multi-tenant isolation

### ✅ **Operational Excellence**
- **Phased migration strategy** with rollback procedures
- **Performance monitoring** with materialized views
- **Disaster recovery** with backup and restore procedures
- **Security controls** with encryption and access policies

### ✅ **Scalability Considerations**
- **Connection pooling** configuration
- **Query optimization** with proper indexing
- **Resource management** for large operations
- **Monitoring and alerting** for proactive maintenance

### 🔧 **Implementation Notes**
1. **Start with Phase 1** (core infrastructure) for POC
2. **Test migrations** in staging environment first
3. **Monitor performance** metrics during rollout
4. **Implement backup strategy** before production deployment
5. **Configure monitoring** for early issue detection

This schema design supports the complete email agent system from POC through production scale, with proper consideration for performance, security, compliance, and operational requirements.
  messages_found INTEGER,
  messages_processed INTEGER DEFAULT 0,
  new_messages INTEGER DEFAULT 0,
  updated_messages INTEGER DEFAULT 0,
  
  -- UID tracking
  last_synced_uid INTEGER,
  highest_uid_seen INTEGER,
  
  -- Error handling
  error_message TEXT,
  retry_count INTEGER DEFAULT 0,
  
  -- Metadata
  created_at TIMESTAMP WITH TIME ZONE DEFAULT CURRENT_TIMESTAMP
);

-- Indexes
CREATE INDEX idx_folder_sync_status_session ON folder_sync_status(sync_session_id);
CREATE INDEX idx_folder_sync_status_folder ON folder_sync_status(folder_id, completed_at);
CREATE INDEX idx_folder_sync_status_status ON folder_sync_status(status, started_at);
```

#### imap_operations
```sql
CREATE TABLE imap_operations (
  id UUID PRIMARY KEY DEFAULT gen_random_uuid(),
  account_id UUID NOT NULL REFERENCES accounts(id) ON DELETE CASCADE,
  
  -- Operation details
  operation_type VARCHAR(50) NOT NULL CHECK (operation_type IN ('move', 'copy', 'flag', 'unflag', 'delete', 'append')),
  
  -- Target details
  source_folder_id UUID REFERENCES account_folders(id) ON DELETE SET NULL,
  target_folder_id UUID REFERENCES account_folders(id) ON DELETE SET NULL,
  message_uid INTEGER,
  
  -- Operation data
  operation_data JSONB,
  
  -- Status
  status VARCHAR(20) NOT NULL DEFAULT 'pending' CHECK (