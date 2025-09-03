# Part 4: LLM Abstraction (OpenAI v1 / Gemini)

## Overview
Design for the LLM Service abstraction layer that provides unified interfaces to multiple LLM providers (OpenAI, Gemini) with safety controls, error handling, and observability.

## HTTP Interfaces

### Core LLM Endpoints

#### POST /llm/classify
**Purpose:** Classify email content for intent, category, sentiment, urgency, and risk assessment.

**Request:**
```json
{
  "email": {
    "sender": "string",
    "recipient": "string",
    "subject": "string",
    "body": "string",
    "headers": "object?", // Optional email headers
    "thread_history": "string?", // Previous emails in thread
    "attachments": [
      {
        "filename": "string",
        "content_type": "string",
        "size_bytes": "number"
      }
    ]?
  },
  "context": {
    "account_id": "string",
    "account_domain": "string",
    "business_category": "string?", // "saas", "ecommerce", "consulting", etc.
    "timezone": "string",
    "business_hours": {
      "start": "string", // "09:00"
      "end": "string",   // "17:00"
      "days": "string[]" // ["monday", "tuesday", ...]
    }?
  },
  "options": {
    "provider": "string?", // "openai", "gemini", "auto"
    "model": "string?", // Provider-specific model
    "confidence_threshold": "number?", // 0.0-1.0, default 0.7
    "include_reasoning": "boolean?", // Include explanation
    "language_hint": "string?", // "en", "es", "fr", etc.
    "custom_categories": "string[]?", // Account-specific categories
    "risk_tolerance": "string?" // "low", "medium", "high"
  }
}
```

**Response:**
```json
{
  "classification": {
    "intent": {
      "primary": "string", // "question", "complaint", "request", "praise", "spam"
      "secondary": "string?", // Additional intent if applicable
      "confidence": "number" // 0.0-1.0
    },
    "category": {
      "primary": "string", // "support", "sales", "billing", "hr", "general"
      "secondary": "string?",
      "confidence": "number"
    },
    "sentiment": {
      "polarity": "string", // "positive", "neutral", "negative"
      "intensity": "number", // 0.0-1.0
      "confidence": "number"
    },
    "urgency": {
      "level": "string", // "low", "medium", "high", "urgent"
      "indicators": "string[]", // ["deadline_mentioned", "escalation_language"]
      "confidence": "number"
    },
    "risk": {
      "level": "string", // "low", "medium", "high"
      "factors": "string[]", // ["legal_language", "threat_detected", "sensitive_data"]
      "confidence": "number",
      "requires_human": "boolean"
    },
    "language": {
      "detected": "string", // ISO 639-1 code
      "confidence": "number"
    },
    "topics": [
      {
        "name": "string",
        "relevance": "number" // 0.0-1.0
      }
    ],
    "entities": [
      {
        "type": "string", // "person", "organization", "product", "date"
        "value": "string",
        "confidence": "number"
      }
    ]
  },
  "metadata": {
    "provider": "string",
    "model": "string",
    "tokens_used": "number",
    "response_time_ms": "number",
    "cost": "number",
    "request_id": "string",
    "reasoning": "string?", // If include_reasoning=true
    "warnings": "string[]?"
  },
  "quality": {
    "overall_confidence": "number", // Weighted average of all confidences
    "reliability_score": "number", // Based on historical accuracy
    "completeness": "number", // How much of the email was analyzed
    "consistency": "number" // Internal consistency of classifications
  }
}
```

#### POST /llm/draft
**Purpose:** Generate email response drafts based on classification and prompt templates.

