# Part 9: Security, Privacy, Abuse

## Overview
Comprehensive security design covering threat modeling, privacy protection, abuse prevention, and incident response. Addresses TLS configuration, secrets management, log redaction, CSRF/SSRF protection, SMTP abuse prevention, rule misconfiguration safety, opt-out mechanisms, rate limiting, and emergency kill-switch procedures.

## Threat Model

### Assets and Data Classification

#### Critical Assets
- **Email Content**: Customer emails, drafts, and responses
- **Authentication Credentials**: IMAP/SMTP passwords, API keys, tokens
- **Customer Data**: Email addresses, names, conversation history
- **Business Logic**: Rules, prompts, AI model configurations
- **System Access**: Admin credentials, service accounts

#### Data Classification
```javascript
const DATA_CLASSIFICATION = {
  PUBLIC: {
    level: 0,
    examples: ['API documentation', 'public templates'],
    protection: 'Standard web security'
  },
  
  INTERNAL: {
    level: 1,
    examples: ['System metrics', 'non-PII logs'],
    protection: 'Authentication required'
  },
  
  CONFIDENTIAL: {
    level: 2,
    examples: ['Email metadata', 'rule configurations'],
    protection: 'Encryption at rest, access logging'
  },
  
  RESTRICTED: {
    level: 3,
    examples: ['Email content', 'customer PII'],
    protection: 'End-to-end encryption, strict access controls'
  },
  
  TOP_SECRET: {
    level: 4,
    examples: ['IMAP/SMTP credentials', 'encryption keys'],
    protection: 'Hardware security modules, zero-trust access'
  }
};
```

### Threat Actors and Motivations

#### External Threats
1. **Cybercriminals**
   - Motivation: Financial gain, data theft
   - Capabilities: Automated attacks, social engineering
   - Targets: Customer data, credentials, system access

2. **Nation-State Actors**
   - Motivation: Espionage, surveillance
   - Capabilities: Advanced persistent threats, zero-days
   - Targets: High-value customer communications

3. **Competitors**
   - Motivation: Business intelligence, disruption
   - Capabilities: Insider recruitment, targeted attacks
   - Targets: Business logic, customer lists

4. **Hacktivists**
   - Motivation: Ideological, publicity
   - Capabilities: DDoS, defacement, data leaks
   - Targets: Public-facing services, customer data

#### Internal Threats
1. **Malicious Insiders**
   - Motivation: Financial gain, revenge, ideology
   - Capabilities: Privileged access, system knowledge
   - Targets: Customer data, system credentials

2. **Negligent Users**
   - Motivation: Convenience, lack of awareness
   - Capabilities: Legitimate access, poor practices
   - Targets: Accidental exposure, weak configurations

### Attack Vectors and Scenarios

#### Network-Based Attacks
```javascript
const NETWORK_THREATS = {
  'Man-in-the-Middle': {
    description: 'Interception of email credentials or content',
    likelihood: 'Medium',
    impact: 'High',
    attack_path: 'Unsecured IMAP/SMTP connections',
    mitigations: ['TLS 1.3 enforcement', 'Certificate pinning', 'HSTS']
  },
  
  'DNS Poisoning': {
    description: 'Redirect email traffic to malicious servers',
    likelihood: 'Low',
    impact: 'High',
    attack_path: 'Compromised DNS resolution',
    mitigations: ['DNS over HTTPS', 'DNS validation', 'Multiple DNS providers']
  },
  
  'DDoS Attacks': {
    description: 'Service disruption and availability impact',
    likelihood: 'High',
    impact: 'Medium',
    attack_path: 'Public API endpoints, email processing',
    mitigations: ['Rate limiting', 'CDN protection', 'Auto-scaling']
  }
};
```

#### Application-Level Attacks
```javascript
const APPLICATION_THREATS = {
  'Injection Attacks': {
    sql_injection: {
      description: 'Malicious SQL in user inputs',
      targets: ['Search queries', 'Filter parameters'],
      mitigations: ['Parameterized queries', 'Input validation', 'ORM usage']
    },
    
    template_injection: {
      description: 'Malicious code in email templates',
      targets: ['Prompt templates', 'Email drafts'],
      mitigations: ['Template sandboxing', 'Input sanitization', 'Safe rendering']
    },
    
    llm_injection: {
      description: 'Prompt injection to manipulate AI responses',
      targets: ['Email classification', 'Response generation'],
      mitigations: ['Input filtering', 'Output validation', 'Prompt isolation']
    }
  },
  
  'Authentication Bypass': {
    description: 'Unauthorized access to user accounts',
    attack_vectors: ['Weak passwords', 'Session hijacking', 'Token theft'],
    mitigations: ['MFA enforcement', 'Strong session management', 'JWT security']
  },
  
  'Authorization Flaws': {
    description: 'Access to unauthorized resources',
    attack_vectors: ['IDOR', 'Privilege escalation', 'Missing access controls'],
    mitigations: ['RBAC implementation', 'Resource-level permissions', 'Access logging']
  }
};
```

#### Data Exfiltration Scenarios
```javascript
const EXFILTRATION_SCENARIOS = {
  'Email Content Theft': {
    description: 'Unauthorized access to customer email content',
    attack_path: 'Compromised admin account → Database access → Bulk export',
    data_at_risk: 'All customer emails and responses',
    detection: ['Unusual database queries', 'Large data exports', 'Off-hours access'],
    prevention: ['Database encryption', 'Query monitoring', 'Export controls']
  },
  
  'Credential Harvesting': {
    description: 'Theft of IMAP/SMTP credentials',
    attack_path: 'Application vulnerability → Secrets access → Credential theft',
    data_at_risk: 'Customer email account access',
    detection: ['Failed authentication spikes', 'Unusual login patterns'],
    prevention: ['Secrets encryption', 'Credential rotation', 'Access monitoring']
  },
  
  'Rule Configuration Theft': {
    description: 'Theft of business logic and automation rules',
    attack_path: 'Insider threat → Rule export → Competitor advantage',
    data_at_risk: 'Business intelligence and competitive advantage',
    detection: ['Rule export logs', 'User behavior analytics'],
    prevention: ['Export restrictions', 'Watermarking', 'Access controls']
  }
};
```

