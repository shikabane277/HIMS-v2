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
 */
interface AiProvider
{
    /**
     * Send a single prompt and return the model's text reply.
     *
     * On success: the generated answer. On failure: a "⚠️ …" message.
     */
    public function ask(string $prompt): string;
}
