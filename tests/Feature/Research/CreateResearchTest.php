<?php

use App\Jobs\RunProductResearch;
use App\Livewire\Research\Create;
use App\Models\ResearchJob;
use App\Models\User;
use Illuminate\Support\Facades\Bus;
use Livewire\Livewire;

beforeEach(function () {
    // Keep the research pipeline from running inline on the sync queue during tests.
    Bus::fake();
});

test('guests are redirected to login from the research create page', function () {
    $this->get(route('research.create'))->assertRedirect(route('login'));
});

test('authenticated users can view the research create page', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('research.create'))->assertOk();
});

test('submitting valid input creates a pending research job and redirects', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $input = 'Saya butuh laptop untuk desain grafis dengan budget 15 juta.';

    Livewire::test(Create::class)
        ->set('userInput', $input)
        ->call('submit')
        ->assertHasNoErrors()
        ->assertRedirect();

    $job = ResearchJob::first();

    expect($job)->not->toBeNull();
    expect($job->user_id)->toBe($user->id);
    expect($job->status)->toBe('pending');
    expect($job->user_input)->toBe($input);

    Bus::assertDispatched(RunProductResearch::class, fn (RunProductResearch $dispatched) => $dispatched->researchJob->is($job));
});

test('submitting too-short input fails validation and creates no job', function () {
    $this->actingAs(User::factory()->create());

    Livewire::test(Create::class)
        ->set('userInput', 'pendek')
        ->call('submit')
        ->assertHasErrors(['userInput' => 'min']);

    expect(ResearchJob::count())->toBe(0);

    Bus::assertNotDispatched(RunProductResearch::class);
});

test('a user cannot view another users research job', function () {
    $owner = User::factory()->create();
    $job = ResearchJob::factory()->for($owner)->create();

    $this->actingAs(User::factory()->create());

    $this->get(route('research.show', $job))->assertForbidden();
});
