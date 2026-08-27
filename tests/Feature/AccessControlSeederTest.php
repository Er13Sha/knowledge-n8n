<?php

use Database\Seeders\AccessControlSeeder;
use Illuminate\Support\Facades\DB;

test('access control seeder creates the default roles and permissions', function () {
    $this->seed(AccessControlSeeder::class);

    expect(DB::table('roles')->pluck('key')->all())
        ->toContain('employee', 'admin')
        ->and(DB::table('permissions')->pluck('key')->all())
        ->toContain(
            'knowledge.read',
            'knowledge.create',
            'knowledge.update',
            'knowledge.delete',
            'employees.manage',
            'access.manage',
        )
        ->and(DB::table('permission_role')->count())->toBe(8);
});
