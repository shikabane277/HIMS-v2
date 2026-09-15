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
    /**
     * @param  string  $label  host name for error messages ("OpenAI", "Groq", …)
     * @param  string  $envKeys  the .env keys this slot reads its model from —
     *                           supplied by AiManager, which is the only place
     *                           that knows whether this instance is the `openai`
     *                           slot or the `compatible` one. Guessing from the
     *                           label would be wrong the moment someone sets
     *                           AI_COMPATIBLE_LABEL=OpenAI.
     */
    public function __construct(
        array $config = [],
        private string $label = 'OpenAI',
        private string $envKeys = 'OPENAI_MODEL',
    ) {
        parent::__construct($config);
    }

    protected function label(): string
    {
        return $this->label;
    }

    protected function modelEnvKeys(): string
    {
        return $this->envKeys;
    }

    public function ask(string $prompt, array $history = [], ?string $scope = null): string
    {
        if ($this->apiKey() === '') {
            return $this->missingKey();
        }

        $base = rtrim((string) ($this->config['base_url'] ?? 'https://api.openai.com/v1'), '/');

        $messages = $this->buildMessages($prompt, $history, $scope);

        // Why the reason is carried out of the loop: a rejected model is the one
        // failure this driver diagnoses and then used to throw the diagnosis
        // away. Groq retiring llama-3.3-70b-versatile produced "Check the
        // model/API key in .env" — two suspects, one of them working fine, and
        // no way to tell from the reply which. The host had already said
        // `model_not_found` and named the model.
        /** @var array<string, string> $rejected model name => the host's reason */
        $rejected = [];

        foreach ($this->models() as $model) {
            try {
                $response = Http::timeout($this->timeout())
                    ->withToken($this->apiKey())
                    ->post("{$base}/chat/completions", [
                        'model' => $model,
                        'messages' => $messages,
                        'temperature' => $this->temperature(),
                        'max_tokens' => $this->maxTokens(),
                    ]);

                if ($response->successful()) {
                    return $response->json('choices.0.message.content')
                        ?? 'No response generated.';
                }

                // 404 (unknown model) or 400 with a model hint — try the next model.
                if (in_array($response->status(), [400, 404], true) && $this->looksLikeModelError($response->json())) {
                    $rejected[$model] = trim((string) ($response->json('error.message') ?: 'rejected as unknown'));
                    Log::warning($this->label().' rejected model', ['model' => $model, 'status' => $response->status()]);

                    continue;
                }

                $msg = $response->json('error.message') ?? $response->body();
                Log::warning($this->label().' API error', ['status' => $response->status(), 'body' => $response->body()]);

                return "⚠️ {$this->label()} API Error ({$response->status()}): {$msg}";
            } catch (\Throwable $e) {
                Log::error($this->label().' request failed', ['error' => $e->getMessage()]);

                return '⚠️ AI service error: '.$e->getMessage();
            }
        }

        return $this->noUsableModel($rejected);
    }

    /**
     * Build the chat-completions messages array.
     *
     * @param  list<array{role?: string, message?: string}>|array<mixed>  $history
     * @return list<array{role: string, content: string}>
     */
    private function buildMessages(string $prompt, array $history, ?string $scope = null): array
    {
        $messages = [['role' => 'system', 'content' => $this->systemContext($scope)]];

        foreach ($this->sanitiseHistory($history) as $turn) {
            $messages[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'assistant',
                'content' => $turn['text'],
            ];
        }

        $messages[] = ['role' => 'user', 'content' => $prompt];

        return $messages;
    }

    /** Heuristic: does this error body point at the model name rather than auth/quota? */
    private function looksLikeModelError(mixed $body): bool
    {
        $message = is_array($body) ? ($body['error']['message'] ?? '') : '';
        $code = is_array($body) ? ($body['error']['code'] ?? '') : '';

        return str_contains(strtolower((string) $message), 'model')
            || in_array($code, ['model_not_found', 'invalid_model'], true);
    }
}