**Request:**
```json
{
  "email": {
    "sender": "string",
    "recipient": "string",
    "subject": "string",
    "body": "string",
    "thread_history": "string?"
  },
  "classification": {
    "intent": "object", // From /llm/classify response
    "category": "object",
    "sentiment": "object",
    "urgency": "object",
    "risk": "object"
  },
  "prompt": {
    "template_id": "string",
    "system_prompt": "string",
    "user_prompt": "string",
    "variables": "object", // Variable name -> value mapping
    "output_format": {
      "type": "json|text|markdown",
      "schema": "object?",
      "max_tokens": "number",
      "temperature": "number"
    }
  },
  "context": {
    "account_id": "string",
    "account_name": "string",
    "support_info": {
      "email": "string",
      "phone": "string?",
      "hours": "string?",
      "website": "string?"
    },
    "sender_history": {
      "previous_emails": "number",
      "last_interaction": "string?",
      "customer_tier": "string?"
    }
  },
  "options": {
    "provider": "string?",
    "model": "string?",
    "tone": "string?", // "professional", "friendly", "formal", "casual"
    "length": "string?", // "brief", "medium", "detailed"
    "include_signature": "boolean?",
    "preserve_thread": "boolean?",
    "safety_level": "string?" // "strict", "moderate", "permissive"
  }
}
```

**Response:**
```json
{
  "draft": {
    "subject": "string", // Reply subject (may be modified)
    "body_text": "string", // Plain text version
    "body_html": "string?", // HTML version if requested
    "structured_data": "object?", // If JSON output format
    "attachments": [
      {
        "filename": "string",
        "content_type": "string",
        "content": "string" // Base64 encoded
      }
    ]?
  },
  "analysis": {
    "tone_detected": "string",
    "formality_level": "string", // "very_formal", "formal", "neutral", "casual"
    "completeness": "number", // 0.0-1.0, addresses all points
    "appropriateness": "number", // 0.0-1.0, suitable for context
    "safety_score": "number", // 0.0-1.0, safety assessment
    "requires_review": "boolean",
    "review_reasons": "string[]?" // Why review is needed
  },
  "suggestions": [
    {
      "type": "string", // "tone_adjustment", "add_information", "remove_content"
      "description": "string",
      "severity": "string", // "info", "warning", "error"
      "auto_fixable": "boolean"
    }
  ],
  "metadata": {
    "provider": "string",
    "model": "string",
    "tokens_used": "number",
    "response_time_ms": "number",
    "cost": "number",
    "request_id": "string",
    "prompt_hash": "string", // SHA256 of compiled prompt
    "safety_filters_applied": "string[]"
  }
}
```

### Provider Management

#### GET /llm/providers
**List Available Providers:**
```json
{
  "providers": [
    {
      "id": "openai",
      "name": "OpenAI",
      "status": "active", // "active", "inactive", "error"
      "models": [
        {
          "id": "gpt-4",
          "name": "GPT-4",
          "capabilities": ["classification", "generation", "reasoning"],
          "max_tokens": 8192,
          "cost_per_1k_tokens": {
            "input": 0.03,
            "output": 0.06
          },
          "rate_limits": {
            "requests_per_minute": 3500,
            "tokens_per_minute": 90000
          }
        }
      ],
      "health": {
        "last_check": "string",
        "response_time_ms": "number",
        "success_rate_24h": "number",
        "error_rate_24h": "number"
      }
    }
  ],
  "default_provider": "string",
  "fallback_chain": "string[]"
}
```

#### POST /llm/providers/:id/test
**Test Provider Connection:**
```json
{
  "test_type": "string", // "health", "classification", "generation"
  "sample_data": "object?"
}
```

**Response:**
```json
{
  "success": "boolean",
  "response_time_ms": "number",
  "error": "string?",
  "test_results": {
    "api_accessible": "boolean",
    "authentication_valid": "boolean",
    "rate_limits_ok": "boolean",
    "model_available": "boolean",
    "response_quality": "number?" // 0.0-1.0
  }
}
```

## Provider Adapters

### OpenAI Adapter

#### Configuration
```json
{
  "openai_config": {
    "api_key": "string", // From environment/secrets
    "organization_id": "string?",
    "base_url": "string", // Default: "https://api.openai.com/v1"
    "default_model": "gpt-4",
    "timeout_seconds": 30,
    "max_retries": 3,
    "retry_delay_ms": 1000,
    "rate_limit": {
      "requests_per_minute": 3000,
      "tokens_per_minute": 80000,
      "concurrent_requests": 100
    }
  }
}
```

