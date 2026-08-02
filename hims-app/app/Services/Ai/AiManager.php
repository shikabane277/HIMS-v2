<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use Illuminate\Support\Facades\Log;
use InvalidArgumentException;

/**
 * Resolves the active AI driver from config('services.ai').
 *
 * config shape:
 *   'default'   => 'gemini'|'openai'|'anthropic'|'compatible'
 *   'providers' => ['gemini' => [...], 'openai' => [...], ...]
 *
 * Each provider config carries: driver, api_key, model, fallback_models,
 * base_url, plus the shared temperature/max_tokens/timeout. AiManager is bound
 * as a singleton and also resolves the AiProvider contract to the default
 * driver (see AppServiceProvider).
 *
 * Misconfiguration never breaks a page. A bad AI_PROVIDER value resolves to a
 * NullAiProvider that returns the usual "⚠️" string, matching the contract in
 * App\Contracts\AiProvider: ask() reports problems, it does not throw. Asking
 * for a bad provider *by name* still throws, since that is a code bug.
 */
class AiManager
{
    /** @var array<string, AiProvider> */
    private array $resolved = [];

    /** @param array<string, mixed> $config the whole config('services.ai') array */
    public function __construct(private array $config) {}

    /** The configured default driver, ready to use. */
    public function provider(?string $name = null): AiProvider
    {
        // An explicit name is a code path: let a typo surface as an exception.
        if ($name !== null) {
            $key = $this->normalise($name);

            return $this->resolved[$key] ??= $this->make($key);
        }

        $default = $this->normalise((string) ($this->config['default'] ?? 'gemini'));

        // The default comes from AI_PROVIDER in .env — user input, effectively.
        // A bad value must degrade, not 500 every page that touches AI.
        try {
            return $this->resolved[$default] ??= $this->make($default);
        } catch (InvalidArgumentException $e) {
            Log::error('Invalid AI_PROVIDER configured', [
                'provider'  => $default,
                'available' => array_keys((array) ($this->config['providers'] ?? [])),
            ]);

            return $this->resolved[$default] = new NullAiProvider($default, array_keys(
                (array) ($this->config['providers'] ?? [])
            ));
        }
    }

    /**
     * Provider names are written by hand in .env, so "Gemini", " gemini", and
     * "GEMINI" all have to resolve to the 'gemini' key. Also maps the common
     * vendor spellings people reach for instead of the config key.
     */
    private function normalise(string $name): string
    {
        $name = strtolower(trim($name));

        return match ($name) {
            'google', 'google-gemini', 'googleai' => 'gemini',
            'claude', 'claude-ai'                 => 'anthropic',
            'gpt', 'chatgpt', 'open-ai'           => 'openai',
            'openai-compatible', 'custom'         => 'compatible',
            default                               => $name,
        };
    }

    /** Build a driver instance from its provider config. */
    private function make(string $name): AiProvider
    {
        $providers = (array) ($this->config['providers'] ?? []);

        if (! isset($providers[$name])) {
            throw new InvalidArgumentException("AI provider [{$name}] is not configured in services.ai.providers.");
        }

        $conf   = (array) $providers[$name];
        $driver = (string) ($conf['driver'] ?? $name);

        // Fold the shared defaults under each provider's own settings.
        $conf += [
            'temperature' => $this->config['temperature'] ?? 0.7,
            'max_tokens'  => $this->config['max_tokens'] ?? 1024,
            'timeout'     => $this->config['timeout'] ?? 30,
        ];

        return match ($driver) {
            'gemini'     => new GeminiProvider($conf),
            'anthropic'  => new AnthropicProvider($conf),
            'openai'     => new OpenAiProvider($conf, 'OpenAI'),
            // Any OpenAI-compatible host (Groq, DeepSeek, xAI, Mistral, …).
            'compatible' => new OpenAiProvider($conf, (string) ($conf['label'] ?? 'AI')),
            default      => throw new InvalidArgumentException("Unknown AI driver [{$driver}] for provider [{$name}]."),
        };
    }
}
