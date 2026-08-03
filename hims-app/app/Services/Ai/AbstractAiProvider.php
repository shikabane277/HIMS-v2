<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use Illuminate\Support\Str;

/**
 * Shared behaviour for every concrete AI driver.
 *
 * Each driver is constructed with a resolved config array (api_key, model,
 * fallback models, base_url, temperature, max_tokens, timeout) by AiManager,
 * and only has to implement ask(). This base supplies the HIMS system prompt,
 * config accessors, the "⚠️"-prefixed failure strings mandated by the
 * AiProvider contract, and the JSON helper methods that used to live on
 * GeminiService (now provider-agnostic).
 */
abstract class AbstractAiProvider implements AiProvider
{
    /** @param array<string, mixed> $config */
    public function __construct(protected array $config = []) {}

    /** Short human label used in error messages, e.g. "Gemini", "OpenAI". */
    abstract protected function label(): string;

    /**
     * The HIMS system prompt prepended to (or sent alongside) every user
     * prompt. Kept identical across drivers so answers stay consistent
     * whichever model is configured.
     */
    protected function systemContext(): string
    {
        return 'You are an AI assistant for a Hospital Information Management System (HIMS) '
            .'in the Philippines. You help HR officers with performance reviews, competency gaps, '
            .'succession planning, training schedules, learning pathways, and employee recognition. '
            .'Reply in English by default. Only switch to Tagalog or Taglish when the user clearly '
            .'writes to you in Tagalog or Taglish, and then match their language. Be concise and helpful. '
            .'Always be professional and sensitive to healthcare context.';
    }

    protected function apiKey(): string
    {
        return trim((string) ($this->config['api_key'] ?? ''));
    }

    protected function model(): string
    {
        return trim((string) ($this->config['model'] ?? ''));
    }

    /**
     * Models to try in order. The configured model is first; any driver
     * defaults are appended so a stale/unavailable model can fall back.
     *
     * @return array<int, string>
     */
    protected function models(): array
    {
        $configured = $this->model();
        $fallbacks = array_map('strval', (array) ($this->config['fallback_models'] ?? []));

        $all = array_values(array_unique(array_filter(
            array_merge($configured !== '' ? [$configured] : [], $fallbacks)
        )));

        return $all;
    }

    protected function temperature(): float
    {
        return (float) ($this->config['temperature'] ?? 0.7);
    }

    protected function maxTokens(): int
    {
        return (int) ($this->config['max_tokens'] ?? 1024);
    }

    protected function timeout(): int
    {
        return (int) ($this->config['timeout'] ?? 30);
    }

    /** Standard "no key" failure string (honours the ⚠️ contract). */
    protected function missingKey(): string
    {
        return '⚠️ '.$this->label().' API key not configured. '
            .'Please add the relevant key to your .env file (see AI_PROVIDER settings).';
    }

    /* ───────── provider-agnostic JSON helpers (moved off GeminiService) ───────── */

    /**
     * Check performance review text for potential unconscious bias.
     *
     * @return array<string, mixed>
     */
    public function checkBias(string $reviewText): array
    {
        $prompt = 'Analyze this performance review text for potential unconscious bias (gender, age, nationality, etc). '
            .'Return a JSON object with: {has_bias: bool, flags: [{type, excerpt, suggestion}], confidence: 0-1}. '
            .'Review text: '.$reviewText;

        return $this->decodeJson($this->ask($prompt)) ?? ['has_bias' => false, 'flags' => []];
    }

    /**
     * Generate multiple-choice quiz questions from course content.
     *
     * @return array<int, mixed>
     */
    public function generateQuizQuestions(string $content, int $count = 5): array
    {
        $prompt = "Generate {$count} multiple-choice quiz questions based on this content. "
            .'Return JSON array: [{question_text, options: [4 strings], correct_answer, explanation}]. '
            .'Content: '.Str::limit($content, 2000);

        $decoded = $this->decodeJson($this->ask($prompt));

        return is_array($decoded) ? $decoded : [];
    }

    /**
     * Analyse training-feedback sentiment.
     *
     * @return array<string, mixed>
     */
    public function analyzeSentiment(string $feedbackText): array
    {
        $prompt = 'Analyze the sentiment of this training feedback. '
            ."Return JSON: {label: 'positive'|'neutral'|'negative', score: 0-1, summary: string}. "
            .'Feedback: '.$feedbackText;

        return $this->decodeJson($this->ask($prompt))
            ?? ['label' => 'neutral', 'score' => 0.5];
    }

    /**
     * Strip markdown fences and decode a JSON reply. Returns null if the reply
     * was a "⚠️" failure string or could not be parsed.
     *
     * @return array<mixed>|null
     */
    protected function decodeJson(string $response): ?array
    {
        if ($response === '' || str_starts_with(trim($response), '⚠️')) {
            return null;
        }

        $decoded = json_decode(preg_replace('/```(?:json)?|```/', '', $response) ?? '', true);

        return is_array($decoded) ? $decoded : null;
    }
}
