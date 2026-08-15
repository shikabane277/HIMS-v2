<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use App\Models\User;

/**
 * Decides whether a chat message is a command, and if so which action it maps
 * to and with what arguments.
 *
 * WHY A SEPARATE CLASSIFIER PASS RATHER THAN TOOL CALLING
 *
 * The AiProvider contract is `ask(string, array, ?string): string` — plain text
 * in, plain text out — and four drivers implement it (Gemini, OpenAI,
 * Anthropic, Null). Native tool calling is shaped differently in each vendor's
 * API, so adding it would mean four parallel implementations and a change to
 * the contract every consumer depends on. A JSON-returning classifier pass
 * works identically on all four and on any OpenAI-compatible host, and reuses
 * the decodeJson() helper AbstractAiProvider already has.
 *
 * The cost is one extra AI call per commanding message. AiController keeps that
 * off the common path with a verb pre-filter, so "how do I enrol in a course?"
 * still costs exactly one call.
 *
 * FAILURE IS ALWAYS "not a command". A malformed reply, an unknown key, a key
 * the role does not permit, or a provider outage all return null, and the
 * caller falls through to the ordinary conversational answer. The assistant
 * getting chattier is an acceptable failure; acting on a half-parsed intent is
 * not.
 */
final class AiActionPlanner
{
    public function __construct(private AiProvider $ai) {}

    /**
     * @return array{action: string, params: array, missing: array, summary: string}|null
     */
    public function plan(string $prompt, User $user): ?array
    {
        $catalogue = AiActionRegistry::catalogueFor($user);

        if (trim($catalogue) === '') {
            return null;
        }

        $response = $this->ai->ask($this->classifierPrompt($prompt, $catalogue));

        $decoded = $this->decode($response);

        if (! $decoded) {
            return null;
        }

        $action = is_string($decoded['action'] ?? null) ? $decoded['action'] : 'none';

        if ($action === '' || $action === 'none') {
            return null;
        }

        // Re-check permission here rather than trusting the model to have
        // respected the catalogue it was given.
        if (! AiActionRegistry::get($action, $user)) {
            return null;
        }

        return [
            'action' => $action,
            'params' => is_array($decoded['params'] ?? null) ? $decoded['params'] : [],
            'missing' => array_values(array_filter(
                is_array($decoded['missing'] ?? null) ? $decoded['missing'] : [],
                'is_string'
            )),
            'summary' => is_string($decoded['summary'] ?? null) ? $decoded['summary'] : '',
        ];
    }

    /**
     * Same tolerance as AbstractAiProvider::decodeJson(): strip a fenced code
     * block, refuse a ⚠️/🔒 sentinel reply, and require an array.
     */
    private function decode(string $response): ?array
    {
        $trimmed = trim($response);

        if ($trimmed === '' || str_starts_with($trimmed, '⚠️') || str_starts_with($trimmed, AiAccessPolicy::REFUSAL_PREFIX)) {
            return null;
        }

        $clean = preg_replace('/```(?:json)?|```/', '', $trimmed) ?? '';

        // Models sometimes wrap the object in a sentence; take the outermost {}.
        if (($start = strpos($clean, '{')) !== false && ($end = strrpos($clean, '}')) !== false && $end > $start) {
            $clean = substr($clean, $start, $end - $start + 1);
        }

        $decoded = json_decode($clean, true);

        return is_array($decoded) ? $decoded : null;
    }

    private function classifierPrompt(string $prompt, string $catalogue): string
    {
        return <<<PROMPT
        You are an intent classifier for HIMS, a hospital HR system. Decide whether
        the message below is an instruction to perform one of the actions listed,
        and reply with JSON only — no prose, no code fence.

        ACTIONS THIS PERSON MAY PERFORM:
        {$catalogue}

        REPLY SHAPE:
        {"action":"<key from the list, or none>","params":{"<name>":"<value>"},"missing":["<name>"],"summary":"<one short sentence describing what will happen>"}

        RULES:
        - A question ("how do I", "where is", "what is", "can I") is NOT a command.
          Return {"action":"none"} for it.
        - Use only keys and parameter names from the list above. Never invent one.
        - Put a value in params only if the message actually supplies it, or it
          follows unambiguously (e.g. "this year" for a date range). Anything
          required that you cannot fill goes in missing instead — do not guess a
          name, a date, an email or a score.
        - Identify records by the name the person used. Do not invent an id.
        - For a list-valued parameter, use a JSON array of names.
        - summary must state the actual target, e.g. "Create the review cycle
          2027 Annual Performance Review running 2027-01-01 to 2027-12-31".
        - If the message is not clearly one of these actions, return
          {"action":"none"}. That is a normal answer, not a failure.

        MESSAGE:
        {$prompt}
        PROMPT;
    }
}
