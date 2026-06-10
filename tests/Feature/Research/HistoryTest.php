<?php

use App\Livewire\Research\History;
use App\Models\ResearchJob;
use App\Models\User;
use Livewire\Livewire;

test('a guest is redirected to login from the history page', function () {
    $this->get(route('research.history'))->assertRedirect(route('login'));
});

test('the history lists only the current user research jobs', function () {
    $user = User::factory()->create();
    ResearchJob::factory()->for($user)->create([
        'user_input' => 'Butuh keyboard mekanik untuk ngoding',
    ]);

    $other = User::factory()->create();
    ResearchJob::factory()->for($other)->create([
        'user_input' => 'Butuh blender untuk smoothie pagi',
    ]);

    $this->actingAs($user);

    $this->get(route('research.history'))
        ->assertOk()
        ->assertSee('Butuh keyboard mekanik untuk ngoding')
        ->assertDontSee('Butuh blender untuk smoothie pagi');
});

test('each job links to its show page', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create();

    $this->actingAs($user);

    Livewire::test(History::class)
        ->assertSeeHtml('href="'.route('research.show', $job).'"');
});

test('the history shows an empty state when the user has no jobs', function () {
    $this->actingAs(User::factory()->create());

    $this->get(route('research.history'))
        ->assertOk()
        ->assertSee('Belum ada riset')
        ->assertSee('Mulai yang pertama');
});

test('jobs are ordered with the most recent first', function () {
    $user = User::factory()->create();

    ResearchJob::factory()->for($user)->create([
        'user_input' => 'Riset lama tentang headphone',
        'created_at' => now()->subDay(),
    ]);
    ResearchJob::factory()->for($user)->create([
        'user_input' => 'Riset baru tentang monitor',
        'created_at' => now(),
    ]);

    $this->actingAs($user);

    $this->get(route('research.history'))
        ->assertOk()
        ->assertSeeInOrder([
            'Riset baru tentang monitor',
            'Riset lama tentang headphone',
        ]);
});

test('the history paginates at ten jobs per page', function () {
    $user = User::factory()->create();
    ResearchJob::factory()->for($user)->count(15)->create();

    $this->actingAs($user);

    Livewire::test(History::class)
        ->assertViewHas('jobs', fn ($jobs) => $jobs->perPage() === 10
            && $jobs->total() === 15
            && $jobs->count() === 10
            && $jobs->hasPages());
});
