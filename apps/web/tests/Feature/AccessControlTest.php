<?php

use App\Models\Knowledge;
use App\Models\KnowledgeDocument;
use App\Models\Role;
use App\Models\User;

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
        ->assertJsonPath('meta.roles.0.scope', 'department')
        ->assertJsonPath('meta.roles.1.key', 'admin')
        ->assertJsonPath('meta.roles.1.scope', 'global');
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
        ])
        ->assertCreated()
        ->assertJsonPath('data.department_id', 'hr')
        ->assertJsonPath('data.roles.0.key', 'employee');

    $employee = User::query()->where('email', 'hr.employee@example.com')->firstOrFail();

    $this->actingAs($admin)
        ->patchJson(route('api.admin.employees.update', $employee), [
            'name' => 'Сотрудник HR',
            'email' => 'hr.employee@example.com',
            'department_id' => 'legal',
            'roles' => ['admin'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Сотрудник HR')
        ->assertJsonPath('data.department_id', 'legal')
        ->assertJsonPath('data.roles.0.key', 'admin')
        ->assertJsonPath('data.permissions.0', 'knowledge.read');

    $this->assertDatabaseHas('users', [
        'id' => $employee->id,
        'department_id' => 'legal',
    ]);

    expect($response->json('data.id'))->toBe($employee->id);
});

test('main administrator can create, update and delete a custom role', function () {
    $admin = createSuperAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.admin.roles.store'), [
            'key' => 'legal_reviewer',
            'name' => 'Проверяющий юрист',
            'scope' => Role::DepartmentScope,
            'permissions' => ['knowledge.read'],
        ])
        ->assertCreated()
        ->assertJsonPath('data.scope', Role::DepartmentScope);

    $role = Role::query()->where('key', 'legal_reviewer')->firstOrFail();

    $this->actingAs($admin)
        ->putJson(route('api.admin.roles.update', $role), [
            'key' => 'legal_reviewer',
            'name' => 'Старший проверяющий юрист',
            'scope' => Role::GlobalScope,
            'permissions' => ['knowledge.read', 'knowledge.update'],
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Старший проверяющий юрист')
        ->assertJsonPath('data.scope', Role::GlobalScope)
        ->assertJsonFragment(['permissions' => ['knowledge.read', 'knowledge.update']]);

    $this->actingAs($admin)
        ->deleteJson(route('api.admin.roles.destroy', $role))
        ->assertNoContent();

    $this->assertDatabaseMissing('roles', ['key' => 'legal_reviewer']);
});

test('custom roles cannot receive protected administration permissions', function () {
    $admin = createSuperAdmin();

    $this->actingAs($admin)
        ->postJson(route('api.admin.roles.store'), [
            'key' => 'unsafe',
            'name' => 'Небезопасная роль',
            'scope' => Role::GlobalScope,
            'permissions' => ['employees.manage'],
        ])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('permissions.0');
});

test('system roles cannot be changed or deleted', function () {
    $admin = createSuperAdmin();
    $role = Role::query()->where('key', 'employee')->firstOrFail();

    $this->actingAs($admin)
        ->putJson(route('api.admin.roles.update', $role), [
            'name' => 'Изменённая роль',
            'scope' => Role::GlobalScope,
            'permissions' => [],
        ])
        ->assertForbidden();

    $this->actingAs($admin)
        ->deleteJson(route('api.admin.roles.destroy', $role))
        ->assertForbidden();
});

test('department scoped role exposes documents from the users department', function () {
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

    $this->actingAs($viewer)
        ->getJson(route('api.knowledge.documents.index'))
        ->assertOk()
        ->assertJsonFragment(['id' => $legalDocument->id])
        ->assertJsonMissing(['id' => $financeDocument->id]);
});

test('global role can see documents from every department', function () {
    $viewer = User::factory()->create(['department_id' => 'legal']);
    $viewer->roles()->sync([Role::query()->where('key', 'admin')->value('id')]);

    $legalDocument = KnowledgeDocument::factory()->indexed()->create();
    $financeDocument = KnowledgeDocument::factory()->indexed()->create();

    $this->actingAs($viewer)
        ->getJson(route('api.knowledge.documents.index'))
        ->assertOk()
        ->assertJsonFragment(['id' => $legalDocument->id])
        ->assertJsonFragment(['id' => $financeDocument->id]);
});
