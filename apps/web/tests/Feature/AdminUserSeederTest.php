<?php

use App\Models\User;
use Database\Seeders\AccessControlSeeder;
use Database\Seeders\AdminUserSeeder;
use Illuminate\Support\Facades\Hash;

test('admin user is created by the admin seeder', function () {
    $this->seed([
        AccessControlSeeder::class,
        AdminUserSeeder::class,
    ]);

    $admin = User::query()
        ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
        ->first();

    expect($admin)->not->toBeNull()
        ->and($admin->name)->toBe(env('ADMIN_NAME', 'Admin'))
        ->and($admin->email_verified_at)->not->toBeNull()
        ->and(Hash::check(env('ADMIN_PASSWORD', 'password'), $admin->password))->toBeTrue()
        ->and($admin->is_super_admin)->toBeTrue()
        ->and($admin->roles()->where('key', 'admin')->exists())->toBeTrue();
});