## Security Controls and Mitigations

### TLS Configuration

#### TLS Policy
```javascript
const TLS_CONFIGURATION = {
  minimum_version: 'TLSv1.3',
  
  cipher_suites: [
    'TLS_AES_256_GCM_SHA384',
    'TLS_CHACHA20_POLY1305_SHA256',
    'TLS_AES_128_GCM_SHA256'
  ],
  
  certificate_requirements: {
    key_size: 2048, // Minimum RSA key size
    signature_algorithm: 'SHA-256',
    validity_period: '1 year maximum',
    san_required: true,
    certificate_transparency: true
  },
  
  hsts_policy: {
    max_age: 31536000, // 1 year
    include_subdomains: true,
    preload: true
  },
  
  certificate_pinning: {
    enabled: true,
    backup_pins: 2,
    pin_rotation_days: 90
  }
};
```

#### IMAP/SMTP TLS Enforcement
```javascript
class SecureEmailConnection {
  constructor(config) {
    this.tlsConfig = {
      rejectUnauthorized: true,
      minVersion: 'TLSv1.3',
      ciphers: TLS_CONFIGURATION.cipher_suites.join(':'),
      checkServerIdentity: this.validateServerCertificate.bind(this)
    };
  }
  
  async connectIMAP(account) {
    const connection = new ImapFlow({
      host: account.imap_host,
      port: account.imap_port,
      secure: true, // Force TLS
      auth: {
        user: account.email,
        pass: await this.decryptPassword(account.encrypted_password)
      },
      tls: this.tlsConfig,
      
      // Additional security options
      disableCompression: true, // Prevent CRIME attacks
      greetingTimeout: 30000,
      socketTimeout: 60000,
      
      // Connection validation
      verifyOnly: false,
      normalizeHeaders: false
    });
    
    // Verify connection security after establishment
    await this.verifyConnectionSecurity(connection);
    
    return connection;
  }
  
  async connectSMTP(account) {
    const transporter = nodemailer.createTransporter({
      host: account.smtp_host,
      port: account.smtp_port,
      secure: true, // Force TLS
      requireTLS: true,
      
      auth: {
        user: account.email,
        pass: await this.decryptPassword(account.encrypted_password)
      },
      
      tls: {
        ...this.tlsConfig,
        servername: account.smtp_host
      },
      
      // Security options
      disableFileAccess: true,
      disableUrlAccess: true
    });
    
    // Verify SMTP security
    await this.verifySMTPSecurity(transporter);
    
    return transporter;
  }
  
  validateServerCertificate(servername, cert) {
    // Implement certificate pinning
    const expectedFingerprints = this.getCertificatePins(servername);
    const certFingerprint = this.calculateFingerprint(cert);
    
    if (!expectedFingerprints.includes(certFingerprint)) {
      throw new Error(`Certificate pinning failed for ${servername}`);
    }
    
    return undefined; // Certificate is valid
  }
}
```

### Secrets Management

#### Encryption at Rest
```javascript
class SecretsManager {
  constructor() {
    this.encryptionKey = this.loadMasterKey();
    this.keyRotationInterval = 90 * 24 * 60 * 60 * 1000; // 90 days
  }
  
  async encryptSecret(plaintext, context = {}) {
    const keyId = await this.getCurrentKeyId();
    const nonce = crypto.randomBytes(12);
    const additionalData = Buffer.from(JSON.stringify(context));
    
    const cipher = crypto.createCipherGCM('aes-256-gcm');
    cipher.setAAD(additionalData);
    
    const key = await this.deriveKey(keyId, context);
    cipher.init(key, nonce);
    
    const encrypted = Buffer.concat([
      cipher.update(plaintext, 'utf8'),
      cipher.final()
    ]);
    
    const authTag = cipher.getAuthTag();
    
    return {
      keyId,
      nonce: nonce.toString('base64'),
      encrypted: encrypted.toString('base64'),
      authTag: authTag.toString('base64'),
      context: context
    };
  }
  
  async decryptSecret(encryptedData) {
    const { keyId, nonce, encrypted, authTag, context } = encryptedData;
    
    const key = await this.deriveKey(keyId, context);
    const additionalData = Buffer.from(JSON.stringify(context));
    
    const decipher = crypto.createDecipherGCM('aes-256-gcm');
    decipher.setAAD(additionalData);
    decipher.setAuthTag(Buffer.from(authTag, 'base64'));
    
    const decrypted = Buffer.concat([
      decipher.update(Buffer.from(encrypted, 'base64')),
      decipher.final()
    ]);
    
    return decrypted.toString('utf8');
  }
  
  async rotateKeys() {
    const newKeyId = this.generateKeyId();
    const newKey = crypto.randomBytes(32);
    
    // Store new key
    await this.storeKey(newKeyId, newKey);
    
    // Re-encrypt all secrets with new key
    await this.reencryptAllSecrets(newKeyId);
    
    // Mark old keys for deletion (after grace period)
    await this.scheduleKeyDeletion();
    
    return newKeyId;
  }
}
```

