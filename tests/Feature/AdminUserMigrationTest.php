<?php

use App\Models\User;
use Illuminate\Support\Facades\Hash;

test('admin user is created by migrations', function () {
    $admin = User::query()
        ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
        ->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe(env('ADMIN_NAME', 'Admin'))
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check(env('ADMIN_PASSWORD', 'password'), $admin->password))->toBeTrue();
});
