<?php

namespace Tests\Unit;

use App\Contracts\AiProvider;
use App\Services\Ai\AiManager;
use App\Services\Ai\AnthropicProvider;
use App\Services\Ai\GeminiProvider;
use App\Services\Ai\OpenAiProvider;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * The OpenAI-compatible driver's failure reporting and model fallback.
 *
 * All of this is regression cover for one live incident: Groq retired
 * llama-3.3-70b-versatile, the `compatible` slot had no fallback chain to walk,
 * and the reply was "Unable to reach Groq with the configured models. Check the
 * model/API key in .env." — which named the working API key as a suspect and
 * did not name the model that had actually been refused.
 *
 * Every test fakes HTTP. Nothing here reaches a provider.
 */
class OpenAiProviderFailureTest extends TestCase
{
    /** A host's 404 for a model it no longer serves, in Groq/OpenAI's shape. */
    private function modelNotFound(string $model): array
    {
        return [
            'error' => [
                'message' => "The model `{$model}` does not exist or you do not have access to it.",
                'type' => 'invalid_request_error',
                'code' => 'model_not_found',
            ],
        ];
    }

    private function provider(array $config = [], string $label = 'Groq'): OpenAiProvider
    {
        return new OpenAiProvider(array_merge([
            'api_key' => 'gsk_test',
            'base_url' => 'https://api.groq.com/openai/v1',
            'model' => 'openai/gpt-oss-120b',
        ], $config), $label);
    }

    /* ───────────────── the reason survives the loop ───────────────── */

