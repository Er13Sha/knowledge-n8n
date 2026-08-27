<?php

use App\Models\Knowledge;
use App\Models\KnowledgeDocument;
use App\Models\User;
use Illuminate\Support\Facades\DB;

function createSuperAdmin(): User
{
    $user = User::factory()->create();
    $user->forceFill(['is_super_admin' => true])->save();

    return $user->fresh();
}

test('only the main administrator can manage employees and access settings', function () {
    $employee = User::factory()->create();
    $admin = createSuperAdmin();

    $this->actingAs($employee)
        ->getJson(route('api.admin.employees.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson(route('api.admin.employees.index'))
        ->assertOk()
        ->assertJsonPath('meta.roles.0.key', 'employee')
        ->assertJsonPath('meta.roles.1.key', 'admin');
});

test('main administrator can create and update an employee with a role', function () {
    $admin = createSuperAdmin();

    $response = $this->actingAs($admin)
        ->postJson(route('api.admin.employees.store'), [
            'name' => 'Сотрудник отдела кадров',
            'email' => 'hr.employee@example.com',
            'password' => 'password',
            'department_id' => 'hr',
            'roles' => ['employee'],
            'permissions' => [],
        ])
        ->assertCreated()
        ->assertJsonPath('data.department_id', 'hr')
        ->assertJsonPath('data.roles.0.key', 'employee');

    $employee = User::query()->where('email', 'hr.employee@example.com')->firstOrFail();

    $this->assertDatabaseHas('users', [
        'id' => $employee->id,
        'department_id' => 'hr',
    ]);

    $this->actingAs($admin)
        ->patchJson(route('api.admin.employees.update', $employee), [
            'name' => 'Сотрудник HR',
            'email' => 'hr.employee@example.com',
            'department_id' => 'legal',
            'roles' => ['admin'],
            'permissions' => ['knowledge.read'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Сотрудник HR')
        ->assertJsonPath('data.department_id', 'legal')
        ->assertJsonPath('data.roles.0.key', 'admin')
        ->assertJsonPath('data.permissions.0', 'knowledge.read');

    expect($response->json('data.id'))->toBe($employee->id);
});

test('main administrator can configure role and department permissions', function () {
    $admin = createSuperAdmin();

    $this->actingAs($admin)
        ->putJson(route('api.admin.roles.permissions.update', 'employee'), [
            'permissions' => ['knowledge.read'],
        ])
        ->assertOk()
        ->assertJsonPath('data.key', 'employee')
        ->assertJsonPath('data.permissions.0', 'knowledge.read');

    $this->actingAs($admin)
        ->putJson(route('api.admin.departments.permissions.update', 'legal'), [
            'permissions' => ['knowledge.read'],
        ])
        ->assertOk()
        ->assertJsonPath('data.0', 'knowledge.read');

    $this->assertDatabaseHas('department_permissions', [
        'department_id' => 'legal',
    ]);
});

test('department read permission exposes documents from that department', function () {
    $owner = User::factory()->create(['department_id' => 'legal']);
    $viewer = User::factory()->create(['department_id' => 'legal']);
    $otherDepartmentUser = User::factory()->create(['department_id' => 'finance']);

    $legalKnowledge = Knowledge::query()->create([
        'user_id' => $owner->id,
        'department_id' => 'legal',
        'title' => 'Legal policy',
        'doc_type' => 'policy',
        'status' => 'indexed',
        'approved_at' => '2026-08-20',
    ]);
    $financeKnowledge = Knowledge::query()->create([
        'user_id' => $otherDepartmentUser->id,
        'department_id' => 'finance',
        'title' => 'Finance policy',
        'doc_type' => 'policy',
        'status' => 'indexed',
        'approved_at' => '2026-08-20',
    ]);

    $legalDocument = KnowledgeDocument::factory()->for($owner)->create([
        'knowledge_id' => $legalKnowledge->id,
        'original_name' => 'legal.pdf',
    ]);
    $financeDocument = KnowledgeDocument::factory()->for($otherDepartmentUser)->create([
        'knowledge_id' => $financeKnowledge->id,
        'original_name' => 'finance.pdf',
    ]);

    $permissionId = DB::table('permissions')->where('key', 'knowledge.read')->value('id');
    DB::table('department_permissions')->insert([
        'department_id' => 'legal',
        'permission_id' => $permissionId,
    ]);

    $this->actingAs($viewer)
        ->getJson(route('api.knowledge.documents.index'))
        ->assertOk()
        ->assertJsonFragment(['id' => $legalDocument->id])
        ->assertJsonMissing(['id' => $financeDocument->id]);
});
