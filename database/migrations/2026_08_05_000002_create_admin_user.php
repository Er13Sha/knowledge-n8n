<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;

return new class extends Migration
{
    public function up(): void
    {
        $email = env('ADMIN_EMAIL', 'admin@example.com');
        $now = now();
        $values = [
            'name' => env('ADMIN_NAME', 'Admin'),
            'email_verified_at' => $now,
            'password' => Hash::make(env('ADMIN_PASSWORD', 'password')),
            'updated_at' => $now,
        ];

        if (DB::table('users')->where('email', $email)->exists()) {
            DB::table('users')->where('email', $email)->update($values);

            return;
        }

        DB::table('users')->insert(array_merge($values, [
            'email' => $email,
            'created_at' => $now,
        ]));
    }

    public function down(): void
    {
        DB::table('users')
            ->where('email', env('ADMIN_EMAIL', 'admin@example.com'))
            ->delete();
    }
};