#### Model Mapping
```javascript
const openaiModels = {
  classification: {
    primary: 'gpt-4',
    fallback: 'gpt-3.5-turbo',
    fast: 'gpt-3.5-turbo'
  },
  generation: {
    primary: 'gpt-4',
    fallback: 'gpt-3.5-turbo',
    creative: 'gpt-4'
  },
  reasoning: {
    primary: 'gpt-4',
    fallback: 'gpt-4'
  }
};
```

#### Request Transformation
```javascript
class OpenAIAdapter {
  async classify(request) {
    const messages = [
      {
        role: 'system',
        content: this.buildClassificationSystemPrompt(request.context)
      },
      {
        role: 'user',
        content: this.buildClassificationUserPrompt(request.email)
      }
    ];
    
    const openaiRequest = {
      model: request.options?.model || this.config.default_model,
      messages: messages,
      temperature: 0.1, // Low temperature for consistent classification
      max_tokens: 1000,
      response_format: { type: 'json_object' },
      tools: [
        {
          type: 'function',
          function: {
            name: 'classify_email',
            description: 'Classify email content',
            parameters: this.getClassificationSchema()
          }
        }
      ],
      tool_choice: { type: 'function', function: { name: 'classify_email' } }
    };
    
    return this.makeRequest('/chat/completions', openaiRequest);
  }
  
  async draft(request) {
    const messages = [
      {
        role: 'system',
        content: request.prompt.system_prompt
      },
      {
        role: 'user',
        content: request.prompt.user_prompt
      }
    ];
    
    const openaiRequest = {
      model: request.options?.model || this.config.default_model,
      messages: messages,
      temperature: request.prompt.output_format.temperature || 0.7,
      max_tokens: request.prompt.output_format.max_tokens || 1000,
      stop: request.prompt.output_format.stop_sequences
    };
    
    if (request.prompt.output_format.type === 'json') {
      openaiRequest.response_format = { type: 'json_object' };
    }
    
    return this.makeRequest('/chat/completions', openaiRequest);
  }
}
```

### Gemini Adapter

#### Configuration
```json
{
  "gemini_config": {
    "api_key": "string",
    "base_url": "string", // Default: "https://generativelanguage.googleapis.com/v1"
    "default_model": "gemini-pro",
    "timeout_seconds": 30,
    "max_retries": 3,
    "retry_delay_ms": 1000,
    "rate_limit": {
      "requests_per_minute": 60,
      "requests_per_day": 1500
    }
  }
}
```

#### Model Mapping
```javascript
const geminiModels = {
  classification: {
    primary: 'gemini-pro',
    fallback: 'gemini-pro'
  },
  generation: {
    primary: 'gemini-pro',
    creative: 'gemini-pro'
  }
};
```

#### Request Transformation
```javascript
class GeminiAdapter {
  async classify(request) {
    const prompt = this.buildClassificationPrompt(request.email, request.context);
    
    const geminiRequest = {
      contents: [
        {
          parts: [
            {
              text: prompt
            }
          ]
        }
      ],
      generationConfig: {
        temperature: 0.1,
        topK: 1,
        topP: 0.8,
        maxOutputTokens: 1000,
        responseMimeType: 'application/json'
      },
      safetySettings: this.getSafetySettings()
    };
    
    return this.makeRequest(`/models/${this.config.default_model}:generateContent`, geminiRequest);
  }
  
  async draft(request) {
    const prompt = `${request.prompt.system_prompt}\n\n${request.prompt.user_prompt}`;
    
    const geminiRequest = {
      contents: [
        {
          parts: [
            {
              text: prompt
            }
          ]
        }
      ],
      generationConfig: {
        temperature: request.prompt.output_format.temperature || 0.7,
        maxOutputTokens: request.prompt.output_format.max_tokens || 1000,
        stopSequences: request.prompt.output_format.stop_sequences
      },
      safetySettings: this.getSafetySettings(request.options?.safety_level)
    };
    
    return this.makeRequest(`/models/${this.config.default_model}:generateContent`, geminiRequest);
  }
}
```

## Safety Matrix

### Content Safety Levels

