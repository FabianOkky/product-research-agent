<?php

namespace App\Livewire\Research;

use App\Jobs\RunProductResearch;
use App\Models\ResearchJob;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Hasil Riset')]
class Show extends Component
{
    public ResearchJob $job;

    /**
     * Canonical, ordered list of pipeline steps shown in the tracker. Each label
     * must match the current_step values written by RunProductResearch so the
     * tracker can locate the active step.
     *
     * @var list<string>
     */
    public array $steps = [
        'Memahami kebutuhanmu...',
        'Mencari sumber terpercaya...',
        'Membaca & menganalisis...',
        'Menyusun laporan...',
        'Selesai',
    ];

    /**
     * Resolve the research job and ensure it belongs to the current user.
     */
    public function mount(ResearchJob $job): void
    {
        abort_unless($job->user_id === Auth::id(), 403);

        $this->job = $job;
    }

    /**
     * Poll target: reload the row so the tracker reflects the queue worker's
     * latest progress. The view halts polling once the job settles.
     */
    public function refreshJob(): void
    {
        $this->job->refresh();
    }

    /**
     * Re-run a failed pipeline against the same job so the user can retry without
     * re-typing their needs.
     */
    public function retry(): void
    {
        abort_unless($this->job->user_id === Auth::id(), 403);

        if ($this->job->status !== 'failed') {
            return;
        }

        $this->job->update([
            'status' => 'pending',
            'current_step' => null,
            'progress' => 0,
            'error' => null,
        ]);

        RunProductResearch::dispatch($this->job);
    }

    /**
     * Whether the job is still running and the view should keep polling.
     */
    #[Computed]
    public function polling(): bool
    {
        return in_array($this->job->status, ['pending', 'processing'], true);
    }

    /**
     * Resolve the tracker state for the step at the given index.
     *
     * Returns one of: done, active, failed, waiting.
     */
    public function stepState(int $index): string
    {
        // A finished job lights up every step.
        if ($this->job->status === 'done') {
            return 'done';
        }

        // A failed job marks completed steps as done and flags the step it died
        // on. current_step is overwritten with "Gagal" on failure, so the failure
        // point is recovered from progress instead.
        if ($this->job->status === 'failed') {
            $failedIndex = $this->failedStepIndex();

            return match (true) {
                $index < $failedIndex => 'done',
                $index === $failedIndex => 'failed',
                default => 'waiting',
            };
        }

        // Pending/processing: locate the active step from current_step.
        $currentIndex = array_search($this->job->current_step, $this->steps, true);

        if ($currentIndex === false) {
            return 'waiting';
        }

        return match (true) {
            $index < $currentIndex => 'done',
            $index === $currentIndex => 'active',
            default => 'waiting',
        };
    }

    /**
     * Map the progress recorded at failure back to the step it was running, using
     * the same thresholds RunProductResearch sets when entering each step.
     */
    private function failedStepIndex(): int
    {
        return match (true) {
            $this->job->progress >= 85 => 3,
            $this->job->progress >= 60 => 2,
            $this->job->progress >= 35 => 1,
            default => 0,
        };
    }
}
