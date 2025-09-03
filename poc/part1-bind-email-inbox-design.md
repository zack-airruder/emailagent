# Part 1: Dashboard → Bind Email (IMAP/SMTP) + Inbox (SnappyMail)

## Overview
Design for email account binding, health monitoring, and SnappyMail integration with single sign-on and automation triggers.

## HTTP Interfaces

### Account Management

#### POST /accounts/test-imap
```json
{
  "host": "string",
  "port": "number",
  "username": "string", 
  "password": "string",
  "tls": "boolean",
  "starttls": "boolean"
}
```
**Response:**
```json
{
  "success": "boolean",
  "error_code": "string?", // "AUTH_FAILED", "TLS_ERROR", "TIMEOUT", "INVALID_HOST"
  "capabilities": "string[]?", // ["IDLE", "UIDPLUS", "MOVE"]
  "folders": "object[]?", // [{"name": "INBOX", "delimiter": "/", "flags": ["\\HasNoChildren"]}]
  "latency_ms": "number?"
}
```

#### POST /accounts/test-smtp
```json
{
  "host": "string",
  "port": "number",
  "username": "string",
  "password": "string", 
  "tls": "boolean",
  "starttls": "boolean"
}
```
**Response:**
```json
{
  "success": "boolean",
  "error_code": "string?", // "AUTH_FAILED", "TLS_ERROR", "RELAY_DENIED"
  "extensions": "string[]?", // ["8BITMIME", "DSN", "SIZE"]
  "max_size_mb": "number?",
  "latency_ms": "number?"
}
```

#### POST /accounts
```json
{
  "alias": "string", // User-friendly name
  "email": "string",
  "imap_host": "string",
  "imap_port": "number",
  "imap_username": "string",
  "imap_password": "string", // Will be stored in SnappyMail config
  "imap_tls": "boolean",
  "imap_starttls": "boolean",
  "smtp_host": "string",
  "smtp_port": "number", 
  "smtp_username": "string",
  "smtp_password": "string",
  "smtp_tls": "boolean",
  "smtp_starttls": "boolean",
  "folder_mapping": {
    "inbox": "string", // "INBOX"
    "drafts": "string", // "Drafts" 
    "sent": "string", // "Sent"
    "archive": "string" // "Archive"
  }
}
```
**Response:**
```json
{
  "id": "string", // UUID
  "alias": "string",
  "email": "string",
  "status": "string", // "active", "testing", "error"
  "created_at": "string" // ISO 8601
}
```

#### PUT /accounts/:id
```json
{
  "alias": "string?",
  "folder_mapping": "object?",
  "status": "string?" // "active", "paused", "deprecated"
}
```

#### GET /accounts/:id/health
**Response:**
```json
{
  "account_id": "string",
  "overall_status": "string", // "healthy", "partial", "error", "locked"
  "imap_status": {
    "status": "string", // "connected", "idle", "error", "timeout"
    "last_sync": "string", // ISO 8601
    "idle_supported": "boolean",
    "connection_count": "number",
    "error_message": "string?"
  },
  "smtp_status": {
    "status": "string", // "ready", "error", "rate_limited"
    "last_test": "string", // ISO 8601
    "daily_sent": "number",
    "daily_limit": "number",
    "error_message": "string?"
  },
  "tls_info": {
    "imap_tls_version": "string?", // "TLSv1.3"
    "smtp_tls_version": "string?",
    "cert_expiry": "string?", // ISO 8601
    "self_signed": "boolean"
  },
  "checked_at": "string" // ISO 8601
}
```

### Automation Trigger

#### POST /automation/trigger
```json
{
  "account_id": "string",
  "mailbox": "string", // "INBOX"
  "uid": "number",
  "message_id": "string?", // RFC822 Message-ID
  "trigger_source": "string" // "snappymail_ui", "imap_idle", "manual"
}
```
**Response:**
```json
{
  "processing_id": "string", // UUID for tracking
  "status": "string", // "queued", "processing", "completed", "failed"
  "estimated_completion": "string" // ISO 8601
}
```

