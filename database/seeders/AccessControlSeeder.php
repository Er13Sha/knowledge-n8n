<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class AccessControlSeeder extends Seeder
{
    public function run(): void
    {
        $now = now();

        DB::table('roles')->upsert([
            ['key' => 'employee', 'name' => 'Сотрудник', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
            ['key' => 'admin', 'name' => 'Админ', 'is_system' => true, 'created_at' => $now, 'updated_at' => $now],
        ], ['key'], ['name', 'is_system', 'updated_at']);

        $permissions = [
            ['key' => 'knowledge.read', 'name' => 'Чтение базы знаний'],
            ['key' => 'knowledge.create', 'name' => 'Создание документов'],
            ['key' => 'knowledge.update', 'name' => 'Редактирование документов'],
            ['key' => 'knowledge.delete', 'name' => 'Удаление документов'],
            ['key' => 'employees.manage', 'name' => 'Управление сотрудниками'],
            ['key' => 'access.manage', 'name' => 'Управление доступами'],
        ];

        DB::table('permissions')->upsert(
            array_map(
                fn (array $permission): array => $permission + ['created_at' => $now, 'updated_at' => $now],
                $permissions,
            ),
            ['key'],
            ['name', 'updated_at'],
        );

        $roleIds = DB::table('roles')->pluck('id', 'key');
        $permissionIds = DB::table('permissions')->pluck('id', 'key');

        DB::table('permission_role')->insertOrIgnore([
            ['permission_id' => $permissionIds['knowledge.read'], 'role_id' => $roleIds['employee']],
            ['permission_id' => $permissionIds['knowledge.create'], 'role_id' => $roleIds['employee']],
            ['permission_id' => $permissionIds['knowledge.update'], 'role_id' => $roleIds['employee']],
            ['permission_id' => $permissionIds['knowledge.delete'], 'role_id' => $roleIds['employee']],
            ['permission_id' => $permissionIds['knowledge.read'], 'role_id' => $roleIds['admin']],
            ['permission_id' => $permissionIds['knowledge.create'], 'role_id' => $roleIds['admin']],
            ['permission_id' => $permissionIds['knowledge.update'], 'role_id' => $roleIds['admin']],
            ['permission_id' => $permissionIds['knowledge.delete'], 'role_id' => $roleIds['admin']],
        ]);

        DB::table('users')
            ->where('is_super_admin', false)
            ->pluck('id')
            ->each(function (int $userId) use ($roleIds): void {
                DB::table('role_user')->insertOrIgnore([
                    ['role_id' => $roleIds['employee'], 'user_id' => $userId],
                ]);
            });
    }
}
