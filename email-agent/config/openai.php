<?php

return [
    /*
    |--------------------------------------------------------------------------
    | OpenAI API Key
    |--------------------------------------------------------------------------
    |
    | Here you may specify your OpenAI API key. This will be used to
    | authenticate with the OpenAI API for various AI operations.
    |
    */
    'api_key' => env('OPENAI_API_KEY'),

    /*
    |--------------------------------------------------------------------------
    | Default Model Settings
    |--------------------------------------------------------------------------
    |
    | Configure default models and parameters for different operations.
    |
    */
    'models' => [
        'classification' => [
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.3,
            'max_tokens' => 500,
        ],
        'response_generation' => [
            'model' => 'gpt-4',
            'temperature' => 0.7,
            'max_tokens' => 1000,
        ],
        'sentiment_analysis' => [
            'model' => 'gpt-3.5-turbo',
            'temperature' => 0.2,
            'max_tokens' => 300,
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | Rate Limiting
    |--------------------------------------------------------------------------
    |
    | Configure rate limiting for OpenAI API requests.
    |
    */
    'rate_limit' => [
        'requests_per_minute' => 60,
        'tokens_per_minute' => 90000,
    ],

    /*
    |--------------------------------------------------------------------------
    | Cost Tracking
    |--------------------------------------------------------------------------
    |
    | Pricing information for cost estimation (per 1K tokens).
    |
    */
    'pricing' => [
        'gpt-3.5-turbo' => 0.002,
        'gpt-4' => 0.03,
        'gpt-4-turbo' => 0.01,
    ],
];