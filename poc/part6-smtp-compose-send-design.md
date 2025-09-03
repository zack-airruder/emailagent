# Part 6: SMTP Compose & Send

## Overview
Design for SMTP email composition and sending system with RFC822 compliance, HTML/text alternatives, safety controls, bounce detection, and comprehensive logging. Ensures reliable delivery with proper error handling and audit trails.

## Goals
- **RFC822 Compliance**: Build properly formatted email messages
- **Multi-format Support**: HTML and plain text alternatives
- **Safety Controls**: Pre-send validation and kill switches
- **Delivery Reliability**: Retry logic and bounce handling
- **Audit Trail**: Comprehensive send logging and tracking
- **Copy to Sent**: Proper IMAP APPEND to Sent folder

## HTTP Interfaces

### Email Composition

#### POST /smtp/compose
**Compose Email Message:**
```json
{
  "account_id": "string",
  "reply_to_email_id": "string?", // If replying to an email
  
  "message": {
    "to": [
      {
        "email": "string",
        "name": "string?"
      }
    ],
    "cc": [
      {
        "email": "string",
        "name": "string?"
      }
    ]?,
    "bcc": [
      {
        "email": "string",
        "name": "string?"
      }
    ]?,
    "subject": "string",
    "body_text": "string", // Plain text version
    "body_html": "string?", // HTML version
    "reply_to": {
      "email": "string",
      "name": "string?"
    }?,
    "priority": "string?", // "high", "normal", "low"
    "delivery_receipt": "boolean?",
    "read_receipt": "boolean?"
  },
  
  "attachments": [
    {
      "filename": "string",
      "content_type": "string",
      "content": "string", // Base64 encoded
      "size_bytes": "number",
      "inline": "boolean?", // For embedded images
      "content_id": "string?" // For inline attachments
    }
  ]?,
  
  "options": {
    "save_to_drafts": "boolean?", // Save as draft before sending
    "schedule_send": "string?", // ISO timestamp for scheduled send
    "tracking_enabled": "boolean?", // Enable open/click tracking
    "auto_text_from_html": "boolean?", // Generate text from HTML
    "validate_recipients": "boolean?", // Validate email addresses
    "safety_check": "boolean?" // Run safety checks
  },
  
  "template": {
    "template_id": "string?",
    "variables": "object?"
  }?
}
```

**Response:**
```json
{
  "compose_id": "string",
  "message": {
    "message_id": "string", // Generated Message-ID
    "from": {
      "email": "string",
      "name": "string?"
    },
    "to": "array",
    "cc": "array?",
    "bcc": "array?",
    "subject": "string",
    "body_text": "string",
    "body_html": "string?",
    "headers": "object", // All RFC822 headers
    "size_bytes": "number"
  },
  "validation": {
    "valid": "boolean",
    "warnings": [
      {
        "type": "string", // "recipient_validation", "content_safety", "size_limit"
        "message": "string",
        "severity": "string" // "info", "warning", "error"
      }
    ],
    "safety_score": "number", // 0.0-1.0
    "requires_approval": "boolean"
  },
  "draft": {
    "saved": "boolean",
    "draft_id": "string?",
    "folder": "string?" // IMAP folder where draft is saved
  },
  "ready_to_send": "boolean"
}
```

### Email Sending

#### POST /smtp/send
**Send Composed Email:**
```json
{
  "compose_id": "string", // From compose response
  "send_options": {
    "priority": "string?", // "immediate", "normal", "batch"
    "retry_policy": {
      "max_retries": "number?",
      "retry_delay_seconds": "number?",
      "exponential_backoff": "boolean?"
    }?,
    "delivery_options": {
      "dsn_notify": "string[]?", // ["never", "success", "failure", "delay"]
      "dsn_return": "string?", // "full", "headers"
      "delivery_timeout_hours": "number?"
    }?
  }?
}
```

**Response:**
```json
{
  "send_id": "string",
  "compose_id": "string",
  "status": "string", // "queued", "sending", "sent", "failed"
  "message_id": "string",
  
  "delivery": {
    "recipients": [
      {
        "email": "string",
        "status": "string", // "queued", "sent", "failed", "deferred"
        "smtp_response": "string?",
        "delivery_time": "string?",
        "error": "string?"
      }
    ],
    "total_recipients": "number",
    "successful_deliveries": "number",
    "failed_deliveries": "number"
  },
  
  "tracking": {
    "tracking_id": "string?",
    "open_tracking_enabled": "boolean",
    "click_tracking_enabled": "boolean",
    "tracking_domain": "string?"
  },
  
  "sent_copy": {
    "saved_to_sent": "boolean",
    "sent_folder": "string?",
    "sent_uid": "string?",
    "error": "string?"
  },
  
  "timing": {
    "queued_at": "string",
    "sent_at": "string?",
    "total_send_time_ms": "number?"
  }
}
```

