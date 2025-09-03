# Part 5: IMAP Ingest & Operators

## Overview
Design for IMAP email ingestion system with IDLE monitoring, periodic sync, and email operations (move, copy, flag management, draft creation). Focuses on reliability, idempotency, and efficient synchronization.

## Goals
- **New-mail detection**: IDLE + periodic fallback for real-time ingestion
- **Idempotency**: Prevent duplicate processing of emails
- **Reliability**: Handle connection drops, server issues, and partial syncs
- **Operations**: Move/copy emails, manage flags, append drafts
- **Performance**: Efficient sync with minimal bandwidth usage

## HTTP Interfaces

### Sync Management

#### POST /imap/accounts/:id/sync
**Trigger Manual Sync:**
```json
{
  "sync_type": "string", // "full", "incremental", "folder"
  "folders": "string[]?", // Specific folders to sync
  "force": "boolean?", // Force sync even if recently synced
  "priority": "string?" // "high", "normal", "low"
}
```

**Response:**
```json
{
  "sync_id": "string",
  "status": "string", // "started", "queued", "running", "completed", "failed"
  "account_id": "string",
  "sync_type": "string",
  "folders_to_sync": "string[]",
  "estimated_duration_seconds": "number",
  "started_at": "string",
  "progress": {
    "folders_completed": "number",
    "folders_total": "number",
    "emails_processed": "number",
    "emails_total": "number?",
    "current_folder": "string?"
  }
}
```

#### GET /imap/accounts/:id/sync/status
**Get Sync Status:**
```json
{
  "account_id": "string",
  "current_sync": {
    "sync_id": "string?",
    "status": "string",
    "started_at": "string?",
    "progress": "object?"
  },
  "last_successful_sync": {
    "sync_id": "string",
    "completed_at": "string",
    "emails_processed": "number",
    "duration_seconds": "number"
  },
  "idle_status": {
    "enabled": "boolean",
    "connected": "boolean",
    "folder": "string?",
    "last_activity": "string?"
  },
  "health": {
    "connection_status": "string", // "connected", "disconnected", "error"
    "last_error": "string?",
    "consecutive_failures": "number",
    "next_retry_at": "string?"
  }
}
```

#### POST /imap/accounts/:id/idle/start
**Start IDLE Monitoring:**
```json
{
  "folder": "string", // "INBOX", "Sent", etc.
  "auto_restart": "boolean?", // Restart on disconnect
  "timeout_minutes": "number?" // IDLE timeout (default: 29)
}
```

#### POST /imap/accounts/:id/idle/stop
**Stop IDLE Monitoring:**
```json
{
  "folder": "string?", // Stop specific folder or all
  "reason": "string?" // "manual", "error", "maintenance"
}
```

### Email Operations

#### POST /imap/accounts/:id/operations/move
**Move Emails:**
```json
{
  "emails": [
    {
      "uid": "string",
      "folder": "string" // Source folder
    }
  ],
  "destination_folder": "string",
  "expunge_source": "boolean?", // Remove from source immediately
  "preserve_flags": "boolean?", // Keep existing flags
  "operation_id": "string?" // For idempotency
}
```

**Response:**
```json
{
  "operation_id": "string",
  "status": "string", // "completed", "partial", "failed"
  "results": [
    {
      "uid": "string",
      "folder": "string",
      "success": "boolean",
      "new_uid": "string?", // UID in destination folder
      "error": "string?"
    }
  ],
  "summary": {
    "total": "number",
    "successful": "number",
    "failed": "number"
  }
}
```

#### POST /imap/accounts/:id/operations/copy
**Copy Emails:**
```json
{
  "emails": [
    {
      "uid": "string",
      "folder": "string"
    }
  ],
  "destination_folder": "string",
  "preserve_flags": "boolean?",
  "operation_id": "string?"
}
```

#### POST /imap/accounts/:id/operations/flags
**Update Flags:**
```json
{
  "emails": [
    {
      "uid": "string",
      "folder": "string"
    }
  ],
  "flags": {
    "add": "string[]?", // ["\\Seen", "\\Flagged", "CustomFlag"]
    "remove": "string[]?",
    "set": "string[]?" // Replace all flags
  },
  "operation_id": "string?"
}
```

