<?php

namespace App\Livewire;

use App\Models\ResearchJob;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Dashboard')]
class Dashboard extends Component
{
    /**
     * Show an at-a-glance summary of the current user's research: status counts
     * and the most recent runs.
     */
    public function render(): View
    {
        $userId = Auth::id();

        /** @var object{total: int, done: int, in_progress: int, failed: int} $counts */
        $counts = ResearchJob::query()
            ->where('user_id', $userId)
            ->toBase()
            ->selectRaw('count(*) as total')
            ->selectRaw("coalesce(sum(case when status = 'done' then 1 else 0 end), 0) as done")
            ->selectRaw("coalesce(sum(case when status in ('pending', 'processing') then 1 else 0 end), 0) as in_progress")
            ->selectRaw("coalesce(sum(case when status = 'failed' then 1 else 0 end), 0) as failed")
            ->first();

        $recentJobs = ResearchJob::query()
            ->where('user_id', $userId)
            ->latest()
            ->take(5)
            ->get(['uuid', 'user_input', 'status', 'created_at']);

        return view('livewire.dashboard', [
            'stats' => [
                'total' => (int) $counts->total,
                'done' => (int) $counts->done,
                'in_progress' => (int) $counts->in_progress,
                'failed' => (int) $counts->failed,
            ],
            'recentJobs' => $recentJobs,
        ]);
    }
}