#### Strict Safety (Default)
```json
{
  "strict_safety": {
    "blocked_content": [
      "harassment",
      "hate_speech",
      "sexually_explicit",
      "dangerous_content",
      "personal_information",
      "financial_data",
      "medical_information",
      "legal_advice",
      "investment_advice"
    ],
    "content_filters": {
      "profanity": "block",
      "personal_data": "redact",
      "urls": "validate",
      "email_addresses": "mask",
      "phone_numbers": "mask"
    },
    "response_constraints": {
      "max_length": 1000,
      "require_professional_tone": true,
      "avoid_commitments": true,
      "include_disclaimers": true
    }
  }
}
```

#### Moderate Safety
```json
{
  "moderate_safety": {
    "blocked_content": [
      "harassment",
      "hate_speech",
      "sexually_explicit",
      "dangerous_content"
    ],
    "content_filters": {
      "profanity": "warn",
      "personal_data": "flag",
      "urls": "allow",
      "email_addresses": "allow",
      "phone_numbers": "allow"
    },
    "response_constraints": {
      "max_length": 2000,
      "require_professional_tone": false,
      "avoid_commitments": true,
      "include_disclaimers": false
    }
  }
}
```

#### Permissive Safety
```json
{
  "permissive_safety": {
    "blocked_content": [
      "illegal_content",
      "extreme_violence"
    ],
    "content_filters": {
      "profanity": "allow",
      "personal_data": "allow",
      "urls": "allow",
      "email_addresses": "allow",
      "phone_numbers": "allow"
    },
    "response_constraints": {
      "max_length": 5000,
      "require_professional_tone": false,
      "avoid_commitments": false,
      "include_disclaimers": false
    }
  }
}
```

### Safety Implementation

#### Pre-processing Filters
```javascript
class SafetyProcessor {
  async preprocessRequest(request, safetyLevel) {
    const filters = this.getSafetyConfig(safetyLevel);
    const processed = { ...request };
    
    // Content filtering
    processed.email.body = await this.filterContent(
      request.email.body, 
      filters.content_filters
    );
    
    // PII detection and redaction
    if (filters.content_filters.personal_data === 'redact') {
      processed.email.body = await this.redactPII(processed.email.body);
    }
    
    // URL validation
    if (filters.content_filters.urls === 'validate') {
      processed.email.body = await this.validateUrls(processed.email.body);
    }
    
    return processed;
  }
  
  async postprocessResponse(response, safetyLevel) {
    const filters = this.getSafetyConfig(safetyLevel);
    const processed = { ...response };
    
    // Length constraints
    if (processed.draft?.body_text?.length > filters.response_constraints.max_length) {
      processed.draft.body_text = this.truncateResponse(
        processed.draft.body_text,
        filters.response_constraints.max_length
      );
    }
    
    // Tone enforcement
    if (filters.response_constraints.require_professional_tone) {
      const toneScore = await this.analyzeTone(processed.draft.body_text);
      if (toneScore.professionalism < 0.7) {
        processed.analysis.requires_review = true;
        processed.analysis.review_reasons.push('unprofessional_tone');
      }
    }
    
    // Commitment detection
    if (filters.response_constraints.avoid_commitments) {
      const commitments = await this.detectCommitments(processed.draft.body_text);
      if (commitments.length > 0) {
        processed.analysis.requires_review = true;
        processed.analysis.review_reasons.push('contains_commitments');
      }
    }
    
    return processed;
  }
}
```

## Error Taxonomy

### Provider Errors

#### OpenAI Error Mapping
```javascript
const openaiErrorMap = {
  400: {
    type: 'invalid_request',
    severity: 'error',
    retry: false,
    user_message: 'Invalid request format'
  },
  401: {
    type: 'authentication_failed',
    severity: 'critical',
    retry: false,
    user_message: 'API authentication failed'
  },
  403: {
    type: 'permission_denied',
    severity: 'error',
    retry: false,
    user_message: 'Access denied'
  },
  429: {
    type: 'rate_limit_exceeded',
    severity: 'warning',
    retry: true,
    backoff: 'exponential',
    user_message: 'Rate limit exceeded, retrying'
  },
  500: {
    type: 'provider_error',
    severity: 'error',
    retry: true,
    backoff: 'linear',
    user_message: 'Provider temporarily unavailable'
  },
  503: {
    type: 'service_unavailable',
    severity: 'warning',
    retry: true,
    backoff: 'exponential',
    user_message: 'Service temporarily unavailable'
  }
};
```

