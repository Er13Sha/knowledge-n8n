<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class EmployeeController extends Controller
{
    public function index(AccessManager $accessManager): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->with('roles')
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->userData($user, $accessManager))
                ->values(),
            'meta' => [
                'roles' => Role::query()
                    ->with('permissions')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Role $role): array => $this->roleData($role))
                    ->values(),
                'permissions' => $accessManager->permissions(),
                'departments' => Department::options(),
                'role_scopes' => [
                    ['value' => Role::GlobalScope, 'title' => 'Все отделы'],
                    ['value' => Role::DepartmentScope, 'title' => 'Отдел пользователя'],
                ],
            ],
        ]);
    }

    public function store(Request $request, AccessManager $accessManager): JsonResponse
    {
        $validated = $this->validateEmployee($request, true);
        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'department_id' => $validated['department_id'] ?? null,
            ]);

            $this->syncAccess($user, $validated['roles']);

            return $user->fresh('roles');
        });

        return response()->json(['data' => $this->userData($user, $accessManager)], Response::HTTP_CREATED);
    }

    public function update(Request $request, User $user, AccessManager $accessManager): JsonResponse
    {
        $validated = $this->validateEmployee($request, false, $user);

        DB::transaction(function () use ($validated, $user): void {
            $attributes = [
                'name' => $validated['name'],
                'email' => $validated['email'],
                'department_id' => $validated['department_id'] ?? null,
            ];

            if (filled($validated['password'] ?? null)) {
                $attributes['password'] = $validated['password'];
            }

            $user->update($attributes);
            $this->syncAccess($user, $validated['roles']);
        });

        return response()->json(['data' => $this->userData($user->fresh('roles'), $accessManager)]);
    }

    public function storeRole(Request $request): JsonResponse
    {
        $validated = $this->validateRole($request, true);
        $role = DB::transaction(function () use ($validated): Role {
            $role = Role::query()->create([
                'key' => $validated['key'],
                'name' => $validated['name'],
                'scope' => $validated['scope'],
                'is_system' => false,
            ]);
            $role->permissions()->sync($this->permissionIds($validated['permissions'] ?? []));

            return $role->fresh('permissions');
        });

        return response()->json(['data' => $this->roleData($role)], Response::HTTP_CREATED);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        abort_if($role->is_system, Response::HTTP_FORBIDDEN);

        $validated = $this->validateRole($request, false, $role);
        $role->update([
            'name' => $validated['name'],
            'scope' => $validated['scope'],
        ]);
        $role->permissions()->sync($this->permissionIds($validated['permissions'] ?? []));

        return response()->json(['data' => $this->roleData($role->fresh('permissions'))]);
    }

    public function destroyRole(Role $role): JsonResponse
    {
        abort_if($role->is_system, Response::HTTP_FORBIDDEN);
        abort_if($role->users()->exists(), Response::HTTP_CONFLICT, 'Нельзя удалить роль, назначенную сотрудникам.');

        $role->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function validateEmployee(Request $request, bool $creating, ?User $user = null): array
    {
        $emailRule = Rule::unique('users', 'email');

        if ($user !== null) {
            $emailRule = $emailRule->ignore($user->id);
        }

        return $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', $emailRule],
            'password' => [$creating ? 'required' : 'nullable', 'string', 'min:8'],
            'department_id' => [
                'nullable',
                'string',
                Rule::exists('departments', 'code')->where('is_active', true),
            ],
            'roles' => ['required', 'array', 'min:1'],
            'roles.*' => ['string', 'exists:roles,key'],
        ]);
    }

    /** @param list<string> $roleKeys */
    private function syncAccess(User $user, array $roleKeys): void
    {
        $roleIds = Role::query()->whereIn('key', $roleKeys)->pluck('id');
        $user->roles()->sync($roleIds);
    }

    /** @return array<string, mixed> */
    private function validateRole(Request $request, bool $creating, ?Role $role = null): array
    {
        $keyRule = Rule::unique('roles', 'key');

        if ($role !== null) {
            $keyRule = $keyRule->ignore($role->id);
        }

        return $request->validate([
            'key' => [$creating ? 'required' : 'sometimes', 'string', 'max:50', 'regex:/^[a-z0-9][a-z0-9._-]*$/', $keyRule],
            'name' => ['required', 'string', 'max:100'],
            'scope' => ['required', Rule::in([Role::GlobalScope, Role::DepartmentScope])],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key', Rule::notIn([
                AccessManager::EmployeesManage,
                AccessManager::AccessManage,
            ])],
        ]);
    }

    /** @param list<string> $keys */
    private function permissionIds(array $keys): array
    {
        return Permission::query()->whereIn('key', $keys)->pluck('id')->all();
    }

    /** @return array<string, mixed> */
    private function userData(User $user, AccessManager $accessManager): array
    {
        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'department_id' => $user->department_id,
            'is_super_admin' => $user->is_super_admin,
            'roles' => $user->roles->map(fn (Role $role): array => [
                'key' => $role->key,
                'name' => $role->name,
                'scope' => $role->scope,
            ])->values(),
            'permissions' => $accessManager->permissionKeys($user),
        ];
    }

    /** @return array<string, mixed> */
    private function roleData(Role $role): array
    {
        return [
            'key' => $role->key,
            'name' => $role->name,
            'scope' => $role->scope,
            'is_system' => $role->is_system,
            'permissions' => $role->permissions->pluck('key')->values(),
        ];
    }
}
