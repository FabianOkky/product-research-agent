<?php

namespace App\Ai\Support;

use Laravel\Ai\Enums\Lab;

class ProviderChain
{
    /**
     * Providers whose strict JSON-schema mode is incompatible with our structured
     * extraction schema (optional fields + min/max array bounds), so they are skipped
     * in the structured() chain but kept for plain-text synthesis in text().
     *
     * @var list<string>
     */
    private const STRUCTURED_INCOMPATIBLE = ['groq'];

    /**
     * Build the text-provider failover chain: the primary provider first, then each
     * configured fallback in order. The result is an associative array keyed by each
     * provider's Lab value mapped to its model name, ready to pass as the `provider:`
     * argument of an agent prompt.
     *
     * Fallbacks form a multi-tier chain: RESEARCH_AI_FALLBACK_PROVIDER and
     * RESEARCH_AI_FALLBACK_MODEL accept comma-separated lists paired by position, so
     * "groq,ollama" + "llama-3.3-70b-versatile,llama3.2:3b" tries Gemini, then Groq,
     * then a local Ollama model. A single value (e.g. "ollama") still works, and an
     * empty value disables failover entirely.
     *
     * Failover only triggers on a FailoverableException (rate limit / provider
     * overloaded / insufficient credits) — i.e. the "quota exhausted" case. With no
     * fallback configured the chain holds a single entry, which behaves exactly like
     * an ordinary single-provider prompt.
     *
     * @return array<string, ?string>
     */
    public static function text(): array
    {
        return static::build();
    }

    /**
     * Like text(), but drops providers that cannot satisfy our JSON-schema structured
     * output (see STRUCTURED_INCOMPATIBLE). Used by the structured ParamExtractionAgent
     * so a failover skips straight to a schema-capable provider (Gemini, Ollama, ...).
     * The primary provider is always kept.
     *
     * @return array<string, ?string>
     */
    public static function structured(): array
    {
        return static::build(skip: self::STRUCTURED_INCOMPATIBLE);
    }

    /**
     * Assemble the provider/model failover chain, optionally skipping incompatible
     * fallback providers. The primary provider is always included.
     *
     * @param  list<string>  $skip
     * @return array<string, ?string>
     */
    private static function build(array $skip = []): array
    {
        // Lab::from() validates the provider name against the enum, throwing on a typo.
        $chain = [
            Lab::from((string) config('research.ai_primary_provider'))->value => (string) config('research.ai_model'),
        ];

        $providers = static::splitList(config('research.ai_fallback_provider'));
        $models = static::splitList(config('research.ai_fallback_model'));

        foreach ($providers as $index => $provider) {
            $name = Lab::from($provider)->value;

            if (in_array($name, $skip, true)) {
                continue;
            }

            // A missing model leaves the value null, letting the SDK fall back to the
            // provider's own default text model rather than passing an empty string.
            $chain[$name] = $models[$index] ?? null;
        }

        return $chain;
    }

    /**
     * Split a comma-separated config value into a trimmed, non-empty, re-indexed list
     * so a single fallback ("groq") and a multi-tier chain ("groq,ollama") both work.
     *
     * @return list<string>
     */
    private static function splitList(mixed $value): array
    {
        return collect(explode(',', (string) $value))
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }
}