#### Gemini Error Mapping
```javascript
const geminiErrorMap = {
  'INVALID_ARGUMENT': {
    type: 'invalid_request',
    severity: 'error',
    retry: false,
    user_message: 'Invalid request parameters'
  },
  'PERMISSION_DENIED': {
    type: 'permission_denied',
    severity: 'error',
    retry: false,
    user_message: 'API access denied'
  },
  'RESOURCE_EXHAUSTED': {
    type: 'quota_exceeded',
    severity: 'warning',
    retry: true,
    backoff: 'exponential',
    user_message: 'API quota exceeded'
  },
  'UNAVAILABLE': {
    type: 'service_unavailable',
    severity: 'warning',
    retry: true,
    backoff: 'exponential',
    user_message: 'Service temporarily unavailable'
  },
  'SAFETY': {
    type: 'content_filtered',
    severity: 'warning',
    retry: false,
    user_message: 'Content filtered by safety policies'
  }
};
```

### Application Errors

#### Classification Errors
```javascript
const classificationErrors = {
  CONFIDENCE_TOO_LOW: {
    code: 'CLASSIFICATION_LOW_CONFIDENCE',
    message: 'Classification confidence below threshold',
    severity: 'warning',
    action: 'fallback_to_human',
    retry: false
  },
  INCONSISTENT_RESULTS: {
    code: 'CLASSIFICATION_INCONSISTENT',
    message: 'Multiple classification attempts yielded different results',
    severity: 'warning',
    action: 'use_most_confident',
    retry: true
  },
  TIMEOUT: {
    code: 'CLASSIFICATION_TIMEOUT',
    message: 'Classification request timed out',
    severity: 'error',
    action: 'retry_with_fallback',
    retry: true
  },
  CONTENT_TOO_LARGE: {
    code: 'CONTENT_SIZE_EXCEEDED',
    message: 'Email content exceeds maximum size limit',
    severity: 'error',
    action: 'truncate_and_retry',
    retry: true
  }
};
```

#### Generation Errors
```javascript
const generationErrors = {
  PROMPT_TOO_LONG: {
    code: 'PROMPT_SIZE_EXCEEDED',
    message: 'Compiled prompt exceeds token limit',
    severity: 'error',
    action: 'truncate_context',
    retry: true
  },
  UNSAFE_CONTENT: {
    code: 'CONTENT_SAFETY_VIOLATION',
    message: 'Generated content violates safety policies',
    severity: 'warning',
    action: 'regenerate_with_stricter_safety',
    retry: true
  },
  QUALITY_TOO_LOW: {
    code: 'RESPONSE_QUALITY_LOW',
    message: 'Generated response quality below threshold',
    severity: 'warning',
    action: 'regenerate_or_escalate',
    retry: true
  },
  TEMPLATE_ERROR: {
    code: 'TEMPLATE_COMPILATION_FAILED',
    message: 'Prompt template compilation failed',
    severity: 'error',
    action: 'use_fallback_template',
    retry: true
  }
};
```

### Error Recovery Strategies

#### Retry Logic
```javascript
class ErrorRecovery {
  async executeWithRetry(operation, maxRetries = 3) {
    let lastError;
    
    for (let attempt = 1; attempt <= maxRetries; attempt++) {
      try {
        return await operation();
      } catch (error) {
        lastError = error;
        const errorInfo = this.classifyError(error);
        
        if (!errorInfo.retry || attempt === maxRetries) {
          throw this.enhanceError(error, errorInfo);
        }
        
        const delay = this.calculateBackoff(errorInfo.backoff, attempt);
        await this.sleep(delay);
        
        // Apply error-specific recovery actions
        if (errorInfo.action) {
          await this.applyRecoveryAction(errorInfo.action, error);
        }
      }
    }
    
    throw lastError;
  }
  
  calculateBackoff(strategy, attempt) {
    switch (strategy) {
      case 'exponential':
        return Math.min(1000 * Math.pow(2, attempt - 1), 30000);
      case 'linear':
        return 1000 * attempt;
      case 'fixed':
        return 1000;
      default:
        return 1000;
    }
  }
  
  async applyRecoveryAction(action, error) {
    switch (action) {
      case 'fallback_to_human':
        return this.escalateToHuman(error);
      case 'use_fallback_template':
        return this.switchToFallbackTemplate();
      case 'truncate_and_retry':
        return this.truncateContent();
      case 'regenerate_with_stricter_safety':
        return this.increaseSafetyLevel();
    }
  }
}
```

