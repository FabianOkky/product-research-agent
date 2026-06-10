<?php

namespace App\Jobs;

use App\Ai\Agents\ParamExtractionAgent;
use App\Ai\Agents\SynthesisAgent;
use App\Ai\Support\ProviderChain;
use App\Models\ResearchJob;
use App\Services\JinaReader;
use App\Services\SerperClient;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Laravel\Ai\Enums\Lab;
use RuntimeException;
use Throwable;

class RunProductResearch implements ShouldQueue
{
    use Queueable;

    /**
     * The number of times the job may be attempted.
     */
    public int $tries = 2;

    /**
     * The number of seconds the job may run before timing out.
     */
    public int $timeout = 120;

    /**
     * Create a new job instance.
     *
     * The property is named $researchJob (not $job) to avoid colliding with the
     * queue's own InteractsWithQueue::$job property.
     */
    public function __construct(public ResearchJob $researchJob) {}

    /**
     * Run the full research pipeline, advancing status/current_step/progress as each
     * stage completes so the progress tracker (Phase 5) can poll the row.
     */
    public function handle(SerperClient $serper, JinaReader $jina): void
    {
        try {
            $this->assertPipelineConfigured();

            $this->researchJob->update([
                'status' => 'processing',
                'current_step' => 'Memahami kebutuhanmu...',
                'progress' => 10,
            ]);

            $params = (new ParamExtractionAgent)
                ->prompt($this->researchJob->user_input, provider: ProviderChain::structured())
                ->toArray();
            $queries = $params['queries'] ?? [];

            $this->researchJob->update([
                'extracted_params' => $params,
                'queries' => $queries,
                'current_step' => 'Mencari sumber terpercaya...',
                'progress' => 35,
            ]);

            $sources = $serper->topUrls($queries);

            $this->researchJob->update([
                'sources' => $sources,
                'current_step' => 'Membaca & menganalisis...',
                'progress' => 60,
            ]);

            $pages = $jina->readMany(collect($sources)->pluck('url'));

            $this->researchJob->update([
                'current_step' => 'Menyusun laporan...',
                'progress' => 85,
            ]);

            $report = (new SynthesisAgent)->report(
                $this->researchJob->user_input,
                $params,
                $pages,
                $sources,
            );

            $this->researchJob->update([
                'report' => $report,
                'status' => 'done',
                'current_step' => 'Selesai',
                'progress' => 100,
            ]);
        } catch (Throwable $e) {
            $this->markFailed($e);

            throw $e;
        }
    }

    /**
     * Fail fast with a human-friendly message when the credentials the pipeline
     * depends on are missing, instead of surfacing a cryptic provider/HTTP error to
     * the user (e.g. an empty SERPER_API_KEY or GEMINI_API_KEY on a fresh deploy).
     */
    private function assertPipelineConfigured(): void
    {
        if (blank(config('services.serper.key'))) {
            throw new RuntimeException('Riset belum bisa dijalankan: kunci pencarian (SERPER_API_KEY) belum diatur. Hubungi admin untuk melengkapinya.');
        }

        if (! $this->hasUsableAiProvider()) {
            throw new RuntimeException('Riset belum bisa dijalankan: kredensial AI (mis. GEMINI_API_KEY) belum diatur. Hubungi admin untuk melengkapinya.');
        }
    }

    /**
     * Whether the primary AI provider is usable. The primary is always attempted
     * first, and a missing key surfaces as a non-failoverable auth error that no
     * fallback can recover — so the primary itself must be usable for the pipeline to
     * stand a chance. Ollama runs locally without a key; every other provider needs
     * its API key set.
     */
    private function hasUsableAiProvider(): bool
    {
        $primary = Lab::from((string) config('research.ai_primary_provider'))->value;

        return $primary === Lab::Ollama->value || filled(config("ai.providers.{$primary}.key"));
    }

    /**
     * Handle a job that has exhausted its retries or failed out-of-band (e.g. on
     * timeout) so the row never stays stuck in a "processing" state.
     */
    public function failed(?Throwable $exception): void
    {
        $this->markFailed($exception);
    }

    /**
     * Persist the failed status and error message on the research job.
     */
    private function markFailed(?Throwable $exception): void
    {
        $this->researchJob->update([
            'status' => 'failed',
            'current_step' => 'Gagal',
            'error' => $exception?->getMessage() ?: 'Pipeline gagal tanpa pesan error.',
        ]);
    }
}
