<?php

namespace App\Services\Access;

use App\Models\KnowledgeDocument;
use App\Models\Role;
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

    /** @var list<string> */
    private const array ProtectedPermissions = [self::EmployeesManage, self::AccessManage];

    public function allows(User $user, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if (in_array($permission, self::ProtectedPermissions, true)) {
            return false;
        }

        return $this->hasRolePermission($user, $permission)
            && $this->roleScopeAllowsUser($user, $permission);
    }

    public function hasGlobalPermission(User $user, string $permission): bool
    {
        if ($user->is_super_admin) {
            return true;
        }

        if (in_array($permission, self::ProtectedPermissions, true)) {
            return false;
        }

        return $this->hasRolePermission($user, $permission, Role::GlobalScope);
    }

    public function canAccessDepartment(User $user, string $departmentId, string $permission): bool
    {
        if ($user->is_super_admin || $this->hasGlobalPermission($user, $permission)) {
            return true;
        }

        return $user->department_id !== null
            && $user->department_id === $departmentId
            && $this->hasRolePermission($user, $permission, Role::DepartmentScope);
    }

    /** @return Builder<KnowledgeDocument> */
    public function visibleDocuments(User $user): Builder
    {
        $query = KnowledgeDocument::query();

        if (! $this->allows($user, self::KnowledgeRead)) {
            return $query->whereRaw('1 = 0');
        }

        if ($this->hasGlobalPermission($user, self::KnowledgeRead)) {
            return $query;
        }

        return $query->where(function (Builder $documentQuery) use ($user): void {
            $documentQuery->where('user_id', $user->id);

            if ($user->department_id !== null
                && $this->hasRolePermission($user, self::KnowledgeRead, Role::DepartmentScope)) {
                $documentQuery->orWhereHas('knowledge', function (Builder $knowledgeQuery) use ($user): void {
                    $knowledgeQuery->where('department_id', $user->department_id);
                });
            }
        });
    }

    public function canAccessDocument(
        User $user,
        KnowledgeDocument $document,
        string $permission = self::KnowledgeRead,
    ): bool {
        if ($user->is_super_admin || $this->hasGlobalPermission($user, $permission)) {
            return true;
        }

        if (! $this->hasRolePermission($user, $permission)) {
            return false;
        }

        if ($document->user_id === $user->id) {
            return true;
        }

        return $user->department_id !== null
            && $document->loadMissing('knowledge')->knowledge?->department_id === $user->department_id
            && $this->hasRolePermission($user, $permission, Role::DepartmentScope);
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

        return DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->whereNotIn('permissions.key', self::ProtectedPermissions)
            ->distinct()
            ->pluck('permissions.key')
            ->all();
    }

    private function hasRolePermission(User $user, string $permission, ?string $scope = null): bool
    {
        $query = DB::table('role_user')
            ->join('permission_role', 'permission_role.role_id', '=', 'role_user.role_id')
            ->join('permissions', 'permissions.id', '=', 'permission_role.permission_id')
            ->join('roles', 'roles.id', '=', 'role_user.role_id')
            ->where('role_user.user_id', $user->id)
            ->where('permissions.key', $permission);

        if ($scope !== null) {
            $query->where('roles.scope', $scope);
        }

        return $query->exists();
    }

    private function roleScopeAllowsUser(User $user, string $permission): bool
    {
        return $this->hasRolePermission($user, $permission, Role::GlobalScope)
            || $this->hasRolePermission($user, $permission, Role::DepartmentScope);
    }
}