## Data Schemas

### accounts
```sql
CREATE TABLE accounts (
  id VARCHAR(36) PRIMARY KEY, -- UUID
  alias VARCHAR(255) NOT NULL,
  email VARCHAR(255) NOT NULL UNIQUE,
  
  -- Endpoint configuration (no credentials)
  imap_host VARCHAR(255) NOT NULL,
  imap_port INT NOT NULL,
  imap_tls BOOLEAN DEFAULT TRUE,
  imap_starttls BOOLEAN DEFAULT FALSE,
  
  smtp_host VARCHAR(255) NOT NULL, 
  smtp_port INT NOT NULL,
  smtp_tls BOOLEAN DEFAULT TRUE,
  smtp_starttls BOOLEAN DEFAULT FALSE,
  
  -- Capability flags
  imap_capabilities JSON, -- ["IDLE", "UIDPLUS", "MOVE"]
  smtp_extensions JSON, -- ["8BITMIME", "DSN", "SIZE"]
  smtp_max_size_mb INT DEFAULT 25,
  
  -- Status and health
  status ENUM('active', 'paused', 'testing', 'error', 'deprecated') DEFAULT 'testing',
  health_status ENUM('healthy', 'partial', 'error', 'locked') DEFAULT 'error',
  last_health_check TIMESTAMP NULL,
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_email (email),
  INDEX idx_status (status),
  INDEX idx_health_check (last_health_check)
);
```

### account_folders
```sql
CREATE TABLE account_folders (
  id VARCHAR(36) PRIMARY KEY,
  account_id VARCHAR(36) NOT NULL,
  folder_type ENUM('inbox', 'drafts', 'sent', 'archive', 'custom') NOT NULL,
  folder_name VARCHAR(255) NOT NULL, -- Actual IMAP folder name
  folder_delimiter VARCHAR(10) DEFAULT '/',
  folder_flags JSON, -- IMAP flags like ["\\HasNoChildren"]
  
  -- Sync metadata
  uidvalidity BIGINT UNSIGNED,
  last_uid BIGINT UNSIGNED DEFAULT 0,
  message_count INT DEFAULT 0,
  last_sync TIMESTAMP NULL,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  UNIQUE KEY unique_account_folder_type (account_id, folder_type),
  INDEX idx_account_sync (account_id, last_sync)
);
```

## UI State Machine

### Account Health States
```
┌─────────────┐    test_success    ┌─────────────┐
│   testing   │ ──────────────────→ │   healthy   │
└─────────────┘                    └─────────────┘
       │                                   │
       │ test_failure                      │ health_check_fail
       ↓                                   ↓
┌─────────────┐    partial_recovery ┌─────────────┐
│    error    │ ←──────────────────→ │   partial   │
└─────────────┘                    └─────────────┘
       │                                   │
       │ admin_lock                        │ admin_lock
       ↓                                   ↓
┌─────────────┐    admin_unlock     ┌─────────────┐
│   locked    │ ←─────────────────── │   locked    │
└─────────────┘                    └─────────────┘
```

**State Definitions:**
- **testing**: Initial state during account setup/validation
- **healthy**: Both IMAP and SMTP working, recent successful health check
- **partial**: One service working (e.g., IMAP ok, SMTP failing)
- **error**: Both services failing or critical errors
- **locked**: Manually disabled by admin (security/abuse)

### UI Status Indicators
```json
{
  "healthy": {
    "color": "green",
    "icon": "check-circle",
    "message": "All services operational"
  },
  "partial": {
    "color": "yellow", 
    "icon": "exclamation-triangle",
    "message": "IMAP connected, SMTP issues detected"
  },
  "error": {
    "color": "red",
    "icon": "x-circle", 
    "message": "Connection failed - check credentials"
  },
  "locked": {
    "color": "gray",
    "icon": "lock",
    "message": "Account disabled by administrator"
  },
  "deprecated_tls": {
    "color": "orange",
    "icon": "shield-exclamation",
    "message": "TLS version deprecated - update server"
  }
}
```