#### Provider Fallback
```javascript
class ProviderFallback {
  constructor() {
    this.fallbackChain = ['openai', 'gemini'];
    this.circuitBreakers = new Map();
  }
  
  async executeWithFallback(operation, providers = this.fallbackChain) {
    let lastError;
    
    for (const provider of providers) {
      if (this.isCircuitOpen(provider)) {
        continue; // Skip if circuit breaker is open
      }
      
      try {
        const result = await operation(provider);
        this.recordSuccess(provider);
        return result;
      } catch (error) {
        lastError = error;
        this.recordFailure(provider, error);
        
        const errorInfo = this.classifyError(error);
        if (!errorInfo.retry) {
          // Don't try other providers for non-retryable errors
          break;
        }
      }
    }
    
    throw lastError || new Error('All providers failed');
  }
  
  recordFailure(provider, error) {
    const breaker = this.getCircuitBreaker(provider);
    breaker.recordFailure(error);
    
    if (breaker.shouldOpen()) {
      console.warn(`Circuit breaker opened for provider: ${provider}`);
      this.scheduleCircuitReset(provider);
    }
  }
}
```

## Observability

### Metrics Collection

#### Request Metrics
```javascript
const requestMetrics = {
  // Counters
  'llm_requests_total': {
    type: 'counter',
    labels: ['provider', 'model', 'endpoint', 'status']
  },
  'llm_errors_total': {
    type: 'counter',
    labels: ['provider', 'error_type', 'severity']
  },
  
  // Histograms
  'llm_request_duration_seconds': {
    type: 'histogram',
    labels: ['provider', 'model', 'endpoint'],
    buckets: [0.1, 0.5, 1, 2, 5, 10, 30]
  },
  'llm_tokens_used': {
    type: 'histogram',
    labels: ['provider', 'model', 'type'], // type: input/output
    buckets: [10, 50, 100, 500, 1000, 2000, 5000]
  },
  
  // Gauges
  'llm_cost_per_request': {
    type: 'gauge',
    labels: ['provider', 'model']
  },
  'llm_provider_health': {
    type: 'gauge',
    labels: ['provider']
  }
};
```

#### Quality Metrics
```javascript
const qualityMetrics = {
  'classification_confidence': {
    type: 'histogram',
    labels: ['provider', 'category', 'intent'],
    buckets: [0.1, 0.3, 0.5, 0.7, 0.8, 0.9, 0.95, 1.0]
  },
  'generation_quality_score': {
    type: 'histogram',
    labels: ['provider', 'template_id'],
    buckets: [0.1, 0.3, 0.5, 0.7, 0.8, 0.9, 0.95, 1.0]
  },
  'safety_violations': {
    type: 'counter',
    labels: ['provider', 'violation_type', 'safety_level']
  },
  'human_escalations': {
    type: 'counter',
    labels: ['reason', 'provider', 'confidence_threshold']
  }
};
```

### Logging Strategy

