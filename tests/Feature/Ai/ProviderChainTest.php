<?php

use App\Ai\Agents\ParamExtractionAgent;
use App\Ai\Agents\SynthesisAgent;
use App\Ai\Support\ProviderChain;
use Laravel\Ai\Enums\Lab;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

it('builds a single-provider chain when no fallback is configured', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => null,
        'research.ai_fallback_model' => null,
    ]);

    expect(ProviderChain::text())->toBe(['gemini' => 'gemini-2.5-flash']);
});

it('appends a local Ollama fallback for development', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'ollama',
        'research.ai_fallback_model' => 'llama3.2:3b',
    ]);

    $chain = ProviderChain::text();

    expect($chain)->toBe([
        'gemini' => 'gemini-2.5-flash',
        'ollama' => 'llama3.2:3b',
    ])
        // Primary must come first so it is attempted before the fallback.
        ->and(array_keys($chain))->toBe(['gemini', 'ollama']);
});

it('appends a cloud fallback like Groq for production', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'groq',
        'research.ai_fallback_model' => 'llama-3.3-70b-versatile',
    ]);

    expect(ProviderChain::text())->toBe([
        'gemini' => 'gemini-2.5-flash',
        'groq' => 'llama-3.3-70b-versatile',
    ]);
});

it('builds a multi-tier chain from comma-separated fallbacks, in order', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'groq,ollama',
        'research.ai_fallback_model' => 'llama-3.3-70b-versatile,llama3.2:3b',
    ]);

    $chain = ProviderChain::text();

    expect($chain)->toBe([
        'gemini' => 'gemini-2.5-flash',
        'groq' => 'llama-3.3-70b-versatile',
        'ollama' => 'llama3.2:3b',
    ])
        // Order matters: primary, then each fallback as written.
        ->and(array_keys($chain))->toBe(['gemini', 'groq', 'ollama']);
});

it('trims whitespace around comma-separated fallbacks', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => ' groq , ollama ',
        'research.ai_fallback_model' => ' llama-3.3-70b-versatile , llama3.2:3b ',
    ]);

    expect(ProviderChain::text())->toBe([
        'gemini' => 'gemini-2.5-flash',
        'groq' => 'llama-3.3-70b-versatile',
        'ollama' => 'llama3.2:3b',
    ]);
});

it('leaves a fallback model null when only the provider is given', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'groq,ollama',
        'research.ai_fallback_model' => 'llama-3.3-70b-versatile',
    ]);

    // Ollama has no paired model, so the SDK will use the provider's default.
    expect(ProviderChain::text())->toBe([
        'gemini' => 'gemini-2.5-flash',
        'groq' => 'llama-3.3-70b-versatile',
        'ollama' => null,
    ]);
});

it('drops Groq from the structured chain but keeps schema-capable providers', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'groq,ollama',
        'research.ai_fallback_model' => 'llama-3.3-70b-versatile,llama3.2:3b',
    ]);

    // Synthesis (plain text) keeps the full chain including Groq...
    expect(ProviderChain::text())->toBe([
        'gemini' => 'gemini-2.5-flash',
        'groq' => 'llama-3.3-70b-versatile',
        'ollama' => 'llama3.2:3b',
    ]);

    // ...but structured extraction skips Groq (strict json_schema incompatible).
    expect(ProviderChain::structured())->toBe([
        'gemini' => 'gemini-2.5-flash',
        'ollama' => 'llama3.2:3b',
    ])
        ->and(array_keys(ProviderChain::structured()))->not->toContain('groq');
});

it('keeps the primary provider in the structured chain even with no compatible fallback', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'groq',
        'research.ai_fallback_model' => 'llama-3.3-70b-versatile',
    ]);

    // Only Groq is configured as fallback; structured() removes it, leaving the primary.
    expect(ProviderChain::structured())->toBe(['gemini' => 'gemini-2.5-flash']);
});

it('treats an empty fallback provider as failover disabled', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => '',
        'research.ai_fallback_model' => '',
    ]);

    expect(ProviderChain::text())->toBe(['gemini' => 'gemini-2.5-flash'])
        ->and(ProviderChain::text())->toHaveCount(1);
});

