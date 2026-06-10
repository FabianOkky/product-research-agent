<?php

namespace App\Livewire\Research;

use App\Models\ResearchJob;
use Illuminate\Contracts\View\View;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;
use Livewire\WithPagination;

#[Title('Riwayat Riset')]
class History extends Component
{
    use WithPagination;

    /**
     * List the current user's research jobs, most recent first.
     */
    public function render(): View
    {
        return view('livewire.research.history', [
            'jobs' => ResearchJob::where('user_id', Auth::id())
                ->latest()
                ->paginate(10),
        ]);
    }
}