#### GET /smtp/send/:id/status
**Get Send Status:**
```json
{
  "send_id": "string",
  "status": "string",
  "message_id": "string",
  "current_stage": "string", // "validating", "connecting", "sending", "completed"
  
  "progress": {
    "recipients_total": "number",
    "recipients_sent": "number",
    "recipients_failed": "number",
    "current_recipient": "string?"
  },
  
  "delivery_status": [
    {
      "recipient": "string",
      "status": "string",
      "attempts": "number",
      "last_attempt": "string",
      "next_retry": "string?",
      "smtp_response": "string?",
      "error_details": "string?"
    }
  ],
  
  "bounce_info": {
    "bounced_recipients": "string[]",
    "bounce_type": "string?", // "hard", "soft", "block"
    "bounce_reason": "string?",
    "bounce_details": "object?"
  }?
}
```

### Batch Operations

#### POST /smtp/batch/send
**Send Multiple Emails:**
```json
{
  "batch_id": "string?",
  "emails": [
    {
      "compose_id": "string",
      "priority": "string?",
      "delay_seconds": "number?" // Delay before sending
    }
  ],
  "batch_options": {
    "max_concurrent": "number?", // Max concurrent sends
    "rate_limit_per_minute": "number?",
    "stop_on_failure_rate": "number?", // Stop if failure rate exceeds threshold
    "notification_webhook": "string?"
  }
}
```

### Safety Controls

#### POST /smtp/safety/validate
**Pre-send Safety Validation:**
```json
{
  "compose_id": "string",
  "validation_level": "string" // "basic", "standard", "strict"
}
```

**Response:**
```json
{
  "validation_id": "string",
  "overall_score": "number", // 0.0-1.0
  "safe_to_send": "boolean",
  "requires_approval": "boolean",
  
  "checks": {
    "recipient_validation": {
      "passed": "boolean",
      "score": "number",
      "issues": [
        {
          "recipient": "string",
          "issue": "string", // "invalid_format", "domain_not_found", "mailbox_full"
          "severity": "string"
        }
      ]
    },
    "content_safety": {
      "passed": "boolean",
      "score": "number",
      "flags": [
        {
          "type": "string", // "spam_indicators", "phishing_risk", "inappropriate_content"
          "confidence": "number",
          "description": "string"
        }
      ]
    },
    "reputation_check": {
      "passed": "boolean",
      "sender_reputation": "number",
      "domain_reputation": "number",
      "ip_reputation": "number"
    },
    "rate_limiting": {
      "passed": "boolean",
      "current_rate": "number",
      "limit": "number",
      "reset_time": "string?"
    }
  },
  
  "recommendations": [
    {
      "type": "string",
      "message": "string",
      "action": "string" // "fix_required", "review_recommended", "proceed_with_caution"
    }
  ]
}
```

#### POST /smtp/safety/kill-switch
**Emergency Stop All Sending:**
```json
{
  "reason": "string",
  "scope": "string", // "all", "account", "domain"
  "account_id": "string?",
  "domain": "string?",
  "duration_minutes": "number?" // Auto-resume after duration
}
```

## RFC822 Message Composition

### Message Builder