#### Secrets Storage Schema
```sql
CREATE TABLE encrypted_secrets (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Secret identification
  secret_type VARCHAR(50) NOT NULL, -- 'imap_password', 'smtp_password', 'api_key'
  owner_id VARCHAR(36) NOT NULL, -- Account or user ID
  
  -- Encryption details
  key_id VARCHAR(36) NOT NULL,
  nonce VARCHAR(100) NOT NULL,
  encrypted_value TEXT NOT NULL,
  auth_tag VARCHAR(100) NOT NULL,
  encryption_context JSON,
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  last_accessed TIMESTAMP,
  access_count INT DEFAULT 0,
  
  -- Rotation and expiry
  expires_at TIMESTAMP,
  rotation_due_at TIMESTAMP,
  
  INDEX idx_owner_type (owner_id, secret_type),
  INDEX idx_key_rotation (key_id, rotation_due_at),
  INDEX idx_expiry_cleanup (expires_at)
);

CREATE TABLE encryption_keys (
  key_id VARCHAR(36) PRIMARY KEY,
  
  -- Key material (encrypted with master key)
  encrypted_key TEXT NOT NULL,
  key_algorithm VARCHAR(50) NOT NULL,
  key_size INT NOT NULL,
  
  -- Lifecycle
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  activated_at TIMESTAMP,
  deactivated_at TIMESTAMP,
  delete_after TIMESTAMP,
  
  -- Usage tracking
  usage_count INT DEFAULT 0,
  last_used TIMESTAMP,
  
  INDEX idx_lifecycle (activated_at, deactivated_at),
  INDEX idx_cleanup (delete_after)
);
```

### Log Redaction and Privacy

#### PII Detection and Redaction
```javascript
class PIIRedactor {
  constructor() {
    this.patterns = {
      email: /\b[A-Za-z0-9._%+-]+@[A-Za-z0-9.-]+\.[A-Z|a-z]{2,}\b/g,
      phone: /\b(?:\+?1[-.]?)?\(?([0-9]{3})\)?[-.]?([0-9]{3})[-.]?([0-9]{4})\b/g,
      ssn: /\b(?!000|666)[0-8][0-9]{2}-(?!00)[0-9]{2}-(?!0000)[0-9]{4}\b/g,
      credit_card: /\b(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|3[47][0-9]{13}|3[0-9]{13}|6(?:011|5[0-9]{2})[0-9]{12})\b/g,
      ip_address: /\b(?:[0-9]{1,3}\.){3}[0-9]{1,3}\b/g,
      
      // Custom patterns for email-specific PII
      email_header: /^(From|To|Cc|Bcc|Reply-To):\s*(.+)$/gm,
      message_id: /Message-ID:\s*<([^>]+)>/gi,
      received_header: /^Received:\s*(.+)$/gm
    };
    
    this.redactionMethods = {
      mask: (match) => '*'.repeat(match.length),
      hash: (match) => crypto.createHash('sha256').update(match).digest('hex').substring(0, 8),
      tokenize: (match) => `[${this.getTokenType(match)}_${this.generateToken()}]`,
      remove: () => '[REDACTED]'
    };
  }
  
  redactContent(content, redactionLevel = 'standard') {
    const config = this.getRedactionConfig(redactionLevel);
    let redactedContent = content;
    
    for (const [patternName, pattern] of Object.entries(this.patterns)) {
      if (config.patterns.includes(patternName)) {
        const method = config.methods[patternName] || config.defaultMethod;
        redactedContent = redactedContent.replace(pattern, this.redactionMethods[method]);
      }
    }
    
    return {
      redacted_content: redactedContent,
      redaction_applied: true,
      redaction_level: redactionLevel,
      patterns_detected: this.detectPatterns(content)
    };
  }
  
  getRedactionConfig(level) {
    const configs = {
      minimal: {
        patterns: ['ssn', 'credit_card'],
        methods: { ssn: 'remove', credit_card: 'remove' },
        defaultMethod: 'mask'
      },
      
      standard: {
        patterns: ['email', 'phone', 'ssn', 'credit_card', 'ip_address'],
        methods: {
          email: 'tokenize',
          phone: 'mask',
          ssn: 'remove',
          credit_card: 'remove',
          ip_address: 'hash'
        },
        defaultMethod: 'mask'
      },
      
      strict: {
        patterns: Object.keys(this.patterns),
        methods: {
          email: 'remove',
          phone: 'remove',
          ssn: 'remove',
          credit_card: 'remove',
          email_header: 'tokenize',
          message_id: 'hash'
        },
        defaultMethod: 'remove'
      }
    };
    
    return configs[level] || configs.standard;
  }
}
```

#### Structured Logging with Redaction
```javascript
class SecureLogger {
  constructor() {
    this.redactor = new PIIRedactor();
    this.logLevels = ['error', 'warn', 'info', 'debug'];
  }
  
  log(level, message, context = {}) {
    const logEntry = {
      timestamp: new Date().toISOString(),
      level: level,
      message: this.redactor.redactContent(message, 'standard').redacted_content,
      context: this.redactContext(context),
      trace_id: context.trace_id || this.generateTraceId(),
      service: 'email-agent',
      version: process.env.APP_VERSION
    };
    
    // Add security-specific fields
    if (context.security_event) {
      logEntry.security = {
        event_type: context.security_event,
        severity: context.severity || 'medium',
        user_id: context.user_id,
        ip_address: this.hashIP(context.ip_address),
        user_agent_hash: context.user_agent ? crypto.createHash('sha256').update(context.user_agent).digest('hex') : null
      };
    }
    
    this.writeLog(logEntry);
  }
  
  redactContext(context) {
    const redactedContext = { ...context };
    
    // Remove sensitive fields
    delete redactedContext.password;
    delete redactedContext.token;
    delete redactedContext.api_key;
    delete redactedContext.email_content;
    
    // Redact PII in remaining fields
    for (const [key, value] of Object.entries(redactedContext)) {
      if (typeof value === 'string') {
        redactedContext[key] = this.redactor.redactContent(value, 'minimal').redacted_content;
      }
    }
    
    return redactedContext;
  }
}
```