    public function test_a_retired_model_is_named_in_the_reply_with_the_hosts_own_reason(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->modelNotFound('llama-3.3-70b-versatile'), 404),
        ]);

        $reply = $this->provider(['model' => 'llama-3.3-70b-versatile'])->ask('hello');

        $this->assertStringStartsWith('⚠️', $reply, 'the ⚠️ failure contract still holds');
        $this->assertStringContainsString('llama-3.3-70b-versatile', $reply, 'the refused model is named');
        $this->assertStringContainsString('does not exist or you do not have access', $reply, "the host's own reason is quoted");
    }

    public function test_the_reply_clears_the_api_key_rather_than_listing_it_as_a_suspect(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->modelNotFound('openai/gpt-oss-120b'), 404),
        ]);

        $reply = $this->provider()->ask('hello');

        // The host authenticated the request in order to refuse the model, so a
        // message that sends the reader back to the key is sending them nowhere.
        $this->assertStringContainsString('API key works', $reply);
        $this->assertStringNotContainsString('Check the model/API key', $reply);
    }

    public function test_every_refused_model_is_listed_not_only_the_last(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->modelNotFound('stale-primary'), 404)
                ->push($this->modelNotFound('stale-fallback'), 404),
        ]);

        $reply = $this->provider([
            'model' => 'stale-primary',
            'fallback_models' => ['stale-fallback'],
        ])->ask('hello');

        $this->assertStringContainsString('stale-primary', $reply);
        $this->assertStringContainsString('stale-fallback', $reply);
    }

    /* ───────────────── no model configured is a different fault ───────────────── */

    public function test_an_empty_model_list_says_no_request_was_sent(): void
    {
        Http::fake();

        $reply = $this->provider(['model' => '', 'fallback_models' => []])->ask('hello');

        $this->assertStringStartsWith('⚠️', $reply);
        $this->assertStringContainsString('No model is configured', $reply);
        $this->assertStringContainsString('no request was sent', $reply);

        // And it must be literally true: models() was empty, so the loop never ran.
        Http::assertNothingSent();
    }

    /* ───────────────── the fallback chain actually redirects ───────────────── */

    public function test_a_retired_primary_falls_through_to_a_live_fallback(): void
    {
        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->modelNotFound('llama-3.3-70b-versatile'), 404)
                ->push(['choices' => [['message' => ['content' => 'A competency gap is …']]]], 200),
        ]);

        $reply = $this->provider([
            'model' => 'llama-3.3-70b-versatile',
            'fallback_models' => ['openai/gpt-oss-120b'],
        ])->ask('what is a competency gap?');

        $this->assertSame('A competency gap is …', $reply, 'a retired primary costs a redirect, not an outage');
        Http::assertSentCount(2);
    }

    /* ───────────────── non-model failures are still reported as themselves ───────────────── */

    public function test_a_bad_key_is_reported_as_an_api_error_and_stops_the_walk(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response([
                'error' => ['message' => 'Invalid API Key', 'code' => 'invalid_api_key'],
            ], 401),
        ]);

        $reply = $this->provider(['fallback_models' => ['openai/gpt-oss-20b']])->ask('hello');

        $this->assertStringContainsString('API Error (401)', $reply);
        $this->assertStringContainsString('Invalid API Key', $reply);

        // 401 is not a model problem: walking the rest of the chain would just
        // spend the same rejection again under a different model name.
        Http::assertSentCount(1);
    }

    public function test_a_missing_key_never_reaches_the_network(): void
    {
        Http::fake();

        $reply = $this->provider(['api_key' => ''])->ask('hello');

        $this->assertStringContainsString('API key not configured', $reply);
        Http::assertNothingSent();
    }

    public function test_the_label_is_the_configured_host_name_not_openai(): void
    {
        Http::fake([
            '*/chat/completions' => Http::response($this->modelNotFound('x'), 404),
        ]);

        $reply = $this->provider(['model' => 'x'], 'DeepSeek')->ask('hello');

        $this->assertStringContainsString('DeepSeek answered', $reply);
        $this->assertStringNotContainsString('OpenAI answered', $reply);
    }

    public function test_each_slot_names_only_the_env_keys_it_actually_reads(): void
    {
        // One driver class serves two config slots, and guessing which from the
        // label would misfire the moment someone sets AI_COMPATIBLE_LABEL=OpenAI.
        // AiManager passes the keys in, so the message can be exact.
        $manager = new AiManager([
            'default' => 'compatible',
            'providers' => [
                'openai' => [
                    'driver' => 'openai',
                    'api_key' => 'k',
                    'model' => 'gpt-nope',
                    'base_url' => 'https://api.openai.com/v1',
                ],
                'compatible' => [
                    'driver' => 'compatible',
                    'label' => 'Groq',
                    'api_key' => 'k',
                    'model' => 'llama-3.3-70b-versatile',
                    'base_url' => 'https://api.groq.com/openai/v1',
                ],
            ],
        ]);

        Http::fake(['*' => Http::response($this->modelNotFound('any'), 404)]);

        $compatible = $manager->provider('compatible')->ask('hello');
        $this->assertStringContainsString('AI_COMPATIBLE_MODEL', $compatible);
        $this->assertStringContainsString('AI_COMPATIBLE_FALLBACK_MODELS', $compatible);
        $this->assertStringNotContainsString('OPENAI_MODEL', $compatible);

        $openai = $manager->provider('openai')->ask('hello');
        $this->assertStringContainsString('OPENAI_MODEL', $openai);
        $this->assertStringNotContainsString('AI_COMPATIBLE', $openai);
    }

    /* ───────────────── the compatible slot reads its chain from the environment ───────────────── */

    public function test_the_compatible_fallback_chain_is_env_configurable(): void
    {
        // A hardcoded list of Groq model names would be nonsense pointed at
        // DeepSeek or a local Ollama, so this slot's chain has to come from .env.
        $manager = new AiManager([
            'default' => 'compatible',
            'providers' => [
                'compatible' => [
                    'driver' => 'compatible',
                    'label' => 'Groq',
                    'api_key' => 'gsk_test',
                    'model' => 'primary-model',
                    'fallback_models' => ['second-model', 'third-model'],
                    'base_url' => 'https://api.groq.com/openai/v1',
                ],
            ],
        ]);

        Http::fake([
            '*/chat/completions' => Http::sequence()
                ->push($this->modelNotFound('primary-model'), 404)
                ->push($this->modelNotFound('second-model'), 404)
                ->push(['choices' => [['message' => ['content' => 'ok']]]], 200),
        ]);

        $this->assertSame('ok', $manager->provider()->ask('hello'));
        Http::assertSentCount(3);
    }

    #[DataProvider('fallbackEnvStrings')]
    public function test_the_fallback_env_string_parses_to_a_clean_list(string $raw, array $expected): void
    {
        // Mirrors config/services.php: a hand-edited .env line brings stray
        // spaces and trailing commas, and an empty string must not become [''].
        $parsed = array_values(array_filter(
            array_map('trim', explode(',', $raw)),
            fn (string $m): bool => $m !== ''
        ));

        $this->assertSame($expected, $parsed);
    }

    public static function fallbackEnvStrings(): array
    {
        return [
            'empty' => ['', []],
            'single' => ['openai/gpt-oss-20b', ['openai/gpt-oss-20b']],
            'two' => ['a,b', ['a', 'b']],
            'spaced' => [' a , b ', ['a', 'b']],
            'trailing comma' => ['a,b,', ['a', 'b']],
            'only commas' => [',,', []],
            'slashes kept' => ['openai/gpt-oss-120b, qwen/qwen3.6-27b', ['openai/gpt-oss-120b', 'qwen/qwen3.6-27b']],
        ];
    }

    /* ───────────────── the ⚠️ contract the rest of the stack depends on ───────────────── */

    #[DataProvider('failureConfigs')]
    public function test_every_failure_path_returns_a_warning_string_rather_than_throwing(array $config, int $status): void
    {
        // AiProvider::ask() must never throw for a config/API problem:
        // CompetencyGapAnalysisService::parseAiJson() and
        // AbstractAiProvider::decodeJson() both read the "⚠️" prefix as
        // "AI unavailable", and AbstractAiProvider::sanitiseHistory() uses it to
        // keep HIMS's own error strings out of the replayed transcript.
        Http::fake(['*/chat/completions' => Http::response($this->modelNotFound('any'), $status)]);

        $reply = $this->provider($config)->ask('hello');

        $this->assertStringStartsWith('⚠️', $reply);
    }

    public static function failureConfigs(): array
    {
        return [
            'retired model, 404' => [['model' => 'gone'], 404],
            'retired model, 400' => [['model' => 'gone'], 400],
            'no model at all' => [['model' => '', 'fallback_models' => []], 404],
            'no key' => [['api_key' => ''], 404],
        ];
    }

    /* ───────────────── all three drivers share the one message builder ───────────────── */

    public function test_gemini_names_the_refused_model_and_its_own_env_key(): void
    {
        Http::fake([
            '*generativelanguage*' => Http::response([
                'error' => ['message' => 'models/gemini-1.0-pro is not found for API version v1beta', 'code' => 404],
            ], 404),
        ]);

        $reply = (new GeminiProvider([
            'api_key' => 'k',
            'model' => 'gemini-1.0-pro',
            'fallback_models' => [],
        ]))->ask('hello');

        $this->assertStringContainsString('gemini-1.0-pro', $reply);
        $this->assertStringContainsString('is not found for API version', $reply, "Google's own wording survives");
        $this->assertStringContainsString('GEMINI_MODEL', $reply);
        $this->assertStringNotContainsString('AI_COMPATIBLE', $reply, 'each driver names only its own keys');
    }

    public function test_anthropic_names_the_refused_model_and_its_own_env_key(): void
    {
        Http::fake([
            '*api.anthropic.com*' => Http::response([
                'error' => ['message' => 'model: claude-2 not found'],
            ], 404),
        ]);

        $reply = (new AnthropicProvider([
            'api_key' => 'k',
            'model' => 'claude-2',
            'fallback_models' => [],
        ]))->ask('hello');

        $this->assertStringContainsString('claude-2', $reply);
        $this->assertStringContainsString('ANTHROPIC_MODEL', $reply);
        $this->assertStringNotContainsString('GEMINI_MODEL', $reply);
    }

    #[DataProvider('allThreeDrivers')]
    public function test_no_driver_still_blames_the_api_key_for_a_refused_model(string $class, array $config, string $fake): void
    {
        // The regression in one sentence: a refusal proves the key authenticated,
        // so naming the key sends the reader to test the one thing that works.
        Http::fake([$fake => Http::response(['error' => ['message' => 'model_not_found']], 404)]);

        /** @var AiProvider $provider */
        $provider = new $class($config);
        $reply = $provider->ask('hello');

        $this->assertStringStartsWith('⚠️', $reply);
        $this->assertStringNotContainsString('API_KEY in .env', $reply);
        $this->assertStringContainsString('API key works', $reply);
    }

    public static function allThreeDrivers(): array
    {
        return [
            'gemini' => [GeminiProvider::class, ['api_key' => 'k', 'model' => 'm', 'fallback_models' => []], '*generativelanguage*'],
            'anthropic' => [AnthropicProvider::class, ['api_key' => 'k', 'model' => 'm', 'fallback_models' => []], '*anthropic*'],
            'compatible' => [OpenAiProvider::class, ['api_key' => 'k', 'model' => 'm', 'base_url' => 'https://api.groq.com/openai/v1'], '*groq*'],
        ];
    }
}
