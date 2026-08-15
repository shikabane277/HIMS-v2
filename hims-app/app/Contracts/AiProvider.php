<?php

namespace App\Contracts;

/**
 * A provider-agnostic AI text interface.
 *
 * Every driver (Gemini, OpenAI, Anthropic, OpenAI-compatible hosts) implements
 * this one method so the rest of the app never needs to know which model is
 * configured. The consumers — AiController and CompetencyGapAnalysisService —
 * depend on this contract, not on any concrete provider.
 *
 * FAILURE CONTRACT: ask() never throws for an API/config problem. On any
 * failure it returns a human-readable string prefixed with "⚠️". Callers such
 * as CompetencyGapAnalysisService::parseAiJson() detect unavailability by
 * testing for that prefix, so every driver MUST honour it.
 *
 * STATELESSNESS: drivers hold no conversation state. AiManager registers each
 * driver as a memoised singleton shared by every consumer, so a driver that
 * remembered previous turns internally would leak one user's chat into another
 * request and into CompetencyGapAnalysisService. Conversation memory is
 * therefore passed in per call and never stored on the driver.
 */
interface AiProvider
{
    /**
     * Send a prompt and return the model's text reply.
     *
     * $history is the earlier turns of this conversation, oldest first, in the
     * shape the app stores them: a list of ['role' => 'user'|'ai', 'message' =>
     * string]. 'ai' is the storage role (the ai_chat_messages enum); each driver
     * maps it to its own wire role — 'assistant' for OpenAI and Anthropic,
     * 'model' for Gemini. Pass [] (the default) for a one-shot question, which
     * is what CompetencyGapAnalysisService does.
     *
     * Drivers must not trust the list: AbstractAiProvider::sanitiseHistory()
     * caps its length, drops "⚠️" failure strings that were persisted as
     * replies, and forces strict user/ai alternation.
     *
     * $scope is an optional role-based instruction appended to the system
     * prompt, built per request by AiAccessPolicy::scopeFor(). It is passed in
     * rather than read from auth() inside the driver because drivers are shared
     * singletons — storing the caller's role on one would leak it into the next
     * request. It is a soft control that shapes answers; the hard block lives in
     * AiController, which refuses a denied question before reaching any driver.
     *
     * On success: the generated answer. On failure: a "⚠️ …" message.
     *
     * @param  list<array{role: string, message: string}>  $history
     */
    public function ask(string $prompt, array $history = [], ?string $scope = null): string;
}