#### POST /imap/accounts/:id/operations/append
**Append Draft/Email:**
```json
{
  "folder": "string", // Usually "Drafts" or "Sent"
  "message": {
    "headers": "object", // Email headers
    "body": "string", // RFC822 message body
    "flags": "string[]?", // Initial flags
    "internal_date": "string?" // ISO timestamp
  },
  "operation_id": "string?"
}
```

**Response:**
```json
{
  "operation_id": "string",
  "success": "boolean",
  "uid": "string?", // UID of appended message
  "uidvalidity": "string", // Folder UIDVALIDITY
  "error": "string?"
}
```

### Folder Management

#### GET /imap/accounts/:id/folders
**List Folders:**
```json
{
  "folders": [
    {
      "name": "string",
      "full_name": "string", // Full IMAP path
      "delimiter": "string",
      "attributes": "string[]", // ["\\HasNoChildren", "\\Drafts"]
      "subscribed": "boolean",
      "selectable": "boolean",
      "message_count": "number?",
      "unseen_count": "number?",
      "uidvalidity": "string?",
      "uidnext": "string?"
    }
  ],
  "hierarchy": "object", // Nested folder structure
  "special_folders": {
    "inbox": "string",
    "sent": "string?",
    "drafts": "string?",
    "trash": "string?",
    "spam": "string?"
  }
}
```

#### POST /imap/accounts/:id/folders
**Create Folder:**
```json
{
  "name": "string",
  "parent": "string?", // Parent folder path
  "subscribe": "boolean?" // Auto-subscribe
}
```

## Sync State Machine

### States

#### Account Sync States
```javascript
const AccountSyncStates = {
  IDLE: 'idle',                    // No active sync
  CONNECTING: 'connecting',        // Establishing IMAP connection
  AUTHENTICATING: 'authenticating', // Logging in
  DISCOVERING: 'discovering',      // Discovering folders
  SYNCING: 'syncing',             // Active sync in progress
  IDLE_MONITORING: 'idle_monitoring', // IDLE command active
  ERROR: 'error',                 // Sync failed
  THROTTLED: 'throttled',         // Rate limited
  MAINTENANCE: 'maintenance'       // Temporarily disabled
};
```

#### Folder Sync States
```javascript
const FolderSyncStates = {
  PENDING: 'pending',             // Queued for sync
  SELECTING: 'selecting',         // Selecting folder
  FETCHING_STATUS: 'fetching_status', // Getting folder status
  FETCHING_HEADERS: 'fetching_headers', // Downloading headers
  FETCHING_BODIES: 'fetching_bodies',   // Downloading bodies
  PROCESSING: 'processing',       // Processing emails
  COMPLETED: 'completed',         // Sync finished
  FAILED: 'failed',              // Sync failed
  SKIPPED: 'skipped'             // Skipped due to conditions
};
```

### State Transitions