#### Core Message Structure
```javascript
class RFC822MessageBuilder {
  constructor(account, options = {}) {
    this.account = account;
    this.options = options;
    this.headers = new Map();
    this.bodyParts = [];
  }
  
  async buildMessage(composeRequest) {
    // Generate unique Message-ID
    const messageId = this.generateMessageId();
    
    // Build headers
    await this.buildHeaders(composeRequest, messageId);
    
    // Build body (multipart if needed)
    await this.buildBody(composeRequest);
    
    // Assemble final message
    return this.assembleMessage();
  }
  
  generateMessageId() {
    const timestamp = Date.now();
    const random = Math.random().toString(36).substring(2);
    const domain = this.account.email.split('@')[1];
    return `<${timestamp}.${random}@${domain}>`;
  }
  
  async buildHeaders(request, messageId) {
    // Required headers
    this.setHeader('Message-ID', messageId);
    this.setHeader('Date', new Date().toUTCString());
    this.setHeader('From', this.formatAddress(this.account.email, this.account.name));
    
    // Recipients
    this.setHeader('To', this.formatAddressList(request.message.to));
    if (request.message.cc?.length > 0) {
      this.setHeader('Cc', this.formatAddressList(request.message.cc));
    }
    
    // Subject
    this.setHeader('Subject', this.encodeHeader(request.message.subject));
    
    // Reply headers
    if (request.reply_to_email_id) {
      await this.buildReplyHeaders(request.reply_to_email_id);
    }
    
    // Optional headers
    if (request.message.reply_to) {
      this.setHeader('Reply-To', this.formatAddress(
        request.message.reply_to.email,
        request.message.reply_to.name
      ));
    }
    
    if (request.message.priority) {
      this.setPriorityHeaders(request.message.priority);
    }
    
    if (request.message.delivery_receipt) {
      this.setHeader('Return-Receipt-To', this.account.email);
    }
    
    if (request.message.read_receipt) {
      this.setHeader('Disposition-Notification-To', this.account.email);
    }
    
    // MIME headers
    this.setHeader('MIME-Version', '1.0');
    
    // User-Agent
    this.setHeader('User-Agent', 'EmailAgent/1.0');
    
    // Security headers
    this.setHeader('X-Mailer', 'EmailAgent');
  }
  
  async buildReplyHeaders(replyToEmailId) {
    const originalEmail = await this.getOriginalEmail(replyToEmailId);
    
    if (originalEmail.message_id) {
      this.setHeader('In-Reply-To', originalEmail.message_id);
    }
    
    // Build References header
    const references = [];
    if (originalEmail.references) {
      references.push(...originalEmail.references.split(/\s+/));
    }
    if (originalEmail.message_id) {
      references.push(originalEmail.message_id);
    }
    
    if (references.length > 0) {
      // Limit References header to prevent it from becoming too long
      const maxReferences = 10;
      const limitedReferences = references.slice(-maxReferences);
      this.setHeader('References', limitedReferences.join(' '));
    }
  }
  
  async buildBody(request) {
    const hasHtml = request.message.body_html;
    const hasText = request.message.body_text;
    const hasAttachments = request.attachments?.length > 0;
    
    if (!hasHtml && !hasAttachments) {
      // Simple text message
      this.setHeader('Content-Type', 'text/plain; charset=utf-8');
      this.setHeader('Content-Transfer-Encoding', 'quoted-printable');
      this.bodyParts.push({
        type: 'text',
        content: this.encodeQuotedPrintable(request.message.body_text)
      });
    } else {
      // Multipart message
      await this.buildMultipartBody(request);
    }
  }
  
  async buildMultipartBody(request) {
    const boundary = this.generateBoundary();
    const hasHtml = request.message.body_html;
    const hasText = request.message.body_text;
    const hasAttachments = request.attachments?.length > 0;
    
    if (hasAttachments) {
      this.setHeader('Content-Type', `multipart/mixed; boundary="${boundary}"`);
    } else {
      this.setHeader('Content-Type', `multipart/alternative; boundary="${boundary}"`);
    }
    
    this.boundary = boundary;
    
    // Add text/html parts
    if (hasText && hasHtml) {
      // Both text and HTML - use multipart/alternative
      const altBoundary = this.generateBoundary();
      
      this.bodyParts.push({
        type: 'multipart_start',
        boundary: boundary
      });
      
      this.bodyParts.push({
        type: 'multipart_alternative_start',
        boundary: altBoundary
      });
      
      // Text part
      this.bodyParts.push({
        type: 'text_part',
        contentType: 'text/plain; charset=utf-8',
        encoding: 'quoted-printable',
        content: this.encodeQuotedPrintable(request.message.body_text)
      });
      
      // HTML part
      this.bodyParts.push({
        type: 'html_part',
        contentType: 'text/html; charset=utf-8',
        encoding: 'quoted-printable',
        content: this.encodeQuotedPrintable(request.message.body_html)
      });
      
      this.bodyParts.push({
        type: 'multipart_end',
        boundary: altBoundary
      });
      
    } else if (hasText) {
      this.bodyParts.push({
        type: 'text_part',
        contentType: 'text/plain; charset=utf-8',
        encoding: 'quoted-printable',
        content: this.encodeQuotedPrintable(request.message.body_text)
      });
    } else if (hasHtml) {
      this.bodyParts.push({
        type: 'html_part',
        contentType: 'text/html; charset=utf-8',
        encoding: 'quoted-printable',
        content: this.encodeQuotedPrintable(request.message.body_html)
      });
    }
    
    // Add attachments
    if (hasAttachments) {
      for (const attachment of request.attachments) {
        await this.addAttachment(attachment);
      }
    }
    
    if (hasAttachments || (hasText && hasHtml)) {
      this.bodyParts.push({
        type: 'multipart_end',
        boundary: boundary
      });
    }
  }
  
  async addAttachment(attachment) {
    const disposition = attachment.inline ? 'inline' : 'attachment';
    let contentType = attachment.content_type;
    
    if (attachment.inline && attachment.content_id) {
      contentType += `; name="${attachment.filename}"`;
    }
    
    this.bodyParts.push({
      type: 'attachment',
      contentType: contentType,
      disposition: `${disposition}; filename="${attachment.filename}"`,
      encoding: 'base64',
      contentId: attachment.content_id,
      content: attachment.content // Already base64 encoded
    });
  }
  
  assembleMessage() {
    const lines = [];
    
    // Add headers
    for (const [name, value] of this.headers) {
      lines.push(`${name}: ${value}`);
    }
    
    lines.push(''); // Empty line between headers and body
    
    // Add body parts
    for (const part of this.bodyParts) {
      switch (part.type) {
        case 'text':
          lines.push(part.content);
          break;
        case 'multipart_start':
        case 'multipart_alternative_start':
          lines.push(`--${part.boundary}`);
          break;
        case 'text_part':
        case 'html_part':
          lines.push(`Content-Type: ${part.contentType}`);
          lines.push(`Content-Transfer-Encoding: ${part.encoding}`);
          lines.push('');
          lines.push(part.content);
          lines.push('');
          break;
        case 'attachment':
          lines.push(`Content-Type: ${part.contentType}`);
          lines.push(`Content-Disposition: ${part.disposition}`);
          lines.push(`Content-Transfer-Encoding: ${part.encoding}`);
          if (part.contentId) {
            lines.push(`Content-ID: <${part.contentId}>`);
          }
          lines.push('');
          lines.push(this.wrapBase64(part.content));
          lines.push('');
          break;
        case 'multipart_end':
          lines.push(`--${part.boundary}--`);
          break;
      }
    }
    
    return lines.join('\r\n');
  }
}
```

### Text Generation from HTML

