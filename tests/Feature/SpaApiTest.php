<?php

use App\Models\User;

test('application routes render the vue spa shell', function (string $path) {
    $this->get($path)
        ->assertOk()
        ->assertSee('id="app"', escape: false)
        ->assertDontSee('data-page=', escape: false);
})->with(['/', '/login', '/dashboard', '/knowledge']);

test('authentication api requires a session for the current user', function () {
    $this->getJson(route('api.auth.user'))->assertUnauthorized();
});

test('authentication api returns the current user', function () {
    $user = User::factory()->create();

    $this->actingAs($user)
        ->getJson(route('api.auth.user'))
        ->assertOk()
        ->assertJsonPath('data.id', $user->id)
        ->assertJsonPath('data.email', $user->email);
});
