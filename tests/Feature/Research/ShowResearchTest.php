<?php

use App\Jobs\RunProductResearch;
use App\Livewire\Research\Show;
use App\Models\ResearchJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    // The retry action re-dispatches the pipeline; keep it off the sync queue.
    Bus::fake();
});

/**
 * Build an unsaved component instance wired to the given job for testing the
 * pure step-state logic without the Livewire lifecycle.
 */
function showFor(ResearchJob $job): Show
{
    $component = new Show;
    $component->job = $job;

    return $component;
}

test('a guest is redirected to login from the show page', function () {
    $job = ResearchJob::factory()->create();

    $this->get(route('research.show', $job))->assertRedirect(route('login'));
});

test('a user cannot view another users research job', function () {
    $job = ResearchJob::factory()->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('research.show', $job))->assertForbidden();
});

test('the owner can view their research job', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'user_input' => 'Butuh headphone untuk WFH dengan budget 1 juta',
    ]);

    $this->actingAs($user);

    $this->get(route('research.show', $job))
        ->assertOk()
        ->assertSee('Butuh headphone untuk WFH dengan budget 1 juta');
});

test('research urls use the opaque uuid as the route key', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create();

    expect($job->uuid)->not->toBeNull()
        ->and(route('research.show', $job))->toEndWith('/research/'.$job->uuid);

    $this->actingAs($user);

    // The sequential integer id is no longer a resolvable route key.
    $this->get('/research/'.$job->id)->assertNotFound();
});

test('the tracker marks steps done, active, and waiting for a processing job', function () {
    $job = ResearchJob::factory()->make([
        'status' => 'processing',
        'current_step' => 'Mencari sumber terpercaya...',
        'progress' => 35,
    ]);

    $component = showFor($job);

    expect($component->stepState(0))->toBe('done')
        ->and($component->stepState(1))->toBe('active')
        ->and($component->stepState(2))->toBe('waiting')
        ->and($component->stepState(3))->toBe('waiting')
        ->and($component->stepState(4))->toBe('waiting');
});

test('the tracker marks every step done for a completed job', function () {
    $job = ResearchJob::factory()->make([
        'status' => 'done',
        'current_step' => 'Selesai',
        'progress' => 100,
    ]);

    $component = showFor($job);

    foreach (range(0, 4) as $index) {
        expect($component->stepState($index))->toBe('done');
    }
});

test('the tracker flags the failed step and keeps earlier steps done', function () {
    $job = ResearchJob::factory()->make([
        'status' => 'failed',
        'current_step' => 'Gagal',
        'progress' => 35,
        'error' => 'Serper meledak',
    ]);

    $component = showFor($job);

    expect($component->stepState(0))->toBe('done')
        ->and($component->stepState(1))->toBe('failed')
        ->and($component->stepState(2))->toBe('waiting')
        ->and($component->stepState(3))->toBe('waiting')
        ->and($component->stepState(4))->toBe('waiting');
});

test('a pending job shows every step waiting', function () {
    $job = ResearchJob::factory()->make([
        'status' => 'pending',
        'current_step' => null,
        'progress' => 0,
    ]);

    $component = showFor($job);

    foreach (range(0, 4) as $index) {
        expect($component->stepState($index))->toBe('waiting');
    }
});

test('the page polls and renders step states while the job is processing', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'status' => 'processing',
        'current_step' => 'Mencari sumber terpercaya...',
        'progress' => 35,
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['job' => $job])
        ->assertSeeHtml('wire:poll.2s')
        ->assertSeeHtml('data-step-state="done"')
        ->assertSeeHtml('data-step-state="active"')
        ->assertSeeHtml('data-step-state="waiting"');
});

test('polling stops and the report appears once the job is done', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'status' => 'processing',
        'current_step' => 'Menyusun laporan...',
        'progress' => 85,
    ]);

    $this->actingAs($user);

    $component = Livewire::test(Show::class, ['job' => $job])
        ->assertSeeHtml('wire:poll.2s');

    // Simulate the queue worker finishing the pipeline between polls.
    $job->update([
        'status' => 'done',
        'current_step' => 'Selesai',
        'progress' => 100,
        'report' => "# Laporan\nRekomendasi headphone terbaik untukmu.",
    ]);

    $component->call('refreshJob')
        ->assertDontSeeHtml('wire:poll.2s')
        ->assertSee('Rekomendasi headphone terbaik untukmu.');
});

test('the done report is rendered as sanitized markdown', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'status' => 'done',
        'current_step' => 'Selesai',
        'progress' => 100,
        'report' => "## Kebutuhan Kamu\n\nButuh laptop editing.\n\n<script>alert('xss-pwned')</script>",
    ]);

    $this->actingAs($user);

    $this->get(route('research.show', $job))
        ->assertOk()
        ->assertSee('Kebutuhan Kamu')          // markdown heading rendered as HTML
        ->assertSee('Butuh laptop editing.')
        ->assertDontSee('xss-pwned');          // raw HTML from the LLM is stripped
});

test('a failed job stops polling and shows the error with a retry button', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'status' => 'failed',
        'current_step' => 'Gagal',
        'progress' => 35,
        'error' => 'Sumber tidak dapat dijangkau',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['job' => $job])
        ->assertDontSeeHtml('wire:poll.2s')
        ->assertSee('Sumber tidak dapat dijangkau')
        ->assertSee('Coba lagi');
});

test('retrying a failed job resets it to pending and re-dispatches the pipeline', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'status' => 'failed',
        'current_step' => 'Gagal',
        'progress' => 35,
        'error' => 'Sumber tidak dapat dijangkau',
    ]);

    $this->actingAs($user);

    Livewire::test(Show::class, ['job' => $job])
        ->call('retry');

    $job->refresh();

    expect($job->status)->toBe('pending')
        ->and($job->progress)->toBe(0)
        ->and($job->current_step)->toBeNull()
        ->and($job->error)->toBeNull();

    Bus::assertDispatched(RunProductResearch::class, fn (RunProductResearch $dispatched) => $dispatched->researchJob->is($job));
});
