<?php

namespace Database\Seeders;

use App\Models\Role;
use App\Models\User;
use Illuminate\Database\Seeder;

class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::query()->firstOrNew([
            'email' => env('ADMIN_EMAIL', 'admin@example.com'),
        ]);

        $admin->forceFill([
            'name' => env('ADMIN_NAME', 'Admin'),
            'email_verified_at' => now(),
            'password' => env('ADMIN_PASSWORD', 'password'),
            'is_super_admin' => true,
        ])->save();

        $adminRole = Role::query()->where('key', 'admin')->firstOrFail();

        $admin->roles()->sync([$adminRole->id]);
    }
}
