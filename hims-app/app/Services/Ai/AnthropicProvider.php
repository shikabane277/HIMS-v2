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

    public function ask(string $prompt, array $history = [], ?string $scope = null): string
    {
        if ($this->apiKey() === '') {
            return $this->missingKey();
        }

        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.anthropic.com'), '/');
        $version = (string) ($this->config['anthropic_version'] ?? '2023-06-01');

        $messages = $this->buildMessages($prompt, $history);

        /** @var array<string, string> $rejected model name => the host's reason */
        $rejected = [];

        foreach ($this->models() as $model) {
            try {
                $response = Http::timeout($this->timeout())
                    ->withHeaders([
                        'x-api-key' => $this->apiKey(),
                        'anthropic-version' => $version,
                    ])
                    ->post("{$base}/v1/messages", [
                        'model' => $model,
                        'max_tokens' => $this->maxTokens(),
                        'system' => $this->systemContext($scope),
                        'messages' => $messages,
                        'temperature' => $this->temperature(),
                    ]);

                if ($response->successful()) {
                    return $response->json('content.0.text')
                        ?? 'No response generated.';
                }

                if (in_array($response->status(), [401, 403], true)) {
                    $msg = trim((string) ($response->json('error.message') ?: $response->body()));
                    Log::warning('Anthropic API error', ['status' => $response->status(), 'body' => $response->body()]);

                    return "⚠️ Anthropic API Error ({$response->status()}): {$msg}";
                }

                $msg = trim((string) ($response->json('error.message') ?: $response->body()));
                $rejected[$model] = "HTTP {$response->status()}: {$msg}";
                Log::warning('Anthropic rejected/failed model', ['model' => $model, 'status' => $response->status(), 'body' => $response->body()]);

                continue;
            } catch (\Throwable $e) {
                Log::error('Anthropic request failed', ['model' => $model, 'error' => $e->getMessage()]);
                $rejected[$model] = 'Error: '.$e->getMessage();

                continue;
            }
        }

        return $this->noUsableModel($rejected);
    }

    protected function modelEnvKeys(): string
    {
        return 'ANTHROPIC_MODEL';
    }

    /**
     * Build the Messages API array. The system prompt is not a message here, so
     * the list is prior turns plus the current question. sanitiseHistory()
     * already guarantees the two constraints this API enforces: the list starts
     * on 'user' and roles strictly alternate.
     *
     * @param  list<array{role?: string, message?: string}>|array<mixed>  $history
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(string $prompt, array $history): array
    {
        $messages = [];

        foreach ($this->sanitiseHistory($history) as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'assistant',
                'content' => $turn['text'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $messages;
    }
}
