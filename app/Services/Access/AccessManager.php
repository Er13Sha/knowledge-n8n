<?php

namespace App\Services\Access;

use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

class AccessManager
{
    public const string KnowledgeRead = 'knowledge.read';

    public const string KnowledgeCreate = 'knowledge.create';

    public const string KnowledgeUpdate = 'knowledge.update';

    public const string KnowledgeDelete = 'knowledge.delete';

    public const string EmployeesManage = 'employees.manage';

    public const string AccessManage = 'access.manage';

    public function allows(User $user, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $permissionId = $this->permissionId($permission);

        if ($permissionId === null) {
            return false;
        }

        return $this->userHasPermission($user, $permissionId)
            || $this->roleHasPermission($user, $permissionId)
            || $this->departmentHasPermission($user, $permissionId);
    }

    public function hasGlobalPermission(User $user, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        $permissionId = $this->permissionId($permission);

        return $permissionId !== null
            && ($this->userHasPermission($user, $permissionId)
                || $this->roleHasGlobalPermission($user, $permissionId));
    }

    /** @return Builder<KnowledgeDocument> */
    public function visibleDocuments(User $user): Builder
    {
        $query = KnowledgeDocument::query();

        if ($this->hasGlobalPermission($user, self::KnowledgeRead)) {
            return $query;
        }

        if ($user->department_id !== null
            && $this->departmentHasPermissionByKey($user, self::KnowledgeRead)) {
            return $query->whereHas('knowledge', function (Builder $knowledgeQuery) use ($user): void {
                $knowledgeQuery->where('department_id', $user->department_id);
            });
        }

        return $query->where('user_id', $user->id);
    }

    public function canAccessDocument(
        User $user,
        KnowledgeDocument $document,
        string $permission = self::KnowledgeRead,
    ): bool {
        if ($this->hasGlobalPermission($user, $permission)) {
            return true;
        }

        return ($user->department_id !== null
                && $document->knowledge?->department_id === $user->department_id
                && $this->departmentHasPermissionByKey($user, $permission))
            || $document->user_id === $user->id && $this->allows($user, $permission);
    }

    /** @return list<array{key: string, name: string}> */
    public function permissions(): array
    {
        return DB::table('permissions')
            ->orderBy('id')
            ->get(['key', 'name'])
            ->map(fn (object $permission): array => [
                'key' => $permission->key,
                'name' => $permission->name,
            ])
            ->all();
    }

    /** @return list<string> */
    public function permissionKeys(User $user): array
    {
        if ($user->is_super_admin) {
            return DB::table('permissions')->pluck('key')->all();
        }

        $keys = DB::table('user_permissions')
            ->join('permissions', 'permissions.id', '=', 'user_permissions.permission_id')
            ->where('user_permissions.user_id', $user->id)
            ->pluck('permissions.key')
            ->all();

        $keys = array_merge($keys, DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->where('role_user.user_id', $user->id)
            ->pluck('permissions.key')
            ->all());

        if ($user->department_id !== null) {
            $keys = array_merge($keys, DB::table('department_permissions')
                ->join('permissions', 'permissions.id', '=', 'department_permissions.permission_id')
                ->where('department_permissions.department_id', $user->department_id)
                ->pluck('permissions.key')
                ->all());
        }

        return array_values(array_unique($keys));
    }

    private function permissionId(string $permission): ?int
    {
        $id = DB::table('permissions')->where('key', $permission)->value('id');

        return $id === null ? null : (int) $id;
    }

    private function userHasPermission(User $user, int $permissionId): bool
    {
        return DB::table('user_permissions')
            ->where('user_id', $user->id)
            ->where('permission_id', $permissionId)
            ->exists();
    }

    private function roleHasPermission(User $user, int $permissionId): bool
    {
        return DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('permission_role.permission_id', $permissionId)
            ->exists();
    }

    private function roleHasGlobalPermission(User $user, int $permissionId): bool
    {
        return DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('permission_role.permission_id', $permissionId)
            ->where('roles.key', '!=', 'employee')
            ->exists();
    }

    private function departmentHasPermission(User $user, int $permissionId): bool
    {
        return $user->department_id !== null
            && DB::table('department_permissions')
                ->where('department_id', $user->department_id)
                ->where('permission_id', $permissionId)
                ->exists();
    }

    private function departmentHasPermissionByKey(User $user, string $permission): bool
    {
        $permissionId = $this->permissionId($permission);

        return $permissionId !== null && $this->departmentHasPermission($user, $permissionId);
    }
}
