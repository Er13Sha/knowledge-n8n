<?php

use App\Models\User;
use Illuminate\Support\Facades\RateLimiter;
use Laravel\Fortify\Features;

test('login screen can be rendered', function () {
    $response = $this->get(route('login'));

    $response->assertOk();
});

test('users can authenticate using the login screen', function () {
    $user = User::factory()->create();

    $response = $this->postJson(route('api.auth.login'), [
        'email' => $user->email,
        'password' => 'password',
    ]);

    $this->assertAuthenticated();
    $response
        ->assertOk()
        ->assertJsonPath('data.id', $user->id);
});

test('two factor authentication remains disabled for the api client', function () {
    expect(Features::enabled(Features::twoFactorAuthentication()))->toBeFalse();
});

test('users can not authenticate with invalid password', function () {
    $user = User::factory()->create();

    $this->postJson(route('api.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ])->assertUnprocessable();

    $this->assertGuest();
});

test('users can logout', function () {
    $user = User::factory()->create();

    $response = $this->actingAs($user)->postJson(route('api.auth.logout'));

    $response->assertNoContent();

    $this->assertGuest();
});

test('users are rate limited', function () {
    $user = User::factory()->create();

    RateLimiter::increment(md5('login'.implode('|', [$user->email, '127.0.0.1'])), amount: 5);

    $response = $this->postJson(route('api.auth.login'), [
        'email' => $user->email,
        'password' => 'wrong-password',
    ]);

    $response->assertTooManyRequests();
});
