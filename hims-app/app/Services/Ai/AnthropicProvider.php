<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Anthropic (Claude) driver — Messages API.
 *
 * POST /v1/messages with x-api-key + anthropic-version headers. The system
 * prompt is a top-level "system" field (not a message), and the reply text is
 * the first content block. Model IDs are supplied from config, e.g.
 * claude-opus-5 (default), claude-opus-4-8, claude-sonnet-5, claude-sonnet-4-6.
 */
class AnthropicProvider extends AbstractAiProvider
{
    protected function label(): string
    {
        return 'Anthropic';
    }

    public function ask(string $prompt): string
    {
        if ($this->apiKey() === '') {
            return $this->missingKey();
        }

        $base    = rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com'), '/');
        $version = (string) ($this->config['anthropic_version'] ?? '2023-06-01');

        foreach ($this->models() as $model) {
            try {
                $response = Http::timeout($this->timeout())
                    ->withHeaders([
                        'x-api-key'         => $this->apiKey(),
                        'anthropic-version' => $version,
                    ])
                    ->post("{$base}/v1/messages", [
                        'model'      => $model,
                        'max_tokens' => $this->maxTokens(),
                        'system'     => $this->systemContext(),
                        'messages'   => [
                            ['role' => 'user', 'content' => $prompt],
                        ],
                        'temperature' => $this->temperature(),
                    ]);

                if ($response->successful()) {
                    return $response->json('content.0.text')
                        ?? 'No response generated.';
                }

                // 404 (unknown model) — try the next configured model.
                if ($response->status() === 404) {
                    continue;
                }

                $msg = $response->json('error.message') ?? $response->body();
                Log::warning('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);

                return "⚠️ Anthropic API Error ({$response->status()}): {$msg}";
            } catch (\Throwable $e) {
                Log::error('Anthropic request failed', ['error' => $e->getMessage()]);

                return '⚠️ AI service error: ' . $e->getMessage();
            }
        }

        return '⚠️ Unable to reach Anthropic with the configured models. Check ANTHROPIC_MODEL / ANTHROPIC_API_KEY in .env.';
    }
}
