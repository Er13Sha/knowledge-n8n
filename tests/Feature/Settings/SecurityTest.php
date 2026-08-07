<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('security page renders the spa shell', function () {
    $this->get('/settings/security')
        ->assertOk()
        ->assertSee('id="app"', escape: false);
});

test('password api requires authentication', function () {
    $this->putJson(route('api.settings.password.update'), [
        'current_password' => 'password',
        'password' => 'new-password',
        'password_confirmation' => 'new-password',
    ])->assertUnauthorized();
});

test('profile api requires authentication', function () {
    $this->patchJson(route('api.settings.profile.update'), [
        'name' => 'Test User',
        'email' => 'test@example.com',
    ])->assertUnauthorized();
});

test('password can be updated', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.settings.password.update'), [
            'current_password' => 'password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response->assertOk();

    expect(Hash::check('new-password', $user->refresh()->password))->toBeTrue();
});

test('correct password must be provided to update password', function () {
    $user = User::factory()->create();

    $response = $this
        ->actingAs($user)
        ->putJson(route('api.settings.password.update'), [
            'current_password' => 'wrong-password',
            'password' => 'new-password',
            'password_confirmation' => 'new-password',
        ]);

    $response
        ->assertUnprocessable()
        ->assertJsonValidationErrors('current_password');
});
