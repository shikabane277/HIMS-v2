<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * OpenAI Chat Completions driver.
 *
 * This one driver serves both OpenAI itself and any OpenAI-compatible host
 * (Groq, DeepSeek, xAI/Grok, Mistral, Together, OpenRouter, Ollama) — they all
 * expose the same POST {base_url}/chat/completions shape with a Bearer key.
 * AiManager passes a distinct label + base_url per configured provider.
 */
class OpenAiProvider extends AbstractAiProvider
{
    public function __construct(array $config = [], private string $label = 'OpenAI')
    {
        parent::__construct($config);
    }

    protected function label(): string
    {
        return $this->label;
    }

    public function ask(string $prompt): string
    {
        if ($this->apiKey() === '') {
            return $this->missingKey();
        }

        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/');

        foreach ($this->models() as $model) {
            try {
                $response = Http::timeout($this->timeout())
                    ->withToken($this->apiKey())
                    ->post("{$base}/chat/completions", [
                        'model'    => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $this->systemContext()],
                            ['role' => 'user',   'content' => $prompt],
                        ],
                        'temperature' => $this->temperature(),
                        'max_tokens'  => $this->maxTokens(),
                    ]);

                if ($response->successful()) {
                    return $response->json('choices.0.message.content')
                        ?? 'No response generated.';
                }

                // 404 (unknown model) or 400 with a model hint — try the next model.
                if (in_array($response->status(), [400, 404], true) && $this->looksLikeModelError($response->json())) {
                    continue;
                }

                $msg = $response->json('error.message') ?? $response->body();
                Log::warning($this->label() . ' API error', ['status' => $response->status(), 'body' => $response->body()]);

                return "⚠️ {$this->label()} API Error ({$response->status()}): {$msg}";
            } catch (\Throwable $e) {
                Log::error($this->label() . ' request failed', ['error' => $e->getMessage()]);

                return '⚠️ AI service error: ' . $e->getMessage();
            }
        }

        return "⚠️ Unable to reach {$this->label()} with the configured models. Check the model/API key in .env.";
    }

    /** Heuristic: does this error body point at the model name rather than auth/quota? */
    private function looksLikeModelError(mixed $body): bool
    {
        $message = is_array($body) ? ($body['error']['message'] ?? '') : '';
        $code    = is_array($body) ? ($body['error']['code'] ?? '') : '';

        return str_contains(strtolower((string) $message), 'model')
            || in_array($code, ['model_not_found', 'invalid_model'], true);
    }
}