#### Account Level Transitions
```javascript
class AccountSyncStateMachine {
  constructor(accountId) {
    this.accountId = accountId;
    this.state = AccountSyncStates.IDLE;
    this.transitions = {
      [AccountSyncStates.IDLE]: [
        AccountSyncStates.CONNECTING,
        AccountSyncStates.MAINTENANCE
      ],
      [AccountSyncStates.CONNECTING]: [
        AccountSyncStates.AUTHENTICATING,
        AccountSyncStates.ERROR,
        AccountSyncStates.THROTTLED
      ],
      [AccountSyncStates.AUTHENTICATING]: [
        AccountSyncStates.DISCOVERING,
        AccountSyncStates.ERROR
      ],
      [AccountSyncStates.DISCOVERING]: [
        AccountSyncStates.SYNCING,
        AccountSyncStates.IDLE_MONITORING,
        AccountSyncStates.ERROR
      ],
      [AccountSyncStates.SYNCING]: [
        AccountSyncStates.IDLE_MONITORING,
        AccountSyncStates.IDLE,
        AccountSyncStates.ERROR
      ],
      [AccountSyncStates.IDLE_MONITORING]: [
        AccountSyncStates.SYNCING,
        AccountSyncStates.IDLE,
        AccountSyncStates.ERROR,
        AccountSyncStates.CONNECTING // Reconnect on disconnect
      ],
      [AccountSyncStates.ERROR]: [
        AccountSyncStates.CONNECTING, // Retry
        AccountSyncStates.THROTTLED,
        AccountSyncStates.MAINTENANCE
      ],
      [AccountSyncStates.THROTTLED]: [
        AccountSyncStates.CONNECTING,
        AccountSyncStates.MAINTENANCE
      ],
      [AccountSyncStates.MAINTENANCE]: [
        AccountSyncStates.IDLE
      ]
    };
  }
  
  async transition(newState, context = {}) {
    if (!this.canTransition(newState)) {
      throw new Error(`Invalid transition from ${this.state} to ${newState}`);
    }
    
    const oldState = this.state;
    
    // Execute exit actions
    await this.executeExitActions(oldState, context);
    
    // Update state
    this.state = newState;
    
    // Execute entry actions
    await this.executeEntryActions(newState, context);
    
    // Log transition
    await this.logTransition(oldState, newState, context);
    
    // Notify observers
    await this.notifyStateChange(oldState, newState, context);
  }
  
  async executeEntryActions(state, context) {
    switch (state) {
      case AccountSyncStates.CONNECTING:
        await this.establishConnection(context);
        break;
      case AccountSyncStates.AUTHENTICATING:
        await this.authenticate(context);
        break;
      case AccountSyncStates.DISCOVERING:
        await this.discoverFolders(context);
        break;
      case AccountSyncStates.SYNCING:
        await this.startSync(context);
        break;
      case AccountSyncStates.IDLE_MONITORING:
        await this.startIdleMonitoring(context);
        break;
      case AccountSyncStates.ERROR:
        await this.handleError(context);
        break;
      case AccountSyncStates.THROTTLED:
        await this.scheduleRetry(context);
        break;
    }
  }
}
```

#### Folder Level Transitions
```javascript
class FolderSyncStateMachine {
  async syncFolder(folder, syncType = 'incremental') {
    const fsm = new FolderSyncStateMachine(folder);
    
    try {
      await fsm.transition(FolderSyncStates.SELECTING);
      await fsm.transition(FolderSyncStates.FETCHING_STATUS);
      
      const status = await this.getFolderStatus(folder);
      const syncPlan = await this.createSyncPlan(folder, status, syncType);
      
      if (syncPlan.needsHeaderSync) {
        await fsm.transition(FolderSyncStates.FETCHING_HEADERS);
        await this.syncHeaders(folder, syncPlan);
      }
      
      if (syncPlan.needsBodySync) {
        await fsm.transition(FolderSyncStates.FETCHING_BODIES);
        await this.syncBodies(folder, syncPlan);
      }
      
      await fsm.transition(FolderSyncStates.PROCESSING);
      await this.processEmails(folder, syncPlan);
      
      await fsm.transition(FolderSyncStates.COMPLETED);
      
    } catch (error) {
      await fsm.transition(FolderSyncStates.FAILED, { error });
      throw error;
    }
  }
}
```

## Idempotency Plan

### Email Deduplication

