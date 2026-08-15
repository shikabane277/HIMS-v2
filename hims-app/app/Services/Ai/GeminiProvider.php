<?php

namespace App\Services\Ai;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Google Gemini driver (generativelanguage REST API).
 *
 * Talks to the v1beta generateContent endpoint over raw HTTP, matching the
 * pattern the app already used. The API key travels as a ?key= query param.
 * Tries each configured/fallback model in turn, advancing on a 404 (model not
 * available for this key) and returning a "⚠️" string on any other failure.
 */
class GeminiProvider extends AbstractAiProvider
{
    protected function label(): string
    {
        return 'Gemini';
    }

    public function ask(string $prompt, array $history = [], ?string $scope = null): string
    {
        if ($this->apiKey() === '') {
            return $this->missingKey();
        }

        $base = rtrim((string) ($this->config['base_url']
            ?? 'https://generativelanguage.googleapis.com/v1beta'), '/');

        $contents = $this->buildContents($prompt, $history, $scope);

        foreach ($this->models() as $model) {
            try {
                $endpoint = "{$base}/models/{$model}:generateContent";

                $response = Http::timeout($this->timeout())->post("{$endpoint}?key={$this->apiKey()}", [
                    'contents' => $contents,
                    'generationConfig' => [
                        'temperature' => $this->temperature(),
                        'maxOutputTokens' => $this->maxTokens(),
                    ],
                ]);

                if ($response->successful()) {
                    return $response->json('candidates.0.content.parts.0.text')
                        ?? 'No response generated.';
                }

                if ($response->status() === 404) {
                    continue; // model unavailable for this key — try the next
                }

                $msg = $response->json('error.message') ?? $response->body();
                Log::warning('Gemini API error', ['status' => $response->status(), 'body' => $response->body()]);

                return "⚠️ Gemini API Error ({$response->status()}): {$msg}";
            } catch (\Throwable $e) {
                Log::error('Gemini request failed', ['error' => $e->getMessage()]);

                return '⚠️ AI service error: '.$e->getMessage();
            }
        }

        return '⚠️ Unable to reach Gemini with the configured models. Check GEMINI_MODEL / GEMINI_API_KEY in .env.';
    }

    /**
     * Build the Gemini contents array from the prompt and prior conversation.
     *
     * Gemini's roles are 'user' and 'model'. systemContext() is prepended to
     * the current user turn rather than using system_instruction, because that
     * field is not supported by older models (gemini-1.0-pro, gemini-1.5-flash).
     *
     * @param  list<array{role?: string, message?: string}>|array<mixed>  $history
     * @return list<array{role: string, parts: list<array{text: string}>}>
     */
    private function buildContents(string $prompt, array $history, ?string $scope = null): array
    {
        $turns = $this->sanitiseHistory($history);

        $contents = [];

        foreach ($turns as $turn) {
            $contents[] = [
                'role' => $turn['role'] === 'user' ? 'user' : 'model',
                'parts' => [['text' => $turn['text']]],
            ];
        }

        // System prompt is glued to the current question, not sent separately.
        $contents[] = [
            'role' => 'user',
            'parts' => [['text' => $this->systemContext($scope)."\n\nUser question: ".$prompt]],
        ];

        return $contents;
    }
}
