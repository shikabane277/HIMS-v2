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
     *
     * Carries HimsKnowledge::appGuide() — a map of the real navigation and,
     * more usefully, of the real limits. Without it the model answers "how do I
     * do X in HIMS" from generic LMS conventions and invents pages and fields
     * that were never built; see that class for the case that prompted it.
     *
     * $scope is the caller's role-based access instruction from
     * AiAccessPolicy::scopeFor(), appended when present. It arrives per call
     * because this driver is a shared singleton — see AiProvider::ask().
     */
    protected function systemContext(?string $scope = null): string
    {
        $base = 'You are an AI assistant for a Hospital Information Management System (HIMS) '
            .'in the Philippines. You help HR officers with performance reviews, competency gaps, '
            .'succession planning, training schedules, and learning pathways. '
            .'Reply in English by default. Only switch to Tagalog or Taglish when the user clearly '
            .'writes to you in Tagalog or Taglish, and then match their language. Be concise and helpful. '
            .'Always be professional and sensitive to healthcare context.'
            ."\n\n".HimsKnowledge::appGuide();

        return $scope === null || trim($scope) === ''
            ? $base
            : $base."\n\n".trim($scope);
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

    /** Most recent turns to replay. Caps the request size; oldest are dropped. */
    protected function historyTurns(): int
    {
        return max(0, (int) ($this->config['history_turns'] ?? 20));
    }

    /** Character ceiling across all replayed turns. A crude but hard budget. */
    protected function historyChars(): int
    {
        return max(0, (int) ($this->config['history_chars'] ?? 12000));
    }

    /**
     * Normalise stored chat rows into turns that are safe to send to a model.
     *
     * The stored history cannot be forwarded as-is. Four things have to happen:
     *
     * 1. Replies HIMS wrote itself are dropped, along with the question each one
     *    answered. AiController persists whatever came back, and that is not
     *    always model output: on an API/config error it is a "⚠️" string from
     *    this class, and on a blocked question it is a "🔒" refusal from
     *    AiAccessPolicy. Replaying either would teach the model that its own
     *    voice says "⚠️ API key not configured" or "🔒 I can't help with that",
     *    and it would keep saying so for the life of the conversation. The
     *    question goes with it because an exchange that never happened should
     *    not leave half of itself behind.
     * 2. Roles are forced to alternate user → ai → user → ai. Anthropic rejects
     *    two consecutive turns of the same role outright. Where a duplicate does
     *    occur, the OLDER turn is the one dropped: the answer that follows
     *    belongs to the most recent question, so keeping the newer question is
     *    what preserves the pairing.
     * 3. A trailing unanswered question is dropped, because the caller appends
     *    the current prompt as the final user turn.
     * 4. The result is capped — by turn count and by total characters, oldest
     *    first. Nothing else in the stack bounds the request: max_tokens limits
     *    generation only, so an unbounded transcript would grow until the
     *    provider rejected it.
     *
     * @param  list<array{role?: string, message?: string}>|array<mixed>  $history
     * @return list<array{role: 'user'|'ai', text: string}>
     */
    protected function sanitiseHistory(array $history): array
    {
        $turns = [];

        foreach ($history as $entry) {
            $entry = is_object($entry) ? (array) $entry : $entry;

            if (! is_array($entry)) {
                continue;
            }

            $role = ($entry['role'] ?? '') === 'user' ? 'user' : 'ai';
            $text = trim((string) ($entry['message'] ?? ''));

            if ($text === '') {
                continue;
            }

            $lastRole = $turns === [] ? null : $turns[count($turns) - 1]['role'];

            if ($role === 'ai') {
                // A reply this app generated rather than the model — take the
                // question it stood in for out with it.
                if ($this->isSyntheticReply($text)) {
                    if ($lastRole === 'user') {
                        array_pop($turns);
                    }

                    continue;
                }

                // An answer cannot open the list or follow another answer.
                if ($lastRole !== 'user') {
                    continue;
                }
            } elseif ($lastRole === 'user') {
                // Two questions running: the previous one never got an answer,
                // so drop it rather than let it absorb this one's reply.
                array_pop($turns);
            }

            $turns[] = ['role' => $role, 'text' => $text];
        }

        // The caller supplies the current question, so the replay must end on a
        // completed exchange.
        if ($turns !== [] && $turns[count($turns) - 1]['role'] === 'user') {
            array_pop($turns);
        }

        return $this->capHistory($turns);
    }

    /**
     * True for a stored reply that HIMS wrote rather than a model: provider
     * failures and RBAC refusals. Both are real parts of the transcript the user
     * sees, and neither belongs in what the model is told it said.
     */
    private function isSyntheticReply(string $text): bool
    {
        return str_starts_with($text, '⚠️')
            || str_starts_with($text, AiAccessPolicy::REFUSAL_PREFIX);
    }

    /**
     * Trim to the configured budget from the oldest end, then re-drop a leading
     * 'ai' turn — cutting mid-exchange can leave the list starting on an answer,
     * which Anthropic will not accept.
     *
     * @param  list<array{role: 'user'|'ai', text: string}>  $turns
     * @return list<array{role: 'user'|'ai', text: string}>
     */
    private function capHistory(array $turns): array
    {
        if (count($turns) > $this->historyTurns()) {
            $turns = array_slice($turns, -$this->historyTurns());
        }

        $budget = $this->historyChars();
        $total = array_sum(array_map(fn (array $t): int => mb_strlen($t['text']), $turns));

        while ($turns !== [] && $total > $budget) {
            $dropped = array_shift($turns);
            $total -= mb_strlen($dropped['text']);
        }

        while ($turns !== [] && $turns[0]['role'] === 'ai') {
            array_shift($turns);
        }

        return array_values($turns);
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