### CSRF Protection

#### CSRF Token Implementation
```javascript
class CSRFProtection {
  constructor() {
    this.tokenExpiry = 60 * 60 * 1000; // 1 hour
    this.secretKey = process.env.CSRF_SECRET_KEY;
  }
  
  generateToken(sessionId, userId) {
    const timestamp = Date.now();
    const nonce = crypto.randomBytes(16).toString('hex');
    
    const payload = {
      sessionId,
      userId,
      timestamp,
      nonce
    };
    
    const signature = this.signPayload(payload);
    
    return {
      token: Buffer.from(JSON.stringify(payload)).toString('base64'),
      signature: signature
    };
  }
  
  validateToken(token, signature, sessionId, userId) {
    try {
      const payload = JSON.parse(Buffer.from(token, 'base64').toString());
      
      // Verify signature
      const expectedSignature = this.signPayload(payload);
      if (!crypto.timingSafeEqual(Buffer.from(signature), Buffer.from(expectedSignature))) {
        throw new Error('Invalid CSRF token signature');
      }
      
      // Verify session and user
      if (payload.sessionId !== sessionId || payload.userId !== userId) {
        throw new Error('CSRF token session mismatch');
      }
      
      // Verify expiry
      if (Date.now() - payload.timestamp > this.tokenExpiry) {
        throw new Error('CSRF token expired');
      }
      
      return true;
      
    } catch (error) {
      throw new Error(`CSRF validation failed: ${error.message}`);
    }
  }
  
  middleware() {
    return (req, res, next) => {
      // Skip CSRF for safe methods
      if (['GET', 'HEAD', 'OPTIONS'].includes(req.method)) {
        return next();
      }
      
      // Skip CSRF for API endpoints with proper authentication
      if (req.path.startsWith('/api/') && req.headers.authorization) {
        return next();
      }
      
      const token = req.headers['x-csrf-token'] || req.body._csrf;
      const signature = req.headers['x-csrf-signature'];
      
      if (!token || !signature) {
        return res.status(403).json({ error: 'CSRF token required' });
      }
      
      try {
        this.validateToken(token, signature, req.sessionID, req.user?.id);
        next();
      } catch (error) {
        res.status(403).json({ error: error.message });
      }
    };
  }
}
```

### SSRF Protection

#### URL Validation and Filtering
```javascript
class SSRFProtection {
  constructor() {
    this.allowedSchemes = ['http', 'https'];
    this.blockedNetworks = [
      '127.0.0.0/8',    // Loopback
      '10.0.0.0/8',     // Private Class A
      '172.16.0.0/12',  // Private Class B
      '192.168.0.0/16', // Private Class C
      '169.254.0.0/16', // Link-local
      '224.0.0.0/4',    // Multicast
      '::1/128',        // IPv6 loopback
      'fc00::/7',       // IPv6 private
      'fe80::/10'       // IPv6 link-local
    ];
    
    this.allowedDomains = [
      'api.openai.com',
      'generativelanguage.googleapis.com',
      // Add other trusted external services
    ];
  }
  
  async validateURL(url, context = {}) {
    try {
      const parsedURL = new URL(url);
      
      // Check scheme
      if (!this.allowedSchemes.includes(parsedURL.protocol.slice(0, -1))) {
        throw new Error(`Blocked scheme: ${parsedURL.protocol}`);
      }
      
      // Resolve hostname to IP
      const ips = await this.resolveHostname(parsedURL.hostname);
      
      // Check for blocked networks
      for (const ip of ips) {
        if (this.isBlockedIP(ip)) {
          throw new Error(`Blocked IP address: ${ip}`);
        }
      }
      
      // Check domain allowlist for external requests
      if (context.external_request && !this.isAllowedDomain(parsedURL.hostname)) {
        throw new Error(`Domain not in allowlist: ${parsedURL.hostname}`);
      }
      
      return {
        valid: true,
        resolved_ips: ips,
        canonical_url: parsedURL.toString()
      };
      
    } catch (error) {
      throw new Error(`SSRF validation failed: ${error.message}`);
    }
  }
  
  isBlockedIP(ip) {
    for (const network of this.blockedNetworks) {
      if (this.ipInNetwork(ip, network)) {
        return true;
      }
    }
    return false;
  }
  
  async makeSecureRequest(url, options = {}) {
    // Validate URL first
    await this.validateURL(url, { external_request: true });
    
    const secureOptions = {
      ...options,
      timeout: 30000, // 30 second timeout
      maxRedirects: 3,
      
      // Prevent following redirects to blocked URLs
      beforeRedirect: (options, responseDetails) => {
        this.validateURL(responseDetails.headers.location, { external_request: true });
      },
      
      // Size limits
      maxContentLength: 10 * 1024 * 1024, // 10MB
      
      // Security headers
      headers: {
        'User-Agent': 'EmailAgent/1.0',
        ...options.headers
      }
    };
    
    return await axios(url, secureOptions);
  }
}
```

### SMTP Abuse Prevention