it('keys the chain by the provider Lab enum value', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'ollama',
        'research.ai_fallback_model' => 'llama3.2:3b',
    ]);

    expect(array_keys(ProviderChain::text()))
        ->toBe([Lab::Gemini->value, Lab::Ollama->value]);
});

it('throws on an unknown primary provider name', function () {
    config(['research.ai_primary_provider' => 'not-a-real-provider']);

    expect(fn () => ProviderChain::text())->toThrow(ValueError::class);
});

it('throws on an unknown fallback provider name', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_fallback_provider' => 'not-a-real-provider',
    ]);

    expect(fn () => ProviderChain::text())->toThrow(ValueError::class);
});

/*
|--------------------------------------------------------------------------
| Failover behaviour (the heart of Phase 9)
|--------------------------------------------------------------------------
|
| These tests prove the chain actually fails over: when the primary provider
| raises a FailoverableException (rate limit / quota exhausted), the agent
| transparently retries on the backup provider and still returns a result.
| The closure fake branches on the provider name to simulate a primary outage
| without touching a real provider.
|
*/

it('fails the synthesis agent over to the backup provider when the primary is rate limited', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'ollama',
        'research.ai_fallback_model' => 'llama3.2:3b',
    ]);

    SynthesisAgent::fake(function ($prompt, $attachments, $provider) {
        if ($provider->name() === 'gemini') {
            throw RateLimitedException::forProvider('gemini');
        }

        return fakeSynthesisReport();
    });

    $report = (new SynthesisAgent)->report('Butuh laptop editing video', ['category' => 'laptop'], [], []);

    // The backup provider produced the report despite the primary being rate limited.
    expect($report)->toContain('## Verdict Final');

    SynthesisAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider()->name() === 'ollama'
        && $prompt->model === 'llama3.2:3b');
});

it('fails over through every tier until a provider succeeds', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'groq,ollama',
        'research.ai_fallback_model' => 'llama-3.3-70b-versatile,llama3.2:3b',
    ]);

    // Both cloud tiers are down; only the local Ollama tier answers.
    SynthesisAgent::fake(function ($prompt, $attachments, $provider) {
        if (in_array($provider->name(), ['gemini', 'groq'], true)) {
            throw RateLimitedException::forProvider($provider->name());
        }

        return fakeSynthesisReport();
    });

    $report = (new SynthesisAgent)->report('Butuh laptop editing video', ['category' => 'laptop'], [], []);

    expect($report)->toContain('## Verdict Final');

    SynthesisAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider()->name() === 'ollama'
        && $prompt->model === 'llama3.2:3b');
});

it('fails the parameter extraction agent over to the backup provider when the primary is rate limited', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'ollama',
        'research.ai_fallback_model' => 'llama3.2:3b',
    ]);

    ParamExtractionAgent::fake(function ($prompt, $attachments, $provider) {
        if ($provider->name() === 'gemini') {
            throw RateLimitedException::forProvider('gemini');
        }

        return [
            'category' => 'laptop',
            'use_case' => 'editing video',
            'budget' => 'tidak disebutkan',
            'priorities' => [],
            'queries' => ['a', 'b', 'c'],
        ];
    });

    $result = (new ParamExtractionAgent)->prompt('Butuh laptop', provider: ProviderChain::text());

    expect($result['category'])->toBe('laptop');

    ParamExtractionAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider()->name() === 'ollama'
        && $prompt->model === 'llama3.2:3b');
});

it('rethrows the rate limit when no fallback provider is configured', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => null,
        'research.ai_fallback_model' => null,
    ]);

    SynthesisAgent::fake(function ($prompt, $attachments, $provider) {
        throw RateLimitedException::forProvider($provider->name());
    });

    // With failover disabled the exception is not swallowed — it surfaces so the
    // pipeline can mark the job failed instead of silently degrading.
    expect(fn () => (new SynthesisAgent)->report('Butuh laptop', ['category' => 'laptop'], [], []))
        ->toThrow(RateLimitedException::class);
});