#### Message-ID Based Deduplication
```javascript
class EmailDeduplicator {
  async checkDuplicate(email, accountId, folderId) {
    // Primary: Message-ID header
    if (email.messageId) {
      const existing = await this.findByMessageId(
        email.messageId, 
        accountId, 
        folderId
      );
      if (existing) {
        return {
          isDuplicate: true,
          existingId: existing.id,
          method: 'message_id'
        };
      }
    }
    
    // Secondary: Content hash
    const contentHash = await this.calculateContentHash(email);
    const existingByHash = await this.findByContentHash(
      contentHash, 
      accountId, 
      folderId
    );
    if (existingByHash) {
      return {
        isDuplicate: true,
        existingId: existingByHash.id,
        method: 'content_hash'
      };
    }
    
    // Tertiary: Fuzzy matching for emails without Message-ID
    if (!email.messageId) {
      const fuzzyMatch = await this.findFuzzyMatch(email, accountId, folderId);
      if (fuzzyMatch && fuzzyMatch.confidence > 0.95) {
        return {
          isDuplicate: true,
          existingId: fuzzyMatch.id,
          method: 'fuzzy_match',
          confidence: fuzzyMatch.confidence
        };
      }
    }
    
    return { isDuplicate: false };
  }
  
  async calculateContentHash(email) {
    // Normalize content for hashing
    const normalized = {
      from: this.normalizeAddress(email.from),
      to: this.normalizeAddresses(email.to),
      subject: this.normalizeSubject(email.subject),
      date: email.date,
      bodyText: this.normalizeBody(email.bodyText)
    };
    
    const content = JSON.stringify(normalized, Object.keys(normalized).sort());
    return crypto.createHash('sha256').update(content).digest('hex');
  }
  
  async findFuzzyMatch(email, accountId, folderId) {
    // Find emails with similar characteristics
    const candidates = await this.db.query(`
      SELECT id, message_id, subject, sender, date_sent, body_text_hash
      FROM processed_emails 
      WHERE account_id = ? 
        AND folder_id = ?
        AND sender = ?
        AND ABS(TIMESTAMPDIFF(SECOND, date_sent, ?)) < 300
      ORDER BY date_sent DESC
      LIMIT 10
    `, [accountId, folderId, email.from, email.date]);
    
    for (const candidate of candidates) {
      const similarity = await this.calculateSimilarity(email, candidate);
      if (similarity > 0.95) {
        return {
          id: candidate.id,
          confidence: similarity
        };
      }
    }
    
    return null;
  }
}
```

### UID Tracking

#### UID Validity Management
```javascript
class UIDManager {
  async validateUIDValidity(folder, currentUIDValidity) {
    const stored = await this.getStoredUIDValidity(folder.id);
    
    if (!stored) {
      // First sync - store current UIDVALIDITY
      await this.storeUIDValidity(folder.id, currentUIDValidity);
      return { valid: true, action: 'initial_sync' };
    }
    
    if (stored.uidvalidity !== currentUIDValidity) {
      // UIDVALIDITY changed - need full resync
      await this.handleUIDValidityChange(folder.id, stored.uidvalidity, currentUIDValidity);
      return { 
        valid: false, 
        action: 'full_resync',
        oldUIDValidity: stored.uidvalidity,
        newUIDValidity: currentUIDValidity
      };
    }
    
    return { valid: true, action: 'incremental_sync' };
  }
  
  async handleUIDValidityChange(folderId, oldUIDValidity, newUIDValidity) {
    // Mark all existing UIDs as invalid
    await this.db.query(`
      UPDATE processed_emails 
      SET uid_valid = FALSE, 
          uid_validity_changed_at = NOW()
      WHERE folder_id = ? AND uid_validity = ?
    `, [folderId, oldUIDValidity]);
    
    // Update stored UIDVALIDITY
    await this.storeUIDValidity(folderId, newUIDValidity);
    
    // Log the change
    await this.logUIDValidityChange(folderId, oldUIDValidity, newUIDValidity);
  }
}
```

### Operation Idempotency

#### Operation Tracking
```javascript
class OperationTracker {
  async executeIdempotentOperation(operationId, operation) {
    // Check if operation already exists
    const existing = await this.getOperation(operationId);
    if (existing) {
      if (existing.status === 'completed') {
        return existing.result;
      } else if (existing.status === 'failed') {
        throw new Error(`Operation ${operationId} previously failed: ${existing.error}`);
      } else {
        throw new Error(`Operation ${operationId} is already in progress`);
      }
    }
    
    // Create operation record
    await this.createOperation(operationId, operation.type, operation.params);
    
    try {
      // Execute operation
      const result = await operation.execute();
      
      // Mark as completed
      await this.completeOperation(operationId, result);
      
      return result;
    } catch (error) {
      // Mark as failed
      await this.failOperation(operationId, error.message);
      throw error;
    }
  }
}
```

## Retry/Backoff Strategy

### Connection Retry