#### Rate Limiting and Throttling
```javascript
class SMTPAbuseProtection {
  constructor() {
    this.rateLimits = {
      per_account: {
        emails_per_hour: 100,
        emails_per_day: 1000,
        recipients_per_hour: 500
      },
      
      per_recipient: {
        emails_per_hour: 5,
        emails_per_day: 20
      },
      
      global: {
        emails_per_minute: 1000,
        new_accounts_per_hour: 10
      }
    };
    
    this.suspiciousPatterns = {
      bulk_sending: {
        threshold: 50, // emails to different recipients in short time
        window_minutes: 10
      },
      
      identical_content: {
        threshold: 10, // identical emails to different recipients
        similarity_threshold: 0.95
      },
      
      rapid_fire: {
        threshold: 20, // emails sent in rapid succession
        window_seconds: 60
      }
    };
  }
  
  async checkSendingLimits(accountId, recipientEmail, emailContent) {
    const checks = await Promise.all([
      this.checkAccountLimits(accountId),
      this.checkRecipientLimits(accountId, recipientEmail),
      this.checkGlobalLimits(),
      this.checkSuspiciousPatterns(accountId, recipientEmail, emailContent)
    ]);
    
    const violations = checks.filter(check => !check.allowed);
    
    if (violations.length > 0) {
      return {
        allowed: false,
        violations: violations,
        retry_after: Math.max(...violations.map(v => v.retry_after || 0))
      };
    }
    
    return { allowed: true };
  }
  
  async checkSuspiciousPatterns(accountId, recipientEmail, emailContent) {
    const recentEmails = await this.getRecentEmails(accountId, 60 * 60 * 1000); // 1 hour
    
    // Check for bulk sending pattern
    const uniqueRecipients = new Set(recentEmails.map(e => e.recipient_email));
    if (uniqueRecipients.size >= this.suspiciousPatterns.bulk_sending.threshold) {
      await this.flagSuspiciousActivity(accountId, 'bulk_sending', {
        unique_recipients: uniqueRecipients.size,
        time_window: '1 hour'
      });
      
      return {
        allowed: false,
        reason: 'Bulk sending pattern detected',
        retry_after: 3600 // 1 hour
      };
    }
    
    // Check for identical content
    const contentHash = crypto.createHash('sha256').update(emailContent).digest('hex');
    const identicalEmails = recentEmails.filter(e => e.content_hash === contentHash);
    
    if (identicalEmails.length >= this.suspiciousPatterns.identical_content.threshold) {
      await this.flagSuspiciousActivity(accountId, 'identical_content', {
        identical_count: identicalEmails.length,
        content_hash: contentHash
      });
      
      return {
        allowed: false,
        reason: 'Identical content spam detected',
        retry_after: 7200 // 2 hours
      };
    }
    
    return { allowed: true };
  }
  
  async implementBackpressure(accountId, violationType) {
    const backpressureStrategies = {
      rate_limit: {
        delay_seconds: 60,
        exponential_backoff: true
      },
      
      suspicious_activity: {
        delay_seconds: 300,
        require_human_review: true
      },
      
      abuse_detected: {
        delay_seconds: 3600,
        escalate_to_admin: true,
        temporary_suspension: true
      }
    };
    
    const strategy = backpressureStrategies[violationType];
    
    if (strategy.require_human_review) {
      await this.createEscalation(accountId, 'abuse_review', {
        violation_type: violationType,
        requires_immediate_attention: strategy.escalate_to_admin
      });
    }
    
    if (strategy.temporary_suspension) {
      await this.suspendAccount(accountId, strategy.delay_seconds);
    }
    
    return strategy;
  }
}
```

### Rule Misconfiguration Safety

#### Rule Validation and Safety Checks
```javascript
class RuleSafetyValidator {
  constructor() {
    this.safetyChecks = {
      infinite_loops: true,
      excessive_automation: true,
      dangerous_conditions: true,
      privilege_escalation: true,
      data_exfiltration: true
    };
    
    this.dangerousPatterns = [
      /auto.*send.*all/i,
      /forward.*external/i,
      /delete.*permanent/i,
      /admin.*access/i
    ];
  }
  
  async validateRule(rule, context = {}) {
    const validationResults = {
      safe: true,
      warnings: [],
      errors: [],
      risk_score: 0
    };
    
    // Check for infinite loop potential
    const loopRisk = await this.checkInfiniteLoopRisk(rule);
    if (loopRisk.risk > 0.7) {
      validationResults.errors.push({
        type: 'infinite_loop_risk',
        message: 'Rule may create infinite loop',
        details: loopRisk.details
      });
      validationResults.safe = false;
    }
    
    // Check for excessive automation
    const automationRisk = this.checkAutomationRisk(rule);
    if (automationRisk.risk > 0.8) {
      validationResults.warnings.push({
        type: 'excessive_automation',
        message: 'Rule may trigger too frequently',
        details: automationRisk.details
      });
    }
    
    // Check for dangerous conditions
    const dangerousConditions = this.checkDangerousConditions(rule);
    if (dangerousConditions.length > 0) {
      validationResults.errors.push({
        type: 'dangerous_conditions',
        message: 'Rule contains potentially dangerous conditions',
        details: dangerousConditions
      });
      validationResults.safe = false;
    }
    
    // Calculate overall risk score
    validationResults.risk_score = this.calculateRiskScore(rule, validationResults);
    
    return validationResults;
  }
  
  checkInfiniteLoopRisk(rule) {
    let riskScore = 0;
    const details = [];
    
    // Check if rule can trigger itself
    if (rule.actions.some(action => action.type === 'send_email')) {
      const sendActions = rule.actions.filter(a => a.type === 'send_email');
      
      for (const action of sendActions) {
        // Check if sent email could match this rule's conditions
        const couldMatch = this.couldEmailMatchRule(action.template, rule.conditions);
        if (couldMatch) {
          riskScore += 0.8;
          details.push(`Send action could trigger rule again: ${action.template}`);
        }
      }
    }
    
    // Check for chain reactions with other rules
    const chainRisk = this.checkRuleChainRisk(rule);
    riskScore += chainRisk.risk;
    details.push(...chainRisk.details);
    
    return {
      risk: Math.min(riskScore, 1.0),
      details: details
    };
  }
  
  implementSafetyLimits(rule) {
    const safetyLimits = {
      max_executions_per_hour: 100,
      max_executions_per_day: 1000,
      cooldown_seconds: 60,
      
      // Circuit breaker
      error_threshold: 5,
      error_window_minutes: 15,
      
      // Human oversight
      require_approval_after: 50, // executions
      escalate_after_errors: 3
    };
    
    return {
      ...rule,
      safety_limits: safetyLimits,
      safety_enabled: true
    };
  }
}
```

