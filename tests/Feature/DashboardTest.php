<?php

use App\Livewire\Dashboard;
use App\Models\ResearchJob;
use App\Models\User;
use Livewire\Livewire;

test('guests are redirected to the login page', function () {
    $response = $this->get(route('dashboard'));
    $response->assertRedirect(route('login'));
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});

test('the dashboard summarizes the current user\'s research counts', function () {
    $user = User::factory()->create();
    ResearchJob::factory()->for($user)->create(['status' => 'done']);
    ResearchJob::factory()->for($user)->create(['status' => 'done']);
    ResearchJob::factory()->for($user)->create(['status' => 'processing']);
    ResearchJob::factory()->for($user)->create(['status' => 'pending']);
    ResearchJob::factory()->for($user)->create(['status' => 'failed']);

    Livewire::actingAs($user)
        ->test(Dashboard::class)
        ->assertViewHas('stats', fn ($stats) => $stats['total'] === 5
            && $stats['done'] === 2
            && $stats['in_progress'] === 2
            && $stats['failed'] === 1);
});

test('the dashboard lists recent research linking to each report', function () {
    $user = User::factory()->create();
    $job = ResearchJob::factory()->for($user)->create([
        'status' => 'done',
        'user_input' => 'Cari laptop untuk video editing budget 15 juta',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Cari laptop untuk video editing')
        ->assertSee(route('research.show', $job));
});

test('the dashboard only shows the current user\'s research', function () {
    $user = User::factory()->create();
    $other = User::factory()->create();
    ResearchJob::factory()->for($other)->create([
        'user_input' => 'Riset rahasia milik orang lain',
    ]);

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('Riset rahasia milik orang lain');
});

test('the dashboard shows an empty state when there is no research', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Belum ada riset');
});