#### Exponential Backoff
```javascript
class ConnectionRetryManager {
  constructor() {
    this.baseDelay = 1000; // 1 second
    this.maxDelay = 300000; // 5 minutes
    this.maxRetries = 10;
    this.jitterFactor = 0.1;
  }
  
  async executeWithRetry(operation, context = {}) {
    let lastError;
    
    for (let attempt = 1; attempt <= this.maxRetries; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error;
        
        const errorType = this.classifyError(error);
        if (!errorType.retryable || attempt === this.maxRetries) {
          throw error;
        }
        
        const delay = this.calculateDelay(attempt, errorType);
        await this.logRetryAttempt(attempt, delay, error, context);
        await this.sleep(delay);
      }
    }
    
    throw lastError;
  }
  
  calculateDelay(attempt, errorType) {
    let delay;
    
    switch (errorType.strategy) {
      case 'exponential':
        delay = Math.min(
          this.baseDelay * Math.pow(2, attempt - 1),
          this.maxDelay
        );
        break;
      case 'linear':
        delay = Math.min(
          this.baseDelay * attempt,
          this.maxDelay
        );
        break;
      case 'fixed':
        delay = this.baseDelay;
        break;
      default:
        delay = this.baseDelay;
    }
    
    // Add jitter to prevent thundering herd
    const jitter = delay * this.jitterFactor * Math.random();
    return Math.floor(delay + jitter);
  }
  
  classifyError(error) {
    // Network errors - exponential backoff
    if (error.code === 'ECONNRESET' || error.code === 'ENOTFOUND') {
      return {
        retryable: true,
        strategy: 'exponential',
        category: 'network'
      };
    }
    
    // Authentication errors - not retryable
    if (error.message?.includes('authentication failed')) {
      return {
        retryable: false,
        category: 'auth'
      };
    }
    
    // Rate limiting - linear backoff
    if (error.message?.includes('rate limit')) {
      return {
        retryable: true,
        strategy: 'linear',
        category: 'rate_limit'
      };
    }
    
    // Server errors - exponential backoff
    if (error.message?.includes('server error')) {
      return {
        retryable: true,
        strategy: 'exponential',
        category: 'server'
      };
    }
    
    // Default: retryable with exponential backoff
    return {
      retryable: true,
      strategy: 'exponential',
      category: 'unknown'
    };
  }
}
```

### Sync Retry Logic

#### Folder-Level Retry
```javascript
class FolderSyncRetryManager {
  async syncFolderWithRetry(folder, syncType) {
    const retryConfig = this.getFolderRetryConfig(folder);
    
    return await this.executeWithRetry(async () => {
      return await this.syncFolder(folder, syncType);
    }, retryConfig);
  }
  
  getFolderRetryConfig(folder) {
    // Different retry strategies based on folder importance
    if (folder.name === 'INBOX') {
      return {
        maxRetries: 5,
        baseDelay: 2000,
        strategy: 'exponential'
      };
    } else if (folder.attributes.includes('\\Important')) {
      return {
        maxRetries: 3,
        baseDelay: 5000,
        strategy: 'exponential'
      };
    } else {
      return {
        maxRetries: 2,
        baseDelay: 10000,
        strategy: 'linear'
      };
    }
  }
}
```

### Circuit Breaker

#### Account-Level Circuit Breaker
```javascript
class AccountCircuitBreaker {
  constructor(accountId) {
    this.accountId = accountId;
    this.state = 'CLOSED'; // CLOSED, OPEN, HALF_OPEN
    this.failureCount = 0;
    this.failureThreshold = 5;
    this.timeout = 60000; // 1 minute
    this.lastFailureTime = null;
  }
  
  async execute(operation) {
    if (this.state === 'OPEN') {
      if (Date.now() - this.lastFailureTime > this.timeout) {
        this.state = 'HALF_OPEN';
      } else {
        throw new Error('Circuit breaker is OPEN');
      }
    }
    
    try {
      const result = await operation();
      this.onSuccess();
      return result;
    } catch (error) {
      this.onFailure();
      throw error;
    }
  }
  
  onSuccess() {
    this.failureCount = 0;
    this.state = 'CLOSED';
  }
  
  onFailure() {
    this.failureCount++;
    this.lastFailureTime = Date.now();
    
    if (this.failureCount >= this.failureThreshold) {
      this.state = 'OPEN';
    }
  }
}
```

## IDLE Implementation

### IDLE Connection Management

