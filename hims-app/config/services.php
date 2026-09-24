<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Third Party Services
    |--------------------------------------------------------------------------
    |
    | This file is for storing the credentials for third party services such
    | as Mailgun, Postmark, AWS and more. This file provides the de facto
    | location for this type of information, allowing packages to have
    | a conventional file to locate the various service credentials.
    |
    */

    'postmark' => [
        'key' => env('POSTMARK_API_KEY'),
    ],

    'brevo' => [
        'key' => env('BREVO_API_KEY'),
    ],

    'ses' => [
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION', 'us-east-1'),
    ],

    'slack' => [
        'notifications' => [
            'bot_user_oauth_token' => env('SLACK_BOT_USER_OAUTH_TOKEN'),
            'channel' => env('SLACK_BOT_USER_DEFAULT_CHANNEL'),
        ],
    ],

    /*
    |--------------------------------------------------------------------------
    | AI providers
    |--------------------------------------------------------------------------
    | Provider-agnostic AI config consumed by App\Services\Ai\AiManager, which
    | resolves the App\Contracts\AiProvider used by AiController and the
    | competency gap analysis. Set AI_PROVIDER to pick the active provider;
    | Gemini stays the default so the existing GEMINI_API_KEY keeps working.
    |
    | Each provider only activates once its API key is set — an unset key makes
    | ask() return a "⚠️" notice rather than erroring.
    |
    | Conversation memory: the chat sidebar replays earlier turns of the current
    | session so follow-up questions make sense. AI_HISTORY_TURNS / _CHARS bound
    | how much is replayed — max_tokens only caps generation, so without these
    | the request would grow with the conversation until the provider rejected
    | it. Oldest turns are dropped first. Set AI_HISTORY_TURNS=0 for no memory.
    */
    'ai' => [
        'default' => env('AI_PROVIDER', 'gemini'),
        'temperature' => (float) env('AI_TEMPERATURE', 0.7),
        'max_tokens' => (int) env('AI_MAX_TOKENS', 2048),
        'timeout' => (int) env('AI_TIMEOUT', 30),
        'history_turns' => (int) env('AI_HISTORY_TURNS', 20),
        'history_chars' => (int) env('AI_HISTORY_CHARS', 12000),

        'providers' => [

            'gemini' => [
                'driver' => 'gemini',
                'api_key' => env('GEMINI_API_KEY', ''),
                'model' => env('GEMINI_MODEL', 'gemini-2.5-flash'),
                'fallback_models' => ['gemini-2.0-flash', 'gemini-1.5-flash-latest', 'gemini-pro'],
                'base_url' => env('GEMINI_BASE_URL', 'https://generativelanguage.googleapis.com/v1beta'),
            ],

            'openai' => [
                'driver' => 'openai',
                'api_key' => env('OPENAI_API_KEY', ''),
                'model' => env('OPENAI_MODEL', 'gpt-4o-mini'),
                'fallback_models' => ['gpt-4o', 'gpt-3.5-turbo'],
                'base_url' => env('OPENAI_BASE_URL', 'https://api.openai.com/v1'),
            ],

            'anthropic' => [
                'driver' => 'anthropic',
                'api_key' => env('ANTHROPIC_API_KEY', ''),
                'model' => env('ANTHROPIC_MODEL', 'claude-opus-5'),
                'fallback_models' => ['claude-opus-4-8', 'claude-sonnet-5', 'claude-sonnet-4-6'],
                'base_url' => env('ANTHROPIC_BASE_URL', 'https://api.anthropic.com'),
                'anthropic_version' => env('ANTHROPIC_VERSION', '2023-06-01'),
            ],

            'compatible' => [
                'driver' => 'compatible',
                'label' => env('AI_COMPATIBLE_LABEL', 'AI'),
                'api_key' => env('AI_COMPATIBLE_API_KEY', ''),
                'model' => env('AI_COMPATIBLE_MODEL', 'openai/gpt-oss-120b'),
                'fallback_models' => array_values(array_unique(array_filter(array_merge(
                    array_map('trim', explode(',', (string) env('AI_COMPATIBLE_FALLBACK_MODELS', ''))),
                    ['openai/gpt-oss-120b', 'openai/gpt-oss-20b', 'qwen/qwen3.8-27b', 'llama-3.3-70b-versatile', 'llama-3.1-8b-instant']
                ), fn (string $m): bool => $m !== ''))),
                'base_url' => env('AI_COMPATIBLE_BASE_URL', ''),
            ],
        ],
    ],

    'zapier' => [
        'performance_review_approved' => env('ZAPIER_WEBHOOK_REVIEW_APPROVED', ''),
        'credential_expired' => env('ZAPIER_WEBHOOK_CREDENTIAL_EXPIRED', ''),
        'pip_initiated' => env('ZAPIER_WEBHOOK_PIP_INITIATED', ''),
        'training_registration' => env('ZAPIER_WEBHOOK_TRAINING_REG', ''),
        'certificate_issued' => env('ZAPIER_WEBHOOK_CERT_ISSUED', ''),
    ],

];