#### HTML to Text Converter
```javascript
class HtmlToTextConverter {
  convert(html, options = {}) {
    const config = {
      wordwrap: options.wordwrap || 80,
      preserveNewlines: options.preserveNewlines !== false,
      uppercaseHeadings: options.uppercaseHeadings !== false,
      linkBrackets: options.linkBrackets !== false,
      ...options
    };
    
    // Parse HTML
    const dom = this.parseHtml(html);
    
    // Convert to text
    let text = this.processNode(dom, config);
    
    // Clean up
    text = this.cleanupText(text, config);
    
    return text;
  }
  
  processNode(node, config, context = {}) {
    if (node.type === 'text') {
      return this.processTextNode(node.data, context);
    }
    
    if (node.type === 'tag') {
      return this.processTagNode(node, config, context);
    }
    
    return '';
  }
  
  processTagNode(node, config, context) {
    const tagName = node.name.toLowerCase();
    let result = '';
    
    // Pre-tag processing
    switch (tagName) {
      case 'br':
        return '\n';
      case 'hr':
        return '\n' + '-'.repeat(config.wordwrap) + '\n';
      case 'p':
      case 'div':
        if (context.needsNewline) result += '\n';
        break;
      case 'h1':
      case 'h2':
      case 'h3':
      case 'h4':
      case 'h5':
      case 'h6':
        if (context.needsNewline) result += '\n';
        break;
    }
    
    // Process children
    const childContext = { ...context };
    if (['strong', 'b'].includes(tagName)) {
      childContext.bold = true;
    }
    if (['em', 'i'].includes(tagName)) {
      childContext.italic = true;
    }
    
    let childText = '';
    if (node.children) {
      for (const child of node.children) {
        childText += this.processNode(child, config, childContext);
      }
    }
    
    // Post-tag processing
    switch (tagName) {
      case 'a':
        const href = node.attribs?.href;
        if (href && config.linkBrackets) {
          result += `${childText} [${href}]`;
        } else {
          result += childText;
        }
        break;
      case 'h1':
      case 'h2':
      case 'h3':
      case 'h4':
      case 'h5':
      case 'h6':
        if (config.uppercaseHeadings) {
          result += childText.toUpperCase();
        } else {
          result += childText;
        }
        result += '\n';
        break;
      case 'li':
        result += `• ${childText}\n`;
        break;
      case 'p':
      case 'div':
        result += childText + '\n';
        break;
      default:
        result += childText;
    }
    
    return result;
  }
}
```

## Safety Switches

### Pre-send Validation

#### Content Safety Checker
```javascript
class ContentSafetyChecker {
  async validateContent(message, options = {}) {
    const results = {
      overall_score: 1.0,
      safe_to_send: true,
      checks: {},
      flags: []
    };
    
    // Spam detection
    const spamCheck = await this.checkSpamIndicators(message);
    results.checks.spam_detection = spamCheck;
    
    // Phishing detection
    const phishingCheck = await this.checkPhishingRisk(message);
    results.checks.phishing_detection = phishingCheck;
    
    // Content appropriateness
    const contentCheck = await this.checkContentAppropriateness(message);
    results.checks.content_appropriateness = contentCheck;
    
    // Link safety
    const linkCheck = await this.checkLinkSafety(message);
    results.checks.link_safety = linkCheck;
    
    // Attachment safety
    if (message.attachments?.length > 0) {
      const attachmentCheck = await this.checkAttachmentSafety(message.attachments);
      results.checks.attachment_safety = attachmentCheck;
    }
    
    // Calculate overall score
    results.overall_score = this.calculateOverallScore(results.checks);
    results.safe_to_send = results.overall_score >= (options.safety_threshold || 0.7);
    
    return results;
  }
  
  async checkSpamIndicators(message) {
    const indicators = [];
    let score = 1.0;
    
    // Subject line checks
    const subject = message.subject.toLowerCase();
    const spamWords = ['urgent', 'act now', 'limited time', 'free money', 'click here'];
    const spamWordCount = spamWords.filter(word => subject.includes(word)).length;
    
    if (spamWordCount > 0) {
      indicators.push({
        type: 'spam_words_in_subject',
        count: spamWordCount,
        severity: spamWordCount > 2 ? 'high' : 'medium'
      });
      score -= spamWordCount * 0.1;
    }
    
    // Excessive capitalization
    const capsRatio = (subject.match(/[A-Z]/g) || []).length / subject.length;
    if (capsRatio > 0.5) {
      indicators.push({
        type: 'excessive_capitalization',
        ratio: capsRatio,
        severity: 'medium'
      });
      score -= 0.2;
    }
    
    // Body content checks
    const bodyText = message.body_text || '';
    
    // Excessive exclamation marks
    const exclamationCount = (bodyText.match(/!/g) || []).length;
    if (exclamationCount > 5) {
      indicators.push({
        type: 'excessive_exclamation',
        count: exclamationCount,
        severity: 'low'
      });
      score -= 0.1;
    }
    
    return {
      passed: score >= 0.7,
      score: Math.max(0, score),
      indicators
    };
  }
  
  async checkPhishingRisk(message) {
    const risks = [];
    let score = 1.0;
    
    // URL analysis
    const urls = this.extractUrls(message.body_text + ' ' + (message.body_html || ''));
    
    for (const url of urls) {
      const urlRisk = await this.analyzeUrl(url);
      if (urlRisk.suspicious) {
        risks.push({
          type: 'suspicious_url',
          url: url,
          reason: urlRisk.reason,
          severity: urlRisk.severity
        });
        score -= urlRisk.severity === 'high' ? 0.5 : 0.2;
      }
    }
    
    // Domain spoofing check
    const fromDomain = message.from?.email?.split('@')[1];
    if (fromDomain) {
      const spoofingRisk = await this.checkDomainSpoofing(fromDomain);
      if (spoofingRisk.suspicious) {
        risks.push({
          type: 'domain_spoofing',
          domain: fromDomain,
          reason: spoofingRisk.reason,
          severity: 'high'
        });
        score -= 0.6;
      }
    }
    
    return {
      passed: score >= 0.7,
      score: Math.max(0, score),
      risks
    };
  }
}
```

