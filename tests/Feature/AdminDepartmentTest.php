<?php

use App\Models\Department;
use App\Models\User;

test('only the main administrator can manage departments', function () {
    $employee = User::factory()->create();
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($employee)
        ->getJson(route('api.admin.departments.index'))
        ->assertForbidden();

    $this->actingAs($admin)
        ->getJson(route('api.admin.departments.index'))
        ->assertOk()
        ->assertJsonFragment([
            'code' => 'legal',
            'name' => 'Юридический отдел',
            'is_active' => true,
        ]);
});

test('main administrator can create update and delete an unused department', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();

    $this->actingAs($admin)
        ->postJson(route('api.admin.departments.store'), [
            'code' => 'SALES',
            'name' => 'Отдел продаж',
            'is_active' => true,
        ])
        ->assertCreated()
        ->assertJsonPath('data.code', 'sales')
        ->assertJsonPath('data.name', 'Отдел продаж')
        ->assertJsonPath('data.is_active', true);

    $this->actingAs($admin)
        ->patchJson(route('api.admin.departments.update', 'sales'), [
            'name' => 'Коммерческий отдел',
            'is_active' => false,
        ])
        ->assertOk()
        ->assertJsonPath('data.name', 'Коммерческий отдел')
        ->assertJsonPath('data.is_active', false);

    $this->actingAs($admin)
        ->deleteJson(route('api.admin.departments.destroy', 'sales'))
        ->assertNoContent();

    $this->assertDatabaseMissing('departments', ['code' => 'sales']);
});

test('used department cannot be deleted', function () {
    $admin = User::factory()->create();
    $admin->forceFill(['is_super_admin' => true])->save();
    User::factory()->create(['department_id' => 'legal']);

    $this->actingAs($admin)
        ->deleteJson(route('api.admin.departments.destroy', 'legal'))
        ->assertStatus(409)
        ->assertJsonPath('message', 'Нельзя удалить отдел, к которому привязаны сотрудники или документы. Деактивируйте его вместо удаления.');

    expect(Department::query()->where('code', 'legal')->exists())->toBeTrue();
});