## Sequence Flows

### Account Creation Flow
```
User → Admin UI → Backend → SnappyMail Config → Backend → Admin UI → User

1. User fills account form
2. Admin UI calls POST /accounts/test-imap
3. Backend tests IMAP connection
4. Admin UI calls POST /accounts/test-smtp  
5. Backend tests SMTP connection
6. User confirms settings
7. Admin UI calls POST /accounts
8. Backend:
   a. Creates account record (no credentials)
   b. Writes credentials to SnappyMail config file
   c. Creates default folder mappings
   d. Schedules initial health check
9. Returns account ID and status
```

### SnappyMail SSO Flow
```
Admin UI → Backend → SnappyMail → User

1. User clicks "Open Inbox" for account
2. Admin UI calls POST /sso/snappymail-token
3. Backend:
   a. Validates user session
   b. Generates time-limited SSO token (5min TTL)
   c. Signs token with shared secret
4. Admin UI redirects to /mail?sso_token=xxx&account=yyy
5. SnappyMail:
   a. Validates SSO token signature
   b. Auto-logs user into specified account
   c. Adds "Send to Automation" button to message actions
```

### Automation Trigger Flow
```
SnappyMail → Backend → Queue → Processing

1. User selects message in SnappyMail
2. User clicks "Send to Automation"
3. SnappyMail calls POST /automation/trigger
4. Backend:
   a. Validates account_id and message exists
   b. Creates processing record
   c. Queues message for LLM classification
   d. Returns processing_id
5. SnappyMail shows "Queued for automation" status
```

## Edge Cases & Error Handling

### OAuth vs App Passwords
- **Detection**: Check for OAuth2 in SMTP/IMAP capabilities
- **Handling**: Show OAuth flow UI vs password field
- **Storage**: OAuth tokens in SnappyMail, refresh tokens encrypted

### Self-Signed TLS Certificates
- **Detection**: Certificate validation error during test
- **UI**: Show warning with certificate details
- **Option**: "Accept self-signed" checkbox (security warning)
- **Storage**: Store certificate fingerprint for validation

### 2FA Plugin in SnappyMail
- **Integration**: SnappyMail 2FA plugin handles authentication
- **SSO**: Skip 2FA for SSO tokens (already authenticated in admin)
- **Fallback**: Direct login link if SSO fails

### UIDVALIDITY Changes
- **Detection**: UIDVALIDITY mismatch during sync
- **Action**: Reset folder sync state, re-index messages
- **Logging**: Log UIDVALIDITY change event for audit

### Large Mailboxes
- **Pagination**: Fetch messages in batches of 100
- **Timeout**: 30s timeout per IMAP operation
- **Progress**: Show sync progress in health status

## Test Matrix

### Host/Port/TLS Combinations
| Provider | IMAP | IMAP TLS | SMTP | SMTP TLS | Notes |
|----------|------|----------|------|----------|-------|
| Gmail | 993 | SSL | 587 | STARTTLS | OAuth preferred |
| Outlook | 993 | SSL | 587 | STARTTLS | App passwords |
| Yahoo | 993 | SSL | 587 | STARTTLS | App passwords |
| Custom | 143 | STARTTLS | 25 | STARTTLS | Self-hosted |
| Custom | 993 | SSL | 465 | SSL | Legacy config |

### Error Scenarios
- **Invalid credentials**: Clear error message, no retry
- **Network timeout**: Retry with exponential backoff
- **TLS handshake failure**: Show TLS version details
- **Certificate expired**: Show expiry date, allow override
- **Rate limiting**: Show retry-after time
- **Quota exceeded**: Show usage stats if available

### Non-ASCII Folder Names
- **UTF-7 encoding**: Handle IMAP UTF-7 folder names
- **Unicode display**: Show proper Unicode in UI
- **Mapping validation**: Ensure required folders exist

