<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        $now = now();

        DB::table('permissions')->insertOrIgnore([
            'key' => 'extraction.use',
            'name' => 'Извлечение данных',
            'created_at' => $now,
            'updated_at' => $now,
        ]);

        $permissionId = DB::table('permissions')->where('key', 'extraction.use')->value('id');
        $roleIds = DB::table('roles')->whereIn('key', ['employee', 'admin'])->pluck('id');

        if ($permissionId !== null && $roleIds->isNotEmpty()) {
            DB::table('permission_role')->insertOrIgnore(
                $roleIds->map(static fn (int $roleId): array => [
                    'permission_id' => $permissionId,
                    'role_id' => $roleId,
                ])->all(),
            );
        }
    }

    public function down(): void
    {
        $permissionId = DB::table('permissions')->where('key', 'extraction.use')->value('id');

        if ($permissionId !== null) {
            DB::table('permission_role')->where('permission_id', $permissionId)->delete();
            DB::table('permissions')->whereKey($permissionId)->delete();
        }
    }
};