#### IDLE Monitor
```javascript
class IMAPIdleMonitor {
  constructor(account) {
    this.account = account;
    this.connections = new Map(); // folder -> connection
    this.idleTimeouts = new Map();
    this.reconnectAttempts = new Map();
  }
  
  async startIdleMonitoring(folder = 'INBOX') {
    try {
      const connection = await this.createIdleConnection(folder);
      
      // Set up IDLE command
      await connection.select(folder);
      await connection.idle();
      
      // Handle IDLE events
      connection.on('exists', (count) => {
        this.handleNewMessages(folder, count);
      });
      
      connection.on('expunge', (seqno) => {
        this.handleMessageExpunge(folder, seqno);
      });
      
      connection.on('flags', (seqno, flags) => {
        this.handleFlagUpdate(folder, seqno, flags);
      });
      
      connection.on('close', () => {
        this.handleConnectionClose(folder);
      });
      
      connection.on('error', (error) => {
        this.handleConnectionError(folder, error);
      });
      
      // Set up IDLE timeout (RFC recommends 29 minutes)
      this.scheduleIdleRenewal(folder, connection);
      
      this.connections.set(folder, connection);
      
    } catch (error) {
      await this.handleIdleStartError(folder, error);
    }
  }
  
  async handleNewMessages(folder, messageCount) {
    try {
      // Get current folder status
      const status = await this.getFolderStatus(folder);
      
      // Calculate new messages
      const lastKnownCount = await this.getLastMessageCount(folder);
      const newMessageCount = messageCount - lastKnownCount;
      
      if (newMessageCount > 0) {
        // Trigger incremental sync for new messages
        await this.triggerIncrementalSync(folder, {
          startUID: status.uidnext - newMessageCount,
          endUID: status.uidnext - 1
        });
      }
      
      // Update last known count
      await this.updateLastMessageCount(folder, messageCount);
      
    } catch (error) {
      console.error(`Error handling new messages in ${folder}:`, error);
    }
  }
  
  scheduleIdleRenewal(folder, connection) {
    // Renew IDLE every 29 minutes to prevent timeout
    const timeout = setTimeout(async () => {
      try {
        await connection.done(); // End current IDLE
        await connection.idle(); // Start new IDLE
        this.scheduleIdleRenewal(folder, connection);
      } catch (error) {
        await this.handleIdleRenewalError(folder, error);
      }
    }, 29 * 60 * 1000); // 29 minutes
    
    this.idleTimeouts.set(folder, timeout);
  }
  
  async handleConnectionClose(folder) {
    this.connections.delete(folder);
    
    const attempts = this.reconnectAttempts.get(folder) || 0;
    if (attempts < 5) {
      // Attempt to reconnect
      const delay = Math.min(1000 * Math.pow(2, attempts), 30000);
      setTimeout(() => {
        this.reconnectAttempts.set(folder, attempts + 1);
        this.startIdleMonitoring(folder);
      }, delay);
    } else {
      // Too many reconnect attempts - fall back to periodic sync
      await this.fallbackToPeriodicSync(folder);
    }
  }
}
```

### Periodic Sync Fallback

#### Sync Scheduler
```javascript
class PeriodicSyncScheduler {
  constructor() {
    this.schedules = new Map(); // accountId -> schedule
    this.intervals = new Map(); // accountId -> intervalId
  }
  
  schedulePeriodicSync(accountId, config = {}) {
    const schedule = {
      interval: config.interval || 300000, // 5 minutes default
      folders: config.folders || ['INBOX'],
      syncType: config.syncType || 'incremental',
      enabled: true
    };
    
    this.schedules.set(accountId, schedule);
    
    const intervalId = setInterval(async () => {
      if (schedule.enabled) {
        await this.executePeriodicSync(accountId, schedule);
      }
    }, schedule.interval);
    
    this.intervals.set(accountId, intervalId);
  }
  
  async executePeriodicSync(accountId, schedule) {
    try {
      // Check if IDLE is working
      const idleStatus = await this.getIdleStatus(accountId);
      if (idleStatus.connected && idleStatus.healthy) {
        // IDLE is working, skip periodic sync
        return;
      }
      
      // Execute sync for each folder
      for (const folder of schedule.folders) {
        await this.syncFolder(accountId, folder, schedule.syncType);
      }
      
    } catch (error) {
      console.error(`Periodic sync failed for account ${accountId}:`, error);
    }
  }
}
```

## Data Schemas