### Opt-out and Consent Management

#### Opt-out Implementation
```javascript
class OptOutManager {
  constructor() {
    this.optOutMethods = {
      email_header: 'List-Unsubscribe',
      email_footer: 'unsubscribe_link',
      web_form: 'opt_out_form',
      api_endpoint: 'POST /opt-out'
    };
  }
  
  async processOptOut(request) {
    const validation = await this.validateOptOutRequest(request);
    if (!validation.valid) {
      throw new Error(`Invalid opt-out request: ${validation.reason}`);
    }
    
    // Record opt-out
    const optOutRecord = {
      id: this.generateOptOutId(),
      email_address: request.email,
      account_id: request.account_id,
      method: request.method,
      timestamp: new Date(),
      ip_address: request.ip_address,
      user_agent: request.user_agent,
      reason: request.reason,
      
      // Verification
      verified: request.method === 'verified_email',
      verification_token: request.verification_token
    };
    
    await this.storeOptOut(optOutRecord);
    
    // Immediately stop all automation for this email
    await this.disableAutomationForEmail(request.email, request.account_id);
    
    // Send confirmation
    await this.sendOptOutConfirmation(request.email, optOutRecord.id);
    
    return {
      opt_out_id: optOutRecord.id,
      status: 'processed',
      effective_immediately: true
    };
  }
  
  async checkOptOutStatus(email, accountId) {
    const optOut = await this.db('opt_outs')
      .where('email_address', email)
      .where('account_id', accountId)
      .where('active', true)
      .first();
    
    return {
      opted_out: !!optOut,
      opt_out_date: optOut?.timestamp,
      method: optOut?.method,
      can_send: !optOut
    };
  }
  
  generateOptOutLink(email, accountId) {
    const token = this.generateSecureToken({
      email: email,
      account_id: accountId,
      expires_at: Date.now() + (7 * 24 * 60 * 60 * 1000) // 7 days
    });
    
    return `${process.env.BASE_URL}/opt-out?token=${token}`;
  }
}
```

### Rate Limiting

#### Multi-tier Rate Limiting
```javascript
class RateLimiter {
  constructor() {
    this.limits = {
      // API endpoints
      api: {
        '/api/emails/send': { requests: 100, window: 3600 }, // 100/hour
        '/api/accounts': { requests: 1000, window: 3600 },
        '/api/rules': { requests: 500, window: 3600 }
      },
      
      // User actions
      user: {
        login_attempts: { requests: 5, window: 900 }, // 5 per 15 minutes
        password_reset: { requests: 3, window: 3600 },
        account_creation: { requests: 2, window: 86400 } // 2 per day
      },
      
      // System operations
      system: {
        email_processing: { requests: 10000, window: 60 }, // 10k per minute
        llm_requests: { requests: 1000, window: 60 },
        imap_sync: { requests: 100, window: 60 }
      }
    };
  }
  
  async checkLimit(key, identifier, customLimit = null) {
    const limit = customLimit || this.getLimitConfig(key);
    const windowStart = Date.now() - (limit.window * 1000);
    
    // Get current usage
    const usage = await this.redis.zcount(
      `rate_limit:${key}:${identifier}`,
      windowStart,
      Date.now()
    );
    
    if (usage >= limit.requests) {
      const oldestRequest = await this.redis.zrange(
        `rate_limit:${key}:${identifier}`,
        0, 0,
        'WITHSCORES'
      );
      
      const resetTime = oldestRequest.length > 0 
        ? parseInt(oldestRequest[1]) + (limit.window * 1000)
        : Date.now() + (limit.window * 1000);
      
      return {
        allowed: false,
        limit: limit.requests,
        remaining: 0,
        reset_time: resetTime,
        retry_after: Math.ceil((resetTime - Date.now()) / 1000)
      };
    }
    
    // Record this request
    await this.recordRequest(key, identifier, limit.window);
    
    return {
      allowed: true,
      limit: limit.requests,
      remaining: limit.requests - usage - 1,
      reset_time: Date.now() + (limit.window * 1000)
    };
  }
  
  middleware(limitKey) {
    return async (req, res, next) => {
      const identifier = this.getIdentifier(req, limitKey);
      const result = await this.checkLimit(limitKey, identifier);
      
      // Add rate limit headers
      res.set({
        'X-RateLimit-Limit': result.limit,
        'X-RateLimit-Remaining': result.remaining,
        'X-RateLimit-Reset': result.reset_time
      });
      
      if (!result.allowed) {
        res.set('Retry-After', result.retry_after);
        return res.status(429).json({
          error: 'Rate limit exceeded',
          retry_after: result.retry_after
        });
      }
      
      next();
    };
  }
}
```

## Incident Response Playbook

### Kill Switch Implementation