### Rate Limiting

#### Account-Level Rate Limiter
```javascript
class SendRateLimiter {
  constructor() {
    this.limits = new Map(); // accountId -> limits
    this.counters = new Map(); // accountId -> counters
  }
  
  async checkRateLimit(accountId, recipientCount = 1) {
    const limits = await this.getAccountLimits(accountId);
    const counters = await this.getAccountCounters(accountId);
    
    const checks = {
      emails_per_minute: this.checkLimit(
        counters.emails_last_minute,
        limits.emails_per_minute,
        'minute'
      ),
      emails_per_hour: this.checkLimit(
        counters.emails_last_hour,
        limits.emails_per_hour,
        'hour'
      ),
      emails_per_day: this.checkLimit(
        counters.emails_last_day,
        limits.emails_per_day,
        'day'
      ),
      recipients_per_minute: this.checkLimit(
        counters.recipients_last_minute,
        limits.recipients_per_minute,
        'minute'
      ),
      recipients_per_hour: this.checkLimit(
        counters.recipients_last_hour,
        limits.recipients_per_hour,
        'hour'
      ),
      recipients_per_day: this.checkLimit(
        counters.recipients_last_day,
        limits.recipients_per_day,
        'day'
      )
    };
    
    const allPassed = Object.values(checks).every(check => check.allowed);
    const mostRestrictive = Object.values(checks)
      .filter(check => !check.allowed)
      .sort((a, b) => a.reset_in_seconds - b.reset_in_seconds)[0];
    
    return {
      allowed: allPassed,
      checks,
      next_available: mostRestrictive?.reset_time,
      wait_seconds: mostRestrictive?.reset_in_seconds
    };
  }
  
  checkLimit(current, limit, period) {
    const allowed = current < limit;
    const remaining = Math.max(0, limit - current);
    const resetTime = this.getNextResetTime(period);
    
    return {
      allowed,
      current,
      limit,
      remaining,
      reset_time: resetTime,
      reset_in_seconds: Math.ceil((resetTime - Date.now()) / 1000)
    };
  }
}
```

### Kill Switch Implementation

#### Emergency Stop System
```javascript
class EmergencyKillSwitch {
  constructor() {
    this.killSwitches = new Map(); // scope -> kill switch config
    this.activeBlocks = new Set();
  }
  
  async activateKillSwitch(config) {
    const killSwitchId = this.generateKillSwitchId();
    
    const killSwitch = {
      id: killSwitchId,
      reason: config.reason,
      scope: config.scope,
      account_id: config.account_id,
      domain: config.domain,
      activated_at: new Date(),
      activated_by: config.activated_by,
      auto_resume_at: config.duration_minutes ? 
        new Date(Date.now() + config.duration_minutes * 60000) : null
    };
    
    // Store kill switch
    await this.storeKillSwitch(killSwitch);
    
    // Add to active blocks
    this.activeBlocks.add(this.getScopeKey(killSwitch));
    
    // Schedule auto-resume if specified
    if (killSwitch.auto_resume_at) {
      this.scheduleAutoResume(killSwitch);
    }
    
    // Notify systems
    await this.notifyKillSwitchActivated(killSwitch);
    
    // Stop any queued sends matching the scope
    await this.stopQueuedSends(killSwitch);
    
    return killSwitchId;
  }
  
  async checkKillSwitch(accountId, recipientEmail) {
    // Check global kill switch
    if (this.activeBlocks.has('global')) {
      return {
        blocked: true,
        reason: 'Global kill switch activated',
        kill_switch_id: this.getKillSwitchByScope('global')?.id
      };
    }
    
    // Check account-specific kill switch
    if (this.activeBlocks.has(`account:${accountId}`)) {
      return {
        blocked: true,
        reason: 'Account kill switch activated',
        kill_switch_id: this.getKillSwitchByScope(`account:${accountId}`)?.id
      };
    }
    
    // Check domain-specific kill switch
    const recipientDomain = recipientEmail.split('@')[1];
    if (this.activeBlocks.has(`domain:${recipientDomain}`)) {
      return {
        blocked: true,
        reason: `Domain kill switch activated for ${recipientDomain}`,
        kill_switch_id: this.getKillSwitchByScope(`domain:${recipientDomain}`)?.id
      };
    }
    
    return { blocked: false };
  }
}
```

## Bounce Detection & Handling