### sync_sessions
```sql
CREATE TABLE sync_sessions (
  id VARCHAR(36) PRIMARY KEY,
  account_id VARCHAR(36) NOT NULL,
  
  -- Session details
  sync_type ENUM('full', 'incremental', 'folder', 'idle_triggered') NOT NULL,
  status ENUM('started', 'running', 'completed', 'failed', 'cancelled') NOT NULL,
  
  -- Progress tracking
  folders_total INT DEFAULT 0,
  folders_completed INT DEFAULT 0,
  emails_total INT DEFAULT 0,
  emails_processed INT DEFAULT 0,
  
  -- Timing
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  duration_seconds INT NULL,
  
  -- Results
  emails_new INT DEFAULT 0,
  emails_updated INT DEFAULT 0,
  emails_deleted INT DEFAULT 0,
  
  -- Error handling
  error_message TEXT NULL,
  retry_count INT DEFAULT 0,
  
  -- Metadata
  triggered_by ENUM('manual', 'scheduled', 'idle', 'webhook') DEFAULT 'manual',
  client_info JSON NULL,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  
  INDEX idx_account_sessions (account_id, started_at),
  INDEX idx_status_tracking (status, started_at),
  INDEX idx_performance (duration_seconds, emails_processed)
);
```

### folder_sync_status
```sql
CREATE TABLE folder_sync_status (
  id VARCHAR(36) PRIMARY KEY,
  account_id VARCHAR(36) NOT NULL,
  folder_id VARCHAR(36) NOT NULL,
  
  -- IMAP status
  uidvalidity VARCHAR(50) NOT NULL,
  uidnext VARCHAR(50) NOT NULL,
  message_count INT NOT NULL,
  unseen_count INT DEFAULT 0,
  
  -- Sync tracking
  last_sync_at TIMESTAMP NULL,
  last_successful_sync_at TIMESTAMP NULL,
  highest_modseq VARCHAR(50) NULL, -- For CONDSTORE
  
  -- IDLE status
  idle_enabled BOOLEAN DEFAULT FALSE,
  idle_connected BOOLEAN DEFAULT FALSE,
  idle_last_activity TIMESTAMP NULL,
  
  -- Error tracking
  consecutive_failures INT DEFAULT 0,
  last_error TEXT NULL,
  last_error_at TIMESTAMP NULL,
  
  -- Performance metrics
  avg_sync_duration_seconds DECIMAL(8,2),
  last_sync_duration_seconds INT,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (folder_id) REFERENCES account_folders(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_account_folder (account_id, folder_id),
  INDEX idx_sync_monitoring (last_sync_at, consecutive_failures),
  INDEX idx_idle_status (idle_enabled, idle_connected)
);
```

### imap_operations
```sql
CREATE TABLE imap_operations (
  id VARCHAR(36) PRIMARY KEY,
  operation_id VARCHAR(100) NOT NULL, -- For idempotency
  account_id VARCHAR(36) NOT NULL,
  
  -- Operation details
  operation_type ENUM('move', 'copy', 'flags', 'append', 'expunge') NOT NULL,
  status ENUM('pending', 'running', 'completed', 'failed') NOT NULL,
  
  -- Target details
  source_folder VARCHAR(255),
  destination_folder VARCHAR(255),
  email_uids JSON, -- Array of UIDs
  
  -- Operation parameters
  parameters JSON, -- Operation-specific parameters
  
  -- Results
  results JSON, -- Operation results
  emails_affected INT DEFAULT 0,
  emails_successful INT DEFAULT 0,
  emails_failed INT DEFAULT 0,
  
  -- Timing
  started_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  completed_at TIMESTAMP NULL,
  duration_ms INT NULL,
  
  -- Error handling
  error_message TEXT NULL,
  retry_count INT DEFAULT 0,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_operation (operation_id),
  INDEX idx_account_operations (account_id, started_at),
  INDEX idx_status_monitoring (status, started_at),
  INDEX idx_operation_type (operation_type, status)
);
```