#### Emergency Stop Procedures
```javascript
class EmergencyKillSwitch {
  constructor() {
    this.killSwitchTypes = {
      GLOBAL_AUTO_SEND: 'Stop all automated email sending',
      ACCOUNT_SPECIFIC: 'Stop automation for specific account',
      RULE_SPECIFIC: 'Disable specific rule',
      LLM_PROCESSING: 'Stop all AI processing',
      IMAP_SYNC: 'Stop all email ingestion'
    };
    
    this.activationMethods = {
      API: 'POST /emergency/kill-switch',
      ADMIN_UI: 'Emergency button in admin interface',
      CLI: 'Emergency CLI command',
      MONITORING: 'Automatic trigger from monitoring'
    };
  }
  
  async activateKillSwitch(type, reason, activatedBy, scope = {}) {
    const killSwitchId = this.generateKillSwitchId();
    
    // Record activation
    const activation = {
      id: killSwitchId,
      type: type,
      reason: reason,
      activated_by: activatedBy,
      activated_at: new Date(),
      scope: scope,
      status: 'active'
    };
    
    await this.recordKillSwitchActivation(activation);
    
    // Execute kill switch based on type
    switch (type) {
      case 'GLOBAL_AUTO_SEND':
        await this.stopAllAutoSend();
        break;
        
      case 'ACCOUNT_SPECIFIC':
        await this.stopAccountAutomation(scope.account_id);
        break;
        
      case 'RULE_SPECIFIC':
        await this.disableRule(scope.rule_id);
        break;
        
      case 'LLM_PROCESSING':
        await this.stopLLMProcessing();
        break;
        
      case 'IMAP_SYNC':
        await this.stopIMAPSync();
        break;
    }
    
    // Send notifications
    await this.notifyKillSwitchActivation(activation);
    
    // Log security event
    await this.logSecurityEvent('kill_switch_activated', {
      kill_switch_id: killSwitchId,
      type: type,
      reason: reason,
      activated_by: activatedBy,
      scope: scope
    });
    
    return {
      kill_switch_id: killSwitchId,
      status: 'activated',
      affected_systems: this.getAffectedSystems(type, scope)
    };
  }
  
  async stopAllAutoSend() {
    // Set global flag to prevent any automated sending
    await this.redis.set('kill_switch:auto_send', 'true');
    
    // Cancel all queued send operations
    await this.cancelQueuedSends();
    
    // Notify all workers to stop processing
    await this.notifyWorkers('STOP_AUTO_SEND');
    
    return {
      action: 'All automated email sending stopped',
      queued_sends_cancelled: await this.getQueuedSendCount(),
      workers_notified: await this.getActiveWorkerCount()
    };
  }
  
  async deactivateKillSwitch(killSwitchId, deactivatedBy, reason) {
    const killSwitch = await this.getKillSwitch(killSwitchId);
    
    if (!killSwitch || killSwitch.status !== 'active') {
      throw new Error('Kill switch not found or not active');
    }
    
    // Update status
    await this.updateKillSwitchStatus(killSwitchId, 'deactivated', {
      deactivated_by: deactivatedBy,
      deactivated_at: new Date(),
      deactivation_reason: reason
    });
    
    // Restore systems based on type
    await this.restoreSystems(killSwitch.type, killSwitch.scope);
    
    // Log deactivation
    await this.logSecurityEvent('kill_switch_deactivated', {
      kill_switch_id: killSwitchId,
      deactivated_by: deactivatedBy,
      reason: reason
    });
    
    return {
      status: 'deactivated',
      systems_restored: this.getAffectedSystems(killSwitch.type, killSwitch.scope)
    };
  }
}
```

### Incident Response Procedures

#### Security Incident Classification
```javascript
const INCIDENT_TYPES = {
  DATA_BREACH: {
    severity: 'critical',
    response_time: '15 minutes',
    escalation: ['security_team', 'legal', 'executive'],
    procedures: ['isolate_systems', 'preserve_evidence', 'notify_authorities']
  },
  
  UNAUTHORIZED_ACCESS: {
    severity: 'high',
    response_time: '30 minutes',
    escalation: ['security_team', 'it_admin'],
    procedures: ['disable_accounts', 'audit_access', 'reset_credentials']
  },
  
  ABUSE_DETECTED: {
    severity: 'medium',
    response_time: '1 hour',
    escalation: ['abuse_team'],
    procedures: ['suspend_account', 'investigate_pattern', 'notify_customer']
  },
  
  SYSTEM_COMPROMISE: {
    severity: 'critical',
    response_time: '10 minutes',
    escalation: ['security_team', 'it_admin', 'executive'],
    procedures: ['isolate_systems', 'activate_kill_switch', 'forensic_analysis']
  }
};
```

#### Automated Response Actions
```javascript
class IncidentResponseAutomation {
  async handleSecurityIncident(incidentType, details) {
    const incident = {
      id: this.generateIncidentId(),
      type: incidentType,
      severity: INCIDENT_TYPES[incidentType].severity,
      detected_at: new Date(),
      details: details,
      status: 'active'
    };
    
    // Immediate automated responses
    const automatedActions = await this.executeAutomatedResponse(incident);
    
    // Human escalation
    await this.escalateToHumans(incident);
    
    // Evidence preservation
    await this.preserveEvidence(incident);
    
    return {
      incident_id: incident.id,
      automated_actions: automatedActions,
      escalated_to: INCIDENT_TYPES[incidentType].escalation
    };
  }
  
  async executeAutomatedResponse(incident) {
    const actions = [];
    
    switch (incident.type) {
      case 'DATA_BREACH':
        // Immediate containment
        await this.activateKillSwitch('GLOBAL_AUTO_SEND', 'Data breach detected');
        await this.isolateAffectedSystems(incident.details.affected_systems);
        actions.push('Global kill switch activated', 'Affected systems isolated');
        break;
        
      case 'UNAUTHORIZED_ACCESS':
        // Disable compromised accounts
        if (incident.details.compromised_accounts) {
          await this.disableAccounts(incident.details.compromised_accounts);
          actions.push('Compromised accounts disabled');
        }
        break;
        
      case 'ABUSE_DETECTED':
        // Suspend abusive account
        await this.suspendAccount(incident.details.account_id, 'Abuse detected');
        actions.push('Abusive account suspended');
        break;
    }
    
    return actions;
  }
}
```

