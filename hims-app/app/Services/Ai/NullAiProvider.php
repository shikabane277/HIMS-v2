<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;

/**
 * Fallback driver used when AI_PROVIDER names a provider that does not exist.
 *
 * Honours the AiProvider contract: ask() never throws, it returns a "⚠️"-prefixed
 * string. Consumers already treat that prefix as "AI unavailable"
 * (see CompetencyGapAnalysisService::parseAiJson), so a typo in .env degrades
 * the AI panel instead of returning a 500 for the whole page.
 */
class NullAiProvider implements AiProvider
{
    /** @param list<string> $available names of the providers that are configured */
    public function __construct(
        private string $configured,
        private array $available = [],
    ) {}

    public function ask(string $prompt): string
    {
        $known = $this->available === [] ? 'none' : implode(', ', $this->available);

        return "⚠️ AI is not configured: AI_PROVIDER is set to [{$this->configured}], "
            . "which is not a known provider. Set AI_PROVIDER in .env to one of: {$known}. "
            . 'Then run: php artisan config:clear';
    }
}
