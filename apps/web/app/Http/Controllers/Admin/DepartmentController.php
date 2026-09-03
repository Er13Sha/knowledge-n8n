<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Department;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class DepartmentController extends Controller
{
    public function index(): JsonResponse
    {
        return response()->json([
            'data' => Department::query()
                ->withCount(['users', 'knowledge'])
                ->orderBy('name')
                ->get()
                ->map(fn (Department $department): array => $this->departmentData($department))
                ->values(),
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $validated = $this->validateDepartment($request);
        $department = Department::query()->create([
            'code' => strtolower(trim($validated['code'])),
            'name' => trim($validated['name']),
            'is_active' => $validated['is_active'] ?? true,
        ]);

        return response()->json(['data' => $this->departmentData($department)], Response::HTTP_CREATED);
    }

    public function update(Request $request, Department $department): JsonResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['required', 'boolean'],
        ]);

        $department->update([
            'name' => trim($validated['name']),
            'is_active' => $validated['is_active'],
        ]);

        return response()->json(['data' => $this->departmentData($department->refresh())]);
    }

    public function destroy(Department $department): JsonResponse
    {
        abort_if(
            $department->users()->exists() || $department->knowledge()->exists(),
            Response::HTTP_CONFLICT,
            'Нельзя удалить отдел, к которому привязаны сотрудники или документы. Деактивируйте его вместо удаления.',
        );

        $department->delete();

        return response()->json(status: Response::HTTP_NO_CONTENT);
    }

    /** @return array<string, mixed> */
    private function departmentData(Department $department): array
    {
        return [
            'code' => $department->code,
            'name' => $department->name,
            'is_active' => $department->is_active,
            'users_count' => $department->users_count ?? $department->users()->count(),
            'knowledge_count' => $department->knowledge_count ?? $department->knowledge()->count(),
        ];
    }

    /** @return array<string, mixed> */
    private function validateDepartment(Request $request): array
    {
        $request->merge([
            'code' => strtolower(trim((string) $request->input('code'))),
            'name' => trim((string) $request->input('name')),
        ]);

        return $request->validate([
            'code' => [
                'required',
                'string',
                'max:100',
                'regex:/^[a-z0-9][a-z0-9_-]*$/',
                Rule::unique('departments', 'code'),
            ],
            'name' => ['required', 'string', 'max:255'],
            'is_active' => ['sometimes', 'boolean'],
        ]);
    }
}
