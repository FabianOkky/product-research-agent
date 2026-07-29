<?php

use App\Models\User;

test('guests are redirected from home to login', function () {
    $response = $this->get(route('home'));

    $response->assertRedirect(route('login'));
});

test('authenticated users are redirected from home to dashboard', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->get(route('home'));

    $response->assertRedirect(route('dashboard'));
});