## Risk Assessment Matrix

### Risk Scoring and Controls

```javascript
const RISK_MATRIX = {
  'Email Content Exposure': {
    likelihood: 'Medium',
    impact: 'High',
    risk_score: 8,
    controls: [
      'End-to-end encryption',
      'Access logging',
      'Data classification',
      'Regular access reviews'
    ],
    residual_risk: 4
  },
  
  'Credential Theft': {
    likelihood: 'High',
    impact: 'High',
    risk_score: 9,
    controls: [
      'Secrets encryption',
      'Key rotation',
      'Access monitoring',
      'MFA enforcement'
    ],
    residual_risk: 3
  },
  
  'SMTP Abuse': {
    likelihood: 'High',
    impact: 'Medium',
    risk_score: 7,
    controls: [
      'Rate limiting',
      'Content filtering',
      'Reputation monitoring',
      'Abuse detection'
    ],
    residual_risk: 3
  },
  
  'Rule Misconfiguration': {
    likelihood: 'Medium',
    impact: 'Medium',
    risk_score: 6,
    controls: [
      'Rule validation',
      'Safety limits',
      'Human approval',
      'Audit logging'
    ],
    residual_risk: 2
  },
  
  'LLM Prompt Injection': {
    likelihood: 'Medium',
    impact: 'Medium',
    risk_score: 6,
    controls: [
      'Input sanitization',
      'Output validation',
      'Prompt isolation',
      'Content filtering'
    ],
    residual_risk: 3
  }
};
```

## CI/CD Security Gates

### Security Checklist for Deployments

```yaml
security_gates:
  pre_deployment:
    - name: "Dependency Vulnerability Scan"
      tool: "npm audit"
      threshold: "no high/critical vulnerabilities"
      
    - name: "Static Code Analysis"
      tool: "SonarQube"
      threshold: "security rating A"
      
    - name: "Secret Detection"
      tool: "GitLeaks"
      threshold: "no secrets detected"
      
    - name: "Container Security Scan"
      tool: "Trivy"
      threshold: "no critical vulnerabilities"
      
  post_deployment:
    - name: "TLS Configuration Test"
      test: "SSL Labs A+ rating"
      
    - name: "Security Headers Check"
      test: "All security headers present"
      
    - name: "Authentication Test"
      test: "MFA enforcement working"
      
    - name: "Rate Limiting Test"
      test: "Rate limits properly enforced"
```

### Automated Security Testing

```javascript
class SecurityTestSuite {
  async runSecurityTests() {
    const results = {
      passed: 0,
      failed: 0,
      tests: []
    };
    
    // Test TLS configuration
    const tlsTest = await this.testTLSConfiguration();
    results.tests.push(tlsTest);
    
    // Test authentication
    const authTest = await this.testAuthentication();
    results.tests.push(authTest);
    
    // Test rate limiting
    const rateLimitTest = await this.testRateLimiting();
    results.tests.push(rateLimitTest);
    
    // Test CSRF protection
    const csrfTest = await this.testCSRFProtection();
    results.tests.push(csrfTest);
    
    // Test input validation
    const inputTest = await this.testInputValidation();
    results.tests.push(inputTest);
    
    // Calculate results
    results.passed = results.tests.filter(t => t.passed).length;
    results.failed = results.tests.filter(t => !t.passed).length;
    
    return results;
  }
  
  async testTLSConfiguration() {
    try {
      const response = await axios.get('https://api.ssllabs.com/api/v3/analyze', {
        params: {
          host: process.env.DOMAIN,
          publish: 'off',
          startNew: 'on'
        }
      });
      
      const grade = response.data.endpoints[0].grade;
      
      return {
        name: 'TLS Configuration',
        passed: ['A+', 'A', 'A-'].includes(grade),
        details: { ssl_labs_grade: grade }
      };
      
    } catch (error) {
      return {
        name: 'TLS Configuration',
        passed: false,
        error: error.message
      };
    }
  }
}
```

---

## Pragmatic Review

### ✅ Comprehensive Threat Coverage
- Complete threat model covering external and internal threats
- Detailed attack vectors and scenarios
- Risk assessment with quantified scores
- Appropriate controls for each identified risk

### ✅ Defense in Depth
- Multiple layers of security controls
- Network, application, and data-level protections
- Proactive monitoring and detection
- Automated response capabilities

### ✅ Privacy and Compliance
- Comprehensive PII detection and redaction
- Structured logging with privacy protection
- Opt-out mechanisms and consent management
- Audit trails for compliance requirements

### ✅ Incident Response Readiness
- Emergency kill switch procedures
- Automated incident response
- Clear escalation procedures
- Evidence preservation capabilities

### Potential Issues Identified:
1. **Performance Impact**: Extensive security controls may impact system performance
2. **Complexity**: Multiple security layers could increase operational complexity
3. **False Positives**: Aggressive abuse detection might block legitimate usage
4. **Key Management**: Complex encryption key rotation procedures

### Recommended Fixes:
1. Implement performance monitoring for security controls
2. Create security control management dashboard
3. Tune abuse detection algorithms with machine learning
4. Consider using managed key management services (AWS KMS, etc.)