### IDLE Reconnection
- **Connection drops**: Auto-reconnect with exponential backoff
- **IDLE timeout**: Re-establish IDLE every 29 minutes
- **Multiple connections**: Limit to 2 concurrent IMAP connections

## Security Considerations

### Credential Protection
- **Storage**: Credentials only in SnappyMail config files
- **Transmission**: HTTPS only, no credentials in logs
- **Access**: File permissions 600 on config files
- **Rotation**: Support credential updates without downtime

### CSRF Protection
- **Tokens**: CSRF tokens on all state-changing operations
- **SameSite**: Cookies with SameSite=Strict
- **Referer**: Validate Referer header on sensitive endpoints

### SSO Token Security
- **Expiry**: 5-minute TTL on SSO tokens
- **Signing**: HMAC-SHA256 with 256-bit secret
- **Single-use**: Tokens invalidated after first use
- **Scope**: Tokens tied to specific account and user

### Log Redaction
```json
{
  "event": "imap_test",
  "account_id": "uuid",
  "host": "imap.gmail.com",
  "port": 993,
  "username": "user@*****.com", // Redacted
  "password": "[REDACTED]",
  "result": "success",
  "latency_ms": 234
}
```

## Risk Assessment & Mitigations

| Risk | Impact | Probability | Mitigation |
|------|--------|-------------|------------|
| Credential theft | High | Medium | File permissions, no DB storage |
| IMAP bombing | Medium | Low | Rate limiting, connection limits |
| TLS downgrade | High | Low | Enforce TLS 1.2+, cert pinning |
| SSRF via IMAP host | Medium | Medium | Validate hostnames, block private IPs |
| SnappyMail RCE | High | Low | Keep SnappyMail updated, sandbox |
| SSO token replay | Medium | Low | Short TTL, single-use tokens |
| Folder traversal | Low | Low | Validate folder names, sanitize paths |

## Deployment Configuration

### Environment Variables
```bash
# Database
DB_HOST=localhost
DB_PORT=3306
DB_NAME=emailagent
DB_USER=emailagent
DB_PASS=secure_password

# SnappyMail Integration
SNAPPYMAIL_PATH=/var/www/snappymail
SNAPPYMAIL_CONFIG_PATH=/var/www/snappymail/data
SSO_SECRET=256_bit_random_key

# Security
CSRF_SECRET=256_bit_random_key
SESSION_SECRET=256_bit_random_key

# Limits
MAX_ACCOUNTS_PER_USER=10
IMAP_CONNECTION_TIMEOUT=30
SMTP_CONNECTION_TIMEOUT=15
HEALTH_CHECK_INTERVAL=300
```

### Health Check Intervals
- **Active accounts**: Every 5 minutes
- **Partial accounts**: Every 2 minutes  
- **Error accounts**: Every 15 minutes
- **Locked accounts**: No health checks

---

## Pragmatic Review

### ✅ Interface Completeness
- All required endpoints defined with precise schemas
- Request/response formats specified
- Error codes and handling documented

### ✅ Data Schema Validation
- Primary keys, foreign keys, and indexes defined
- Appropriate data types and constraints
- Migration-friendly structure

### ✅ Security Coverage
- Credential protection strategy
- CSRF and SSO token security
- Log redaction and access controls
- Risk assessment with mitigations

### ✅ Error Path Coverage
- Network failures, authentication errors
- TLS issues, certificate problems
- Edge cases like UIDVALIDITY changes
- Graceful degradation strategies

### ✅ Test Matrix
- Provider-specific configurations
- Error scenario coverage
- Unicode and encoding edge cases
- Connection management testing

### Potential Issues Identified:
1. **SSO Token Storage**: Consider Redis for distributed deployments
2. **Health Check Load**: May need circuit breaker for failing accounts
3. **Folder Mapping**: Need validation that required folders exist
4. **Concurrent Access**: SnappyMail and automation may conflict on same message

### Recommended Fixes:
1. Add Redis option for SSO token storage
2. Implement circuit breaker pattern for health checks
3. Add folder existence validation in account creation
4. Add message locking mechanism for concurrent access protection