### email_uid_tracking
```sql
CREATE TABLE email_uid_tracking (
  id VARCHAR(36) PRIMARY KEY,
  processed_email_id VARCHAR(36) NOT NULL,
  account_id VARCHAR(36) NOT NULL,
  folder_id VARCHAR(36) NOT NULL,
  
  -- IMAP identifiers
  uid VARCHAR(50) NOT NULL,
  uidvalidity VARCHAR(50) NOT NULL,
  modseq VARCHAR(50) NULL, -- For CONDSTORE
  
  -- Validity tracking
  uid_valid BOOLEAN DEFAULT TRUE,
  uid_validity_changed_at TIMESTAMP NULL,
  
  -- Sync tracking
  first_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_seen_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE CASCADE,
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (folder_id) REFERENCES account_folders(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_email_uid (processed_email_id),
  UNIQUE KEY unique_folder_uid (folder_id, uid, uidvalidity),
  INDEX idx_uid_validity (uidvalidity, uid_valid),
  INDEX idx_sync_tracking (last_seen_at, uid_valid)
);
```

## Edge Cases

### Connection Issues
- **Network interruptions**: Automatic reconnection with exponential backoff
- **Server maintenance**: Graceful handling of temporary unavailability
- **Authentication expiry**: Token refresh and re-authentication
- **Rate limiting**: Respect server limits and implement client-side throttling

### IMAP Server Quirks
- **UIDVALIDITY changes**: Full folder resync when UIDVALIDITY changes
- **Missing IDLE support**: Fallback to periodic polling
- **Inconsistent FETCH responses**: Robust parsing and error handling
- **Large mailboxes**: Chunked processing and memory management

### Data Consistency
- **Concurrent modifications**: Optimistic locking and conflict resolution
- **Partial sync failures**: Resume from last successful point
- **Duplicate detection**: Multiple deduplication strategies
- **Flag synchronization**: Bidirectional flag sync with conflict resolution

## Test Checklist

### Connection Management
- **Authentication**: Valid/invalid credentials, token expiry
- **Network resilience**: Connection drops, timeouts, DNS failures
- **IDLE functionality**: IDLE start/stop, timeout handling, reconnection
- **Concurrent connections**: Multiple folder monitoring

### Sync Reliability
- **Full sync**: Complete mailbox synchronization
- **Incremental sync**: Only new/changed emails
- **Resume capability**: Continue from interruption point
- **Large mailboxes**: Performance with 100k+ emails

### Idempotency
- **Duplicate prevention**: Same email not processed twice
- **Operation replay**: Idempotent operations
- **UID tracking**: Proper UID validity handling
- **Concurrent operations**: Race condition prevention

### Error Handling
- **Retry logic**: Appropriate backoff strategies
- **Circuit breaker**: Prevent cascade failures
- **Graceful degradation**: Fallback mechanisms
- **Error classification**: Proper error categorization

### Performance
- **Memory usage**: Stable under load
- **Bandwidth efficiency**: Minimal data transfer
- **Sync speed**: Reasonable sync times
- **Resource cleanup**: Proper connection/memory cleanup

---

## Pragmatic Review

### ✅ Sync State Machine
- Clear state transitions for account and folder levels
- Proper error handling and recovery paths
- IDLE monitoring with fallback to periodic sync
- Comprehensive progress tracking

### ✅ Idempotency Implementation
- Multiple deduplication strategies (Message-ID, content hash, fuzzy)
- UID validity tracking and change handling
- Operation idempotency with tracking
- Robust duplicate detection

### ✅ Retry/Backoff Strategy
- Exponential backoff with jitter
- Error classification and appropriate retry strategies
- Circuit breaker pattern for account-level failures
- Folder-specific retry configurations

### ✅ IDLE Implementation
- Real-time monitoring with IDLE command
- Automatic reconnection on disconnects
- Periodic renewal to prevent timeouts
- Graceful fallback to polling

### ✅ Data Schema
- Comprehensive tracking of sync sessions and status
- UID tracking with validity management
- Operation logging for debugging and auditing
- Performance metrics collection

### Potential Issues Identified:
1. **Memory Usage**: Large mailbox processing could consume excessive memory
2. **Connection Pooling**: No explicit connection pool management
3. **Batch Processing**: Limited batch size configuration
4. **Monitoring**: Need better health check endpoints

### Recommended Fixes:
1. Implement streaming processing for large mailboxes
2. Add connection pool with configurable limits
3. Add configurable batch sizes for operations
4. Create comprehensive health check endpoints