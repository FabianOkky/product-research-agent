<?php

namespace App\Livewire\Research;

use App\Jobs\RunProductResearch;
use App\Models\ResearchJob;
use Illuminate\Support\Facades\Auth;
use Livewire\Attributes\Title;
use Livewire\Component;

#[Title('Riset Produk Baru')]
class Create extends Component
{
    public string $userInput = '';

    /**
     * Create a pending research job from the user's free-form needs.
     */
    public function submit()
    {
        $validated = $this->validate([
            'userInput' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $job = ResearchJob::create([
            'user_id' => Auth::id(),
            'user_input' => $validated['userInput'],
            'status' => 'pending',
        ]);

        RunProductResearch::dispatch($job);

        return $this->redirectRoute('research.show', ['job' => $job], navigate: true);
    }
}