### Bounce Classification

#### Bounce Analyzer
```javascript
class BounceAnalyzer {
  analyzeBounce(bounceMessage) {
    const analysis = {
      bounce_type: null, // 'hard', 'soft', 'block'
      bounce_category: null,
      bounce_reason: null,
      recipient: null,
      original_message_id: null,
      smtp_code: null,
      smtp_response: null,
      is_permanent: false,
      retry_recommended: false,
      action_required: null
    };
    
    // Extract bounce information from headers and body
    const bounceInfo = this.extractBounceInfo(bounceMessage);
    
    // Classify bounce type based on SMTP code
    if (bounceInfo.smtp_code) {
      analysis.smtp_code = bounceInfo.smtp_code;
      analysis.bounce_type = this.classifyBySmtpCode(bounceInfo.smtp_code);
    }
    
    // Analyze bounce message content
    const contentAnalysis = this.analyzeBounceContent(bounceMessage.body);
    if (contentAnalysis.bounce_type) {
      analysis.bounce_type = contentAnalysis.bounce_type;
      analysis.bounce_category = contentAnalysis.category;
      analysis.bounce_reason = contentAnalysis.reason;
    }
    
    // Extract recipient information
    analysis.recipient = this.extractFailedRecipient(bounceMessage);
    
    // Extract original message ID
    analysis.original_message_id = this.extractOriginalMessageId(bounceMessage);
    
    // Determine if bounce is permanent
    analysis.is_permanent = this.isPermanentBounce(analysis);
    
    // Determine if retry is recommended
    analysis.retry_recommended = this.shouldRetry(analysis);
    
    // Determine required action
    analysis.action_required = this.determineAction(analysis);
    
    return analysis;
  }
  
  classifyBySmtpCode(smtpCode) {
    const code = parseInt(smtpCode);
    
    // 4xx codes are temporary failures (soft bounces)
    if (code >= 400 && code < 500) {
      return 'soft';
    }
    
    // 5xx codes are permanent failures (hard bounces)
    if (code >= 500 && code < 600) {
      return 'hard';
    }
    
    return 'unknown';
  }
  
  analyzeBounceContent(body) {
    const patterns = {
      mailbox_full: {
        patterns: [
          /mailbox.*full/i,
          /quota.*exceeded/i,
          /insufficient.*storage/i
        ],
        type: 'soft',
        category: 'mailbox_full'
      },
      invalid_recipient: {
        patterns: [
          /user.*unknown/i,
          /recipient.*not.*found/i,
          /no.*such.*user/i,
          /invalid.*recipient/i
        ],
        type: 'hard',
        category: 'invalid_recipient'
      },
      domain_not_found: {
        patterns: [
          /domain.*not.*found/i,
          /host.*unknown/i,
          /name.*resolution.*failed/i
        ],
        type: 'hard',
        category: 'domain_not_found'
      },
      blocked: {
        patterns: [
          /blocked/i,
          /blacklisted/i,
          /spam/i,
          /reputation/i
        ],
        type: 'block',
        category: 'reputation_block'
      },
      rate_limited: {
        patterns: [
          /rate.*limit/i,
          /too.*many.*messages/i,
          /throttled/i
        ],
        type: 'soft',
        category: 'rate_limited'
      }
    };
    
    for (const [key, config] of Object.entries(patterns)) {
      for (const pattern of config.patterns) {
        if (pattern.test(body)) {
          return {
            bounce_type: config.type,
            category: config.category,
            reason: key
          };
        }
      }
    }
    
    return { bounce_type: 'unknown' };
  }
  
  shouldRetry(analysis) {
    // Never retry hard bounces
    if (analysis.bounce_type === 'hard') {
      return false;
    }
    
    // Retry soft bounces with specific conditions
    if (analysis.bounce_type === 'soft') {
      switch (analysis.bounce_category) {
        case 'mailbox_full':
        case 'rate_limited':
          return true;
        default:
          return false;
      }
    }
    
    // Handle blocks case by case
    if (analysis.bounce_type === 'block') {
      return false; // Generally don't retry blocks
    }
    
    return false;
  }
}
```

### Bounce Processing

#### Bounce Handler
```javascript
class BounceHandler {
  async processBounce(bounceMessage) {
    try {
      // Analyze the bounce
      const analysis = await this.bounceAnalyzer.analyzeBounce(bounceMessage);
      
      // Find the original send record
      const originalSend = await this.findOriginalSend(analysis.original_message_id);
      if (!originalSend) {
        console.warn('Could not find original send for bounce:', analysis.original_message_id);
        return;
      }
      
      // Update send status
      await this.updateSendStatus(originalSend.id, analysis);
      
      // Handle recipient-specific actions
      if (analysis.recipient) {
        await this.handleRecipientBounce(analysis.recipient, analysis);
      }
      
      // Schedule retry if recommended
      if (analysis.retry_recommended) {
        await this.scheduleRetry(originalSend, analysis);
      }
      
      // Update reputation tracking
      await this.updateReputationMetrics(originalSend.account_id, analysis);
      
      // Notify if action required
      if (analysis.action_required) {
        await this.notifyActionRequired(originalSend, analysis);
      }
      
    } catch (error) {
      console.error('Error processing bounce:', error);
    }
  }
  
  async handleRecipientBounce(recipient, analysis) {
    // Update recipient status
    await this.updateRecipientStatus(recipient, analysis);
    
    // Add to suppression list if hard bounce
    if (analysis.is_permanent) {
      await this.addToSuppressionList(recipient, {
        reason: analysis.bounce_reason,
        bounce_type: analysis.bounce_type,
        added_at: new Date()
      });
    }
    
    // Update recipient reputation
    await this.updateRecipientReputation(recipient, analysis);
  }
  
  async scheduleRetry(originalSend, analysis) {
    const retryConfig = this.getRetryConfig(analysis);
    
    const retryAt = new Date(Date.now() + retryConfig.delay_minutes * 60000);
    
    await this.createRetryJob({
      original_send_id: originalSend.id,
      retry_at: retryAt,
      retry_attempt: (originalSend.retry_count || 0) + 1,
      max_retries: retryConfig.max_retries,
      bounce_reason: analysis.bounce_reason
    });
  }
}
```