#### Structured Logging
```javascript
class LLMLogger {
  logRequest(request, metadata) {
    const logEntry = {
      timestamp: new Date().toISOString(),
      level: 'info',
      event: 'llm_request',
      request_id: metadata.request_id,
      provider: metadata.provider,
      model: metadata.model,
      endpoint: request.endpoint,
      account_id: request.context?.account_id,
      email_id: request.email?.id,
      prompt_hash: this.hashPrompt(request.prompt),
      tokens_estimate: this.estimateTokens(request),
      safety_level: request.options?.safety_level || 'strict'
    };
    
    // Redact sensitive data
    if (request.email) {
      logEntry.email_preview = this.redactEmail(request.email);
    }
    
    this.logger.info(logEntry);
  }
  
  logResponse(response, metadata, duration) {
    const logEntry = {
      timestamp: new Date().toISOString(),
      level: 'info',
      event: 'llm_response',
      request_id: metadata.request_id,
      provider: metadata.provider,
      model: metadata.model,
      success: !response.error,
      duration_ms: duration,
      tokens_used: response.metadata?.tokens_used,
      cost: response.metadata?.cost,
      quality_score: response.quality?.overall_confidence,
      requires_review: response.analysis?.requires_review,
      safety_score: response.analysis?.safety_score
    };
    
    if (response.error) {
      logEntry.level = 'error';
      logEntry.error_type = response.error.type;
      logEntry.error_message = response.error.message;
    }
    
    this.logger.info(logEntry);
  }
  
  redactEmail(email) {
    return {
      sender_domain: this.extractDomain(email.sender),
      subject_length: email.subject?.length || 0,
      body_length: email.body?.length || 0,
      has_attachments: email.attachments?.length > 0
    };
  }
}
```

## Data Schemas

### llm_providers
```sql
CREATE TABLE llm_providers (
  id VARCHAR(50) PRIMARY KEY, -- 'openai', 'gemini', etc.
  name VARCHAR(100) NOT NULL,
  status ENUM('active', 'inactive', 'maintenance', 'error') DEFAULT 'active',
  
  -- Configuration
  config_json JSON NOT NULL, -- API keys, endpoints, etc.
  default_model VARCHAR(100),
  
  -- Rate limiting
  rate_limit_rpm INT, -- Requests per minute
  rate_limit_tpm INT, -- Tokens per minute
  rate_limit_rpd INT, -- Requests per day
  
  -- Health monitoring
  last_health_check TIMESTAMP,
  health_status ENUM('healthy', 'degraded', 'unhealthy'),
  avg_response_time_ms DECIMAL(8,2),
  success_rate_24h DECIMAL(5,4), -- 0.0000-1.0000
  error_rate_24h DECIMAL(5,4),
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  INDEX idx_status_health (status, health_status),
  INDEX idx_performance (success_rate_24h, avg_response_time_ms)
);
```

### llm_requests
```sql
CREATE TABLE llm_requests (
  id VARCHAR(36) PRIMARY KEY,
  
  -- Request context
  account_id VARCHAR(36) NOT NULL,
  processed_email_id VARCHAR(36), -- Link to email being processed
  endpoint VARCHAR(50) NOT NULL, -- 'classify', 'draft'
  
  -- Provider details
  provider_id VARCHAR(50) NOT NULL,
  model VARCHAR(100) NOT NULL,
  
  -- Request data (hashed/redacted)
  prompt_hash VARCHAR(64), -- SHA256 of compiled prompt
  request_size_bytes INT,
  safety_level ENUM('strict', 'moderate', 'permissive') DEFAULT 'strict',
  
  -- Response data
  success BOOLEAN NOT NULL,
  response_time_ms INT,
  tokens_input INT,
  tokens_output INT,
  cost DECIMAL(10,6),
  
  -- Quality metrics
  confidence_score DECIMAL(3,2), -- 0.00-1.00
  quality_score DECIMAL(3,2),
  safety_score DECIMAL(3,2),
  requires_review BOOLEAN DEFAULT FALSE,
  
  -- Error handling
  error_type VARCHAR(100),
  error_message TEXT,
  retry_count INT DEFAULT 0,
  fallback_used BOOLEAN DEFAULT FALSE,
  
  -- Metadata
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  
  FOREIGN KEY (account_id) REFERENCES accounts(id) ON DELETE CASCADE,
  FOREIGN KEY (processed_email_id) REFERENCES processed_emails(id) ON DELETE SET NULL,
  FOREIGN KEY (provider_id) REFERENCES llm_providers(id),
  
  INDEX idx_account_requests (account_id, created_at),
  INDEX idx_provider_performance (provider_id, success, created_at),
  INDEX idx_endpoint_metrics (endpoint, success, response_time_ms),
  INDEX idx_cost_analysis (provider_id, cost, created_at),
  INDEX idx_quality_tracking (quality_score, confidence_score, created_at)
);
```

