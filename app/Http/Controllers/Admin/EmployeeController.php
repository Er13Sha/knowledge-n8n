<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Knowledge;
use App\Models\Permission;
use App\Models\Role;
use App\Models\User;
use App\Services\Access\AccessManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(AccessManager $accessManager): JsonResponse
    {
        return response()->json([
            'data' => User::query()
                ->with(['roles', 'directPermissions'])
                ->orderBy('name')
                ->get()
                ->map(fn (User $user): array => $this->userData($user))
                ->values(),
            'meta' => [
                'roles' => Role::query()
                    ->with('permissions')
                    ->orderBy('id')
                    ->get()
                    ->map(fn (Role $role): array => $this->roleData($role))
                    ->values(),
                'permissions' => $accessManager->permissions(),
                'departments' => Knowledge::DepartmentOptions,
                'department_permissions' => $this->departmentPermissions(),
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateEmployee($request, true);
        $user = DB::transaction(function () use ($validated): User {
            $user = User::query()->create([
                'name' => $validated['name'],
                'email' => $validated['email'],
                'password' => $validated['password'],
                'department_id' => $validated['department_id'] ?? null,
            ]);

            $this->syncAccess($user, $validated);

            return $user->fresh(['roles', 'directPermissions']);
        });

        return response()->json(['data' => $this->userData($user)], 201);
    }

    public function update(Request $request, User $user): JsonResponse
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
            $this->syncAccess($user, $validated);
        });

        return response()->json(['data' => $this->userData($user->fresh(['roles', 'directPermissions']))]);
    }

    public function updateRole(Request $request, Role $role): JsonResponse
    {
        $permissionIds = $this->permissionIds($request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ])['permissions'] ?? []);

        $role->permissions()->sync($permissionIds);

        return response()->json(['data' => $this->roleData($role->fresh('permissions'))]);
    }

    public function updateDepartment(Request $request, string $department): JsonResponse
    {
        abort_unless(
            in_array($department, array_column(Knowledge::DepartmentOptions, 'value'), true),
            404,
        );

        $permissionIds = $this->permissionIds($request->validate([
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ])['permissions'] ?? []);

        DB::transaction(function () use ($department, $permissionIds): void {
            DB::table('department_permissions')->where('department_id', $department)->delete();

            if ($permissionIds !== []) {
                DB::table('department_permissions')->insert(array_map(
                    fn (int $permissionId): array => [
                        'department_id' => $department,
                        'permission_id' => $permissionId,
                    ],
                    $permissionIds,
                ));
            }
        });

        return response()->json(['data' => $this->departmentPermissions()[$department] ?? []]);
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
                Rule::in(array_column(Knowledge::DepartmentOptions, 'value')),
            ],
            'roles' => ['array'],
            'roles.*' => ['string', 'exists:roles,key'],
            'permissions' => ['array'],
            'permissions.*' => ['string', 'exists:permissions,key'],
        ]);
    }

    /** @param array<string, mixed> $validated */
    private function syncAccess(User $user, array $validated): void
    {
        $roleIds = Role::query()->whereIn('key', $validated['roles'] ?? [])->pluck('id');
        $permissionIds = $this->permissionIds($validated['permissions'] ?? []);

        $user->roles()->sync($roleIds);
        $user->directPermissions()->sync($permissionIds);
    }

    /** @param list<string> $keys */
    private function permissionIds(array $keys): array
    {
        return Permission::query()->whereIn('key', $keys)->pluck('id')->all();
    }

    /** @return array<string, mixed> */
    private function userData(User $user): array
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
            ])->values(),
            'permissions' => $user->directPermissions->pluck('key')->values(),
        ];
    }

    /** @return array<string, mixed> */
    private function roleData(Role $role): array
    {
        return [
            'key' => $role->key,
            'name' => $role->name,
            'permissions' => $role->permissions->pluck('key')->values(),
        ];
    }

    /** @return array<string, list<string>> */
    private function departmentPermissions(): array
    {
        return DB::table('department_permissions')
            ->join('permissions', 'permissions.id', '=', 'department_permissions.permission_id')
            ->get(['department_id', 'permissions.key'])
            ->groupBy('department_id')
            ->map(fn ($permissions): array => $permissions->pluck('key')->values()->all())
            ->all();
    }
}
