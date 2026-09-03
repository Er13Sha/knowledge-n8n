<?php

use App\Models\User;

test('guests can load the dashboard spa shell', function () {
    $response = $this->get(route('dashboard'));
    $response->assertOk()->assertSee('id="app"', escape: false);
});

test('authenticated users can visit the dashboard', function () {
    $user = User::factory()->create();
    $this->actingAs($user);

    $response = $this->get(route('dashboard'));
    $response->assertOk();
});
