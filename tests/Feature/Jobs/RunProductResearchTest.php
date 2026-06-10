<?php

use App\Ai\Agents\ParamExtractionAgent;
use App\Ai\Agents\SynthesisAgent;
use App\Jobs\RunProductResearch;
use App\Models\ResearchJob;
use App\Services\SerperClient;
use Illuminate\Support\Facades\Http;
use Laravel\Ai\Exceptions\RateLimitedException;
use Laravel\Ai\Prompts\AgentPrompt;

beforeEach(function () {
    config([
        'services.serper.key' => 'test-serper-key',
        'services.serper.url' => 'https://google.serper.dev/search',
        'services.jina.url' => 'https://r.jina.ai',
        'ai.providers.gemini.key' => 'test-gemini-key',
        'research.queries' => 3,
        'research.urls_per_query' => 2,
        'research.http_timeout' => 20,
    ]);

    Http::preventStrayRequests();
});

/**
 * Canned structured-output response for the parameter extraction agent.
 *
 * @return array<string, mixed>
 */
function fakeExtractedParams(): array
{
    return [
        'category' => 'laptop',
        'use_case' => 'editing video',
        'budget' => 'Rp15.000.000',
        'priorities' => ['layar akurat warna', 'performa GPU'],
        'queries' => [
            'laptop editing video terbaik 2026',
            'rekomendasi laptop editing budget 15 juta',
            'best video editing laptop review',
        ],
    ];
}

it('runs the full pipeline and moves the job from pending to done', function () {
    ParamExtractionAgent::fake([fakeExtractedParams()]);
    SynthesisAgent::fake([fakeSynthesisReport()]);

    Http::fake([
        'google.serper.dev/*' => Http::response([
            'organic' => [
                ['title' => 'Review A', 'link' => 'https://shop.test/a'],
                ['title' => 'Review B', 'link' => 'https://shop.test/b'],
            ],
        ]),
        'r.jina.ai/*' => Http::response("# Konten Markdown\nIsi halaman uji untuk sintesis."),
    ]);

    $input = 'Butuh laptop untuk editing video dengan budget 15 juta';
    $job = ResearchJob::factory()->create(['status' => 'pending', 'user_input' => $input]);

    RunProductResearch::dispatchSync($job);

    $job->refresh();

    expect($job->status)->toBe('done')
        ->and($job->current_step)->toBe('Selesai')
        ->and($job->progress)->toBe(100)
        ->and($job->error)->toBeNull()
        ->and($job->extracted_params['category'])->toBe('laptop')
        ->and($job->queries)->toHaveCount(3)
        ->and($job->sources)->not->toBeEmpty()
        ->and($job->sources[0])->toHaveKeys(['query', 'url', 'title']);

    // The stored report is the SynthesisAgent's 8-section markdown.
    foreach (synthesisReportHeadings() as $heading) {
        expect($job->report)->toContain($heading);
    }

    ParamExtractionAgent::assertPrompted($input);

    // The synthesis prompt carries the user input, the discovered sources, and the
    // page content read by Jina.
    SynthesisAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->contains($input)
        && $prompt->contains('https://shop.test/a')
        && $prompt->contains('Konten Markdown'));
});

it('marks the job failed and stores the error when a stage throws', function () {
    ParamExtractionAgent::fake([fakeExtractedParams()]);

    // Force a mid-pipeline failure: the resolved SerperClient throws.
    $this->app->bind(SerperClient::class, fn () => new class extends SerperClient
    {
        public function topUrls(array $queries, ?int $perQuery = null): array
        {
            throw new RuntimeException('Serper meledak');
        }
    });

    $job = ResearchJob::factory()->create(['status' => 'pending']);

    expect(fn () => RunProductResearch::dispatchSync($job))
        ->toThrow(RuntimeException::class, 'Serper meledak');

    $job->refresh();

    expect($job->status)->toBe('failed')
        ->and($job->current_step)->toBe('Gagal')
        ->and($job->error)->toBe('Serper meledak')
        ->and($job->report)->toBeNull();
});

it('is configured with two tries and a 120 second timeout', function () {
    $job = new RunProductResearch(ResearchJob::factory()->make());

    expect($job->tries)->toBe(2)
        ->and($job->timeout)->toBe(120);
});

it('fails with a friendly message when the Serper key is missing', function () {
    config(['services.serper.key' => '']);

    $job = ResearchJob::factory()->create(['status' => 'pending']);

    expect(fn () => RunProductResearch::dispatchSync($job))
        ->toThrow(RuntimeException::class);

    $job->refresh();

    expect($job->status)->toBe('failed')
        ->and($job->current_step)->toBe('Gagal')
        ->and($job->error)->toContain('SERPER_API_KEY');
});

it('fails with a friendly message when the primary AI provider key is missing', function () {
    // Even with a keyless Ollama fallback configured, a missing primary key must
    // surface the friendly message: the primary is tried first and its auth error is
    // not failoverable, so no fallback can rescue it.
    config([
        'ai.providers.gemini.key' => '',
        'research.ai_primary_provider' => 'gemini',
        'research.ai_fallback_provider' => 'ollama',
        'research.ai_fallback_model' => 'qwen2.5:3b',
    ]);

    $job = ResearchJob::factory()->create(['status' => 'pending']);

    expect(fn () => RunProductResearch::dispatchSync($job))
        ->toThrow(RuntimeException::class);

    $job->refresh();

    expect($job->status)->toBe('failed')
        ->and($job->current_step)->toBe('Gagal')
        ->and($job->error)->toContain('GEMINI_API_KEY');
});

it('fails over to the backup AI provider mid-pipeline when the primary is rate limited', function () {
    config([
        'research.ai_primary_provider' => 'gemini',
        'research.ai_model' => 'gemini-2.5-flash',
        'research.ai_fallback_provider' => 'ollama',
        'research.ai_fallback_model' => 'llama3.2:3b',
    ]);

    // Gemini (primary) is rate limited during extraction; the provider chain must
    // transparently retry on the Ollama backup so the queued pipeline still finishes.
    ParamExtractionAgent::fake(function ($prompt, $attachments, $provider) {
        if ($provider->name() === 'gemini') {
            throw RateLimitedException::forProvider('gemini');
        }

        return fakeExtractedParams();
    });
    SynthesisAgent::fake([fakeSynthesisReport()]);

    Http::fake([
        'google.serper.dev/*' => Http::response([
            'organic' => [
                ['title' => 'Review A', 'link' => 'https://shop.test/a'],
            ],
        ]),
        'r.jina.ai/*' => Http::response("# Konten\nIsi halaman uji."),
    ]);

    $job = ResearchJob::factory()->create(['status' => 'pending']);

    RunProductResearch::dispatchSync($job);

    $job->refresh();

    expect($job->status)->toBe('done')
        ->and($job->error)->toBeNull()
        ->and($job->extracted_params['category'])->toBe('laptop');

    // Extraction completed on the Ollama backup, not the rate-limited Gemini primary.
    ParamExtractionAgent::assertPrompted(fn (AgentPrompt $prompt) => $prompt->provider()->name() === 'ollama');
});