## Data Schemas

### send_logs
```sql
CREATE TABLE send_logs (
  id VARCHAR(36) PRIMARY KEY,
  account_id VARCHAR(36) NOT NULL,
  processed_email_id VARCHAR(36), -- Original email if reply
  
  -- Message identification
  message_id VARCHAR(255) NOT NULL, -- RFC822 Message-ID
  compose_id VARCHAR(36), -- Link to compose request
  
  -- Recipients
  recipients_json JSON NOT NULL, -- Array of recipient objects
  total_recipients INT NOT NULL,
  
  -- Send status
  status ENUM('queued', 'sending', 'sent', 'failed', 'cancelled') NOT NULL,
  
  -- Timing
  queued_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  started_sending_at TIMESTAMP NULL,
  completed_at TIMESTAMP NULL,
  total_send_time_ms INT NULL,
  
  -- Results
  successful_deliveries INT DEFAULT 0,
  failed_deliveries INT DEFAULT 0,
  bounced_deliveries INT DEFAULT 0,
  
  -- SMTP details
  smtp_server VARCHAR(255),
  smtp_response TEXT,
  
  -- Safety and validation
  safety_score DECIMAL(3,2),
  validation_passed BOOLEAN DEFAULT TRUE,
  validation_warnings JSON,
  
  -- Tracking
  tracking_enabled BOOLEAN DEFAULT FALSE,
  tracking_id VARCHAR(100),
  
  -- Copy to Sent
  copied_to_sent BOOLEAN DEFAULT FALSE,
  sent_folder_uid VARCHAR(50),
  
  -- Error handling
  error_message TEXT,
  retry_count INT DEFAULT 0,
  
  -- Metadata
  user_agent VARCHAR(255),
  client_ip VARCHAR(45),
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE SET NULL,
  
  INDEX idx_account_sends (account_id, queued_at),
  INDEX idx_message_tracking (message_id),
  INDEX idx_status_monitoring (status, queued_at),
  INDEX idx_performance (total_send_time_ms, total_recipients)
);
```

### recipient_delivery_status
```sql
CREATE TABLE recipient_delivery_status (
  id VARCHAR(36) PRIMARY KEY,
  send_log_id VARCHAR(36) NOT NULL,
  
  -- Recipient details
  recipient_email VARCHAR(255) NOT NULL,
  recipient_name VARCHAR(255),
  recipient_type ENUM('to', 'cc', 'bcc') NOT NULL,
  
  -- Delivery status
  status ENUM('queued', 'sent', 'failed', 'bounced', 'deferred') NOT NULL,
  
  -- SMTP response
  smtp_code VARCHAR(10),
  smtp_response TEXT,
  
  -- Timing
  attempted_at TIMESTAMP,
  delivered_at TIMESTAMP,
  
  -- Retry tracking
  retry_count INT DEFAULT 0,
  next_retry_at TIMESTAMP,
  
  -- Bounce information
  bounce_type ENUM('hard', 'soft', 'block'),
  bounce_reason VARCHAR(255),
  bounce_category VARCHAR(100),
  
  -- Error details
  error_message TEXT,
  
  FOREIGN KEY (send_log_id) REFERENCES send_logs(id) ON DELETE CASCADE,
  
  INDEX idx_send_recipients (send_log_id, status),
  INDEX idx_recipient_history (recipient_email, attempted_at),
  INDEX idx_bounce_tracking (bounce_type, bounce_category),
  INDEX idx_retry_queue (next_retry_at, status)
);
```

### bounce_logs
```sql
CREATE TABLE bounce_logs (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Original message tracking
  original_message_id VARCHAR(255),
  send_log_id VARCHAR(36),
  recipient_delivery_id VARCHAR(36),
  
  -- Bounce message details
  bounce_message_id VARCHAR(255),
  received_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  -- Bounce analysis
  bounce_type ENUM('hard', 'soft', 'block', 'unknown') NOT NULL,
  bounce_category VARCHAR(100),
  bounce_reason VARCHAR(255),
  
  -- SMTP details
  smtp_code VARCHAR(10),
  smtp_response TEXT,
  
  -- Recipient information
  failed_recipient VARCHAR(255),
  
  -- Processing status
  processed BOOLEAN DEFAULT FALSE,
  processed_at TIMESTAMP,
  
  -- Actions taken
  retry_scheduled BOOLEAN DEFAULT FALSE,
  suppression_added BOOLEAN DEFAULT FALSE,
  
  -- Raw bounce message (for debugging)
  raw_message LONGTEXT,
  
  FOREIGN KEY (send_log_id) REFERENCES send_logs(id) ON DELETE SET NULL,
  FOREIGN KEY (recipient_delivery_id) REFERENCES recipient_delivery_status(id) ON DELETE SET NULL,
  
  INDEX idx_original_message (original_message_id),
  INDEX idx_bounce_analysis (bounce_type, bounce_category),
  INDEX idx_processing_queue (processed, received_at),
  INDEX idx_recipient_bounces (failed_recipient, bounce_type)
);
```