### llm_rate_limits
```sql
CREATE TABLE llm_rate_limits (
  id VARCHAR(36) PRIMARY KEY,
  provider_id VARCHAR(50) NOT NULL,
  
  -- Time window
  window_start TIMESTAMP NOT NULL,
  window_type ENUM('minute', 'hour', 'day') NOT NULL,
  
  -- Counters
  requests_count INT DEFAULT 0,
  tokens_count INT DEFAULT 0,
  
  -- Limits
  requests_limit INT NOT NULL,
  tokens_limit INT,
  
  -- Status
  limit_exceeded BOOLEAN DEFAULT FALSE,
  reset_at TIMESTAMP,
  
  created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
  
  FOREIGN KEY (provider_id) REFERENCES llm_providers(id) ON DELETE CASCADE,
  
  UNIQUE KEY unique_provider_window (provider_id, window_start, window_type),
  INDEX idx_rate_monitoring (provider_id, window_type, limit_exceeded)
);
```

## Test Checklist

### Provider Integration Tests
- **Authentication**: Valid API keys work, invalid keys fail gracefully
- **Model Availability**: All configured models are accessible
- **Rate Limiting**: Respect provider rate limits and handle 429 errors
- **Request Format**: Proper transformation between internal and provider formats
- **Response Parsing**: Correctly parse and validate provider responses

### Safety & Security Tests
- **Content Filtering**: Blocked content is properly filtered
- **PII Redaction**: Personal information is redacted in logs
- **Prompt Injection**: Malicious prompts are detected and blocked
- **Output Validation**: Generated content meets safety requirements
- **Access Control**: Only authorized requests are processed

### Error Handling Tests
- **Provider Failures**: Graceful handling of provider outages
- **Timeout Handling**: Requests timeout appropriately
- **Retry Logic**: Exponential backoff and retry limits work
- **Fallback Chain**: Provider fallback works correctly
- **Circuit Breaker**: Circuit breakers open and close properly

### Performance Tests
- **Response Time**: Requests complete within SLA (< 5 seconds)
- **Throughput**: Handle expected request volume
- **Memory Usage**: Stable memory under load
- **Cost Optimization**: Token usage is optimized
- **Concurrent Requests**: Thread-safe operation

### Quality Assurance Tests
- **Classification Accuracy**: High accuracy on test dataset
- **Generation Quality**: Generated responses meet quality standards
- **Consistency**: Same input produces consistent results
- **Confidence Calibration**: Confidence scores correlate with accuracy
- **Human Agreement**: High agreement with human evaluators

---

## Pragmatic Review

### ✅ Interface Design
- Clean REST API with comprehensive request/response schemas
- Unified interface abstracts provider differences
- Flexible configuration options for different use cases
- Proper error handling and status reporting

### ✅ Provider Abstraction
- Adapter pattern allows easy addition of new providers
- Proper request/response transformation
- Provider-specific optimizations and configurations
- Fallback chain for high availability

### ✅ Safety Implementation
- Multi-level safety controls (strict/moderate/permissive)
- Content filtering and PII protection
- Safety score calculation and review triggers
- Comprehensive safety violation logging

### ✅ Error Taxonomy
- Detailed error classification and mapping
- Appropriate retry strategies for different error types
- Circuit breaker pattern for provider failures
- Graceful degradation and fallback mechanisms

### ✅ Observability
- Comprehensive metrics collection
- Structured logging with proper redaction
- Performance and quality tracking
- Cost monitoring and optimization

### ✅ Data Schema
- Proper normalization and relationships
- Performance indexes for common queries
- Audit trail and compliance support
- Rate limiting and quota management

### Potential Issues Identified:
1. **Token Estimation**: Accurate token counting before requests
2. **Cost Control**: Real-time cost monitoring and limits
3. **Model Versioning**: Handling provider model updates
4. **Cache Strategy**: Caching classification results

### Recommended Fixes:
1. Implement tiktoken-based token estimation
2. Add real-time cost tracking with alerts
3. Version model configurations and handle deprecation
4. Add Redis-based caching for repeated classifications