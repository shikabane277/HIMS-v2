<?php

namespace App\Services\Ai;

use App\Contracts\AiProvider;
use Illuminate\Support\Facades\Log;

/**
 * Wraps AI providers in a fallback chain.
 * If the primary provider returns an error (starts with "⚠️"), it tries
 * remaining configured providers with valid API keys in order.
 */
class FallbackAiProvider implements AiProvider
{
    /**
     * @param array<int, AiProvider> $providers
     */
    public function __construct(private array $providers) {}

    public function ask(string $prompt, array $history = [], ?string $scope = null): string
    {
        $lastResult = '⚠️ No AI provider available.';

        foreach ($this->providers as $index => $provider) {
            try {
                $result = $provider->ask($prompt, $history, $scope);

                if (! str_starts_with($result, '⚠️')) {
                    return $result;
                }

                $lastResult = $result;
                Log::warning('AI provider in chain returned error, falling back to next provider', [
                    'provider_index' => $index,
                    'error' => $result,
                ]);
            } catch (\Throwable $e) {
                Log::error('AI provider threw exception in fallback chain', [
                    'provider_index' => $index,
                    'error' => $e->getMessage(),
                ]);
                $lastResult = '⚠️ AI provider error: '.$e->getMessage();
            }
        }

        return $lastResult;
    }
}