### suppression_list
```sql
CREATE TABLE suppression_list (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Suppressed recipient
  email VARCHAR(255) NOT NULL,
  domain VARCHAR(255) NOT NULL,
  
  -- Suppression details
  reason ENUM('hard_bounce', 'spam_complaint', 'unsubscribe', 'manual') NOT NULL,
  source VARCHAR(100), -- 'bounce', 'complaint', 'user_request', 'admin'
  
  -- Reference to original event
  bounce_log_id VARCHAR(36),
  send_log_id VARCHAR(36),
  
  -- Suppression metadata
  added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  added_by VARCHAR(100),
  
  -- Status
  active BOOLEAN DEFAULT TRUE,
  removed_at TIMESTAMP,
  removed_by VARCHAR(100),
  removal_reason TEXT,
  
  FOREIGN KEY (bounce_log_id) REFERENCES bounce_logs(id) ON DELETE SET NULL,
  FOREIGN KEY (send_log_id) REFERENCES send_logs(id) ON DELETE SET NULL,
  
  UNIQUE KEY unique_active_email (email, active),
  INDEX idx_domain_suppression (domain, active),
  INDEX idx_reason_tracking (reason, added_at),
  INDEX idx_suppression_lookup (email, active)
);
```

## Edge Cases

### Message Size Limits
- **SMTP size limits**: Handle 25MB+ messages with chunking or rejection
- **Attachment handling**: Compress or reject oversized attachments
- **Recipient limits**: Batch large recipient lists

### Character Encoding
- **Unicode support**: Proper UTF-8 encoding in headers and body
- **Header encoding**: RFC2047 encoding for non-ASCII headers
- **Content-Transfer-Encoding**: Appropriate encoding selection

### SMTP Server Issues
- **Connection failures**: Retry with exponential backoff
- **Authentication errors**: Handle OAuth token refresh
- **Rate limiting**: Respect server-imposed limits
- **Temporary failures**: Distinguish from permanent failures

### Threading and References
- **Message-ID generation**: Ensure uniqueness across time
- **References header**: Maintain proper thread continuity
- **In-Reply-To**: Correct reply chain handling

## Test Checklist

### Message Composition
- **RFC822 compliance**: Valid message format
- **Multipart handling**: Proper MIME structure
- **Character encoding**: Unicode and special characters
- **Header encoding**: Non-ASCII headers properly encoded
- **Attachment handling**: Various file types and sizes

### Safety Validation
- **Content filtering**: Spam and phishing detection
- **Rate limiting**: Respect sending limits
- **Kill switch**: Emergency stop functionality
- **Recipient validation**: Email format and domain checks

### SMTP Delivery
- **Connection handling**: Various SMTP servers
- **Authentication**: Different auth methods
- **Error handling**: Temporary vs permanent failures
- **Retry logic**: Appropriate backoff strategies

### Bounce Processing
- **Bounce detection**: Various bounce formats
- **Classification**: Hard vs soft bounces
- **Suppression**: Automatic list management
- **Retry scheduling**: Appropriate retry logic

### Performance
- **Large messages**: Handle 25MB+ emails
- **Batch sending**: Multiple recipients efficiently
- **Memory usage**: Stable under load
- **Concurrent sends**: Thread-safe operations

---

## Pragmatic Review

### ✅ RFC822 Compliance
- Comprehensive message building with proper headers
- Multipart MIME support for HTML/text alternatives
- Proper character encoding and header formatting
- Thread continuity with References and In-Reply-To

### ✅ Safety Controls
- Multi-layered content validation (spam, phishing, appropriateness)
- Rate limiting at multiple levels (account, recipient, time-based)
- Emergency kill switch with granular scoping
- Pre-send validation with configurable thresholds

### ✅ Bounce Handling
- Comprehensive bounce analysis and classification
- Automatic suppression list management
- Intelligent retry logic based on bounce type
- Reputation tracking and monitoring

### ✅ Data Schema
- Detailed send logging with recipient-level tracking
- Bounce analysis and processing records
- Suppression list with audit trail
- Performance metrics and monitoring

### Potential Issues Identified:
1. **Memory Usage**: Large attachments could consume excessive memory
2. **SMTP Connection Pooling**: No explicit connection management
3. **Delivery Status Notifications**: Limited DSN handling
4. **Message Queuing**: No explicit queue management system

### Recommended Fixes:
1. Implement streaming for large attachments
2. Add SMTP connection pooling with limits
3. Enhanced DSN parsing and processing
4. Add Redis-based message queue for scalability