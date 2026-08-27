<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { adminApi } from '@/modules/access/api';
import type {
    AdminDepartment,
    AdminEmployee,
    AdminMeta,
    AdminRole,
    DepartmentFormData,
    EmployeeFormData,
    RoleFormData,
} from '@/modules/access/types';

const emit = defineEmits<{
    notify: [message: string, color?: string];
}>();

const employees = ref<AdminEmployee[]>([]);
const departments = ref<AdminDepartment[]>([]);
const meta = ref<AdminMeta | null>(null);
const isLoading = ref(false);
const isSaving = ref(false);
const activeTab = ref('employees');
const employeeDialogOpen = ref(false);
const roleDialogOpen = ref(false);
const departmentDialogOpen = ref(false);
const editingEmployee = ref<AdminEmployee | null>(null);
const editingRole = ref<AdminRole | null>(null);
const editingDepartment = ref<AdminDepartment | null>(null);
const form = ref<EmployeeFormData>(emptyEmployeeForm());
const roleForm = ref<RoleFormData>(emptyRoleForm());
const departmentForm = ref<DepartmentFormData>(emptyDepartmentForm());

function emptyEmployeeForm(): EmployeeFormData {
    return {
        name: '',
        email: '',
        password: '',
        department_id: null,
        roles: [],
    };
}

function emptyRoleForm(): RoleFormData {
    return {
        key: '',
        name: '',
        scope: 'department',
        permissions: [],
    };
}

function emptyDepartmentForm(): DepartmentFormData {
    return {
        code: '',
        name: '',
        is_active: true,
    };
}

function notify(message: string, color = 'success'): void {
    emit('notify', message, color);
}

async function load(): Promise<void> {
    isLoading.value = true;

    try {
        const [employeesResponse, departmentsResponse] = await Promise.all([
            adminApi.employees(),
            adminApi.departments(),
        ]);
        employees.value = employeesResponse.data;
        meta.value = employeesResponse.meta;
        departments.value = departmentsResponse.data;
    } catch (error) {
        notify((error as Error).message, 'error');
    } finally {
        isLoading.value = false;
    }
}

function openCreate(): void {
    editingEmployee.value = null;
    form.value = emptyEmployeeForm();
    employeeDialogOpen.value = true;
}

function openEdit(employee: AdminEmployee): void {
    editingEmployee.value = employee;
    form.value = {
        name: employee.name,
        email: employee.email,
        password: '',
        department_id: employee.department_id,
        roles: employee.roles.map((role) => role.key),
    };
    employeeDialogOpen.value = true;
}

function openCreateRole(): void {
    editingRole.value = null;
    roleForm.value = emptyRoleForm();
    roleDialogOpen.value = true;
}

function openEditRole(role: AdminRole): void {
    if (role.is_system) {
        return;
    }

    editingRole.value = role;
    roleForm.value = {
        key: role.key,
        name: role.name,
        scope: role.scope,
        permissions: [...role.permissions],
    };
    roleDialogOpen.value = true;
}

function openCreateDepartment(): void {
    editingDepartment.value = null;
    departmentForm.value = emptyDepartmentForm();
    departmentDialogOpen.value = true;
}

function openEditDepartment(department: AdminDepartment): void {
    editingDepartment.value = department;
    departmentForm.value = {
        code: department.code,
        name: department.name,
        is_active: department.is_active,
    };
    departmentDialogOpen.value = true;
}

async function saveEmployee(): Promise<void> {
    isSaving.value = true;

    try {
        const payload = {
            ...form.value,
            password: form.value.password || undefined,
        };
        const employee = editingEmployee.value
            ? await adminApi.updateEmployee(editingEmployee.value.id, payload)
            : await adminApi.createEmployee(payload);
        const index = employees.value.findIndex(
            (item) => item.id === employee.id,
        );

        if (index === -1) {
            employees.value.push(employee);
        } else {
            employees.value[index] = employee;
        }

        employeeDialogOpen.value = false;
        notify('Сотрудник сохранён.');
    } catch (error) {
        notify((error as Error).message, 'error');
    } finally {
        isSaving.value = false;
    }
}

async function saveRole(): Promise<void> {
    if (!meta.value) {
        return;
    }

    isSaving.value = true;

    try {
        const role = editingRole.value
            ? await adminApi.updateRole(editingRole.value, roleForm.value)
            : await adminApi.createRole(roleForm.value);
        const index = meta.value.roles.findIndex(
            (item) => item.key === role.key,
        );

        if (index === -1) {
            meta.value.roles.push(role);
        } else {
            meta.value.roles[index] = role;
        }

        roleDialogOpen.value = false;
        notify('Роль сохранена.');
    } catch (error) {
        notify((error as Error).message, 'error');
    } finally {
        isSaving.value = false;
    }
}

async function deleteRole(role: AdminRole): Promise<void> {
    if (role.is_system || !window.confirm(`Удалить роль «${role.name}»?`)) {
        return;
    }

    try {
        await adminApi.deleteRole(role);
        if (meta.value) {
            meta.value.roles = meta.value.roles.filter(
                (item) => item.key !== role.key,
            );
        }
        notify('Роль удалена.');
    } catch (error) {
        notify((error as Error).message, 'error');
    }
}

async function saveDepartment(): Promise<void> {
    isSaving.value = true;

    try {
        if (editingDepartment.value) {
            await adminApi.updateDepartment(
                editingDepartment.value.code,
                departmentForm.value,
            );
        } else {
            await adminApi.createDepartment(departmentForm.value);
        }

        departmentDialogOpen.value = false;
        await load();
        notify('Отдел сохранён.');
    } catch (error) {
        notify((error as Error).message, 'error');
    } finally {
        isSaving.value = false;
    }
}

async function deleteDepartment(department: AdminDepartment): Promise<void> {
    if (
        department.users_count > 0 ||
        department.knowledge_count > 0 ||
        !window.confirm(`Удалить отдел «${department.name}»?`)
    ) {
        return;
    }

    try {
        await adminApi.deleteDepartment(department.code);
        await load();
        notify('Отдел удалён.');
    } catch (error) {
        notify((error as Error).message, 'error');
    }
}

onMounted(load);
</script>

<template>
    <header class="page-heading">
        <div>
            <h1>Сотрудники и доступы</h1>
            <p>Управление ролями и правами базы знаний</p>
        </div>
        <v-btn
            color="primary"
            prepend-icon="mdi-account-plus"
            @click="openCreate"
        >
            Добавить сотрудника
        </v-btn>
    </header>

    <v-card class="admin-panel" border>
        <v-tabs v-model="activeTab" color="primary">
            <v-tab value="employees">Сотрудники</v-tab>
            <v-tab value="roles">Роли</v-tab>
            <v-tab value="departments">Отделы</v-tab>
        </v-tabs>
        <v-divider />

        <v-window v-model="activeTab">
            <v-window-item value="employees">
                <v-progress-linear
                    v-if="isLoading"
                    color="primary"
                    indeterminate
                />
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Сотрудник</th>
                            <th>Отдел</th>
                            <th>Роли</th>
                            <th>Права</th>
                            <th aria-label="Действия" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="employee in employees" :key="employee.id">
                            <td>
                                <strong>{{ employee.name }}</strong>
                                <div class="table-secondary">
                                    {{ employee.email }}
                                </div>
                            </td>
                            <td>
                                {{
                                    departments.find(
                                        (department) =>
                                            department.code ===
                                            employee.department_id,
                                    )?.name || '—'
                                }}
                            </td>
                            <td>
                                <v-chip
                                    v-for="role in employee.roles"
                                    :key="role.key"
                                    class="mr-1"
                                    size="small"
                                    variant="tonal"
                                >
                                    {{ role.name }}
                                </v-chip>
                                <v-chip
                                    v-if="employee.is_super_admin"
                                    color="error"
                                    size="small"
                                >
                                    Главный админ
                                </v-chip>
                            </td>
                            <td>{{ employee.permissions.length }}</td>
                            <td class="text-right">
                                <v-btn
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEdit(employee)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-window-item>

            <v-window-item value="roles" class="pa-5">
                <div class="d-flex justify-space-between align-center mb-4">
                    <div>
                        <h3>Кастомные роли</h3>
                        <p class="table-secondary">
                            Системные роли нельзя изменить или удалить.
                        </p>
                    </div>
                    <v-btn color="primary" @click="openCreateRole">
                        Добавить роль
                    </v-btn>
                </div>
                <v-list lines="two">
                    <v-list-item
                        v-for="role in meta?.roles ?? []"
                        :key="role.key"
                    >
                        <template #prepend>
                            <v-icon
                                :icon="
                                    role.scope === 'global'
                                        ? 'mdi-earth'
                                        : 'mdi-domain'
                                "
                            />
                        </template>
                        <v-list-item-title>{{ role.name }}</v-list-item-title>
                        <v-list-item-subtitle>
                            {{ role.key }} ·
                            {{
                                role.scope === 'global'
                                    ? 'все отделы'
                                    : 'отдел пользователя'
                            }}
                            · {{ role.permissions.length }} прав
                        </v-list-item-subtitle>
                        <template #append>
                            <v-btn
                                icon="mdi-pencil-outline"
                                size="small"
                                variant="text"
                                :disabled="role.is_system"
                                @click="openEditRole(role)"
                            />
                            <v-btn
                                icon="mdi-delete-outline"
                                size="small"
                                variant="text"
                                color="error"
                                :disabled="role.is_system"
                                @click="deleteRole(role)"
                            />
                        </template>
                    </v-list-item>
                </v-list>
            </v-window-item>

            <v-window-item value="departments" class="pa-5">
                <div class="d-flex justify-space-between align-center mb-4">
                    <div>
                        <h3>Отделы</h3>
                        <p class="table-secondary">
                            Отделы этой организации и их использование в
                            системе.
                        </p>
                    </div>
                    <v-btn
                        color="primary"
                        prepend-icon="mdi-office-building-plus-outline"
                        @click="openCreateDepartment"
                    >
                        Добавить отдел
                    </v-btn>
                </div>
                <v-progress-linear
                    v-if="isLoading"
                    color="primary"
                    indeterminate
                />
                <v-table density="comfortable">
                    <thead>
                        <tr>
                            <th>Код</th>
                            <th>Название</th>
                            <th>Сотрудники</th>
                            <th>Документы</th>
                            <th>Статус</th>
                            <th aria-label="Действия" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr
                            v-for="department in departments"
                            :key="department.code"
                        >
                            <td>
                                <code>{{ department.code }}</code>
                            </td>
                            <td>
                                <strong>{{ department.name }}</strong>
                            </td>
                            <td>{{ department.users_count }}</td>
                            <td>{{ department.knowledge_count }}</td>
                            <td>
                                <v-chip
                                    :color="
                                        department.is_active
                                            ? 'success'
                                            : 'default'
                                    "
                                    size="small"
                                    variant="tonal"
                                >
                                    {{
                                        department.is_active
                                            ? 'Активен'
                                            : 'Выключен'
                                    }}
                                </v-chip>
                            </td>
                            <td class="text-right">
                                <v-btn
                                    icon="mdi-pencil-outline"
                                    size="small"
                                    variant="text"
                                    @click="openEditDepartment(department)"
                                />
                                <v-btn
                                    color="error"
                                    icon="mdi-delete-outline"
                                    size="small"
                                    variant="text"
                                    :disabled="
                                        department.users_count > 0 ||
                                        department.knowledge_count > 0
                                    "
                                    title="Сначала отвяжите сотрудников и документы"
                                    @click="deleteDepartment(department)"
                                />
                            </td>
                        </tr>
                    </tbody>
                </v-table>
            </v-window-item>
        </v-window>
    </v-card>

    <v-dialog v-model="employeeDialogOpen" max-width="620">
        <v-card rounded="lg">
            <v-card-title class="dialog-title">
                <strong>{{
                    editingEmployee
                        ? 'Редактировать сотрудника'
                        : 'Добавить сотрудника'
                }}</strong>
                <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="employeeDialogOpen = false"
                />
            </v-card-title>
            <v-divider />
            <v-card-text class="dialog-body">
                <v-text-field
                    v-model="form.name"
                    label="Имя"
                    required
                    variant="outlined"
                />
                <v-text-field
                    v-model="form.email"
                    label="Email"
                    required
                    type="email"
                    variant="outlined"
                />
                <v-text-field
                    v-model="form.password"
                    :label="
                        editingEmployee
                            ? 'Новый пароль (необязательно)'
                            : 'Пароль'
                    "
                    :required="!editingEmployee"
                    type="password"
                    variant="outlined"
                />
                <v-select
                    v-model="form.department_id"
                    clearable
                    item-title="title"
                    item-value="value"
                    :items="meta?.departments ?? []"
                    label="Отдел"
                    variant="outlined"
                />
                <v-select
                    v-model="form.roles"
                    chips
                    item-title="name"
                    item-value="key"
                    :items="meta?.roles ?? []"
                    label="Роли"
                    multiple
                    variant="outlined"
                />
            </v-card-text>
            <v-card-actions class="dialog-actions">
                <v-btn variant="text" @click="employeeDialogOpen = false"
                    >Отмена</v-btn
                >
                <v-btn color="primary" :loading="isSaving" @click="saveEmployee"
                    >Сохранить</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="roleDialogOpen" max-width="620">
        <v-card rounded="lg">
            <v-card-title class="dialog-title">
                <strong>{{
                    editingRole ? 'Редактировать роль' : 'Добавить роль'
                }}</strong>
                <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="roleDialogOpen = false"
                />
            </v-card-title>
            <v-divider />
            <v-card-text class="dialog-body">
                <v-text-field
                    v-model="roleForm.key"
                    label="Ключ"
                    :disabled="!!editingRole"
                    variant="outlined"
                />
                <v-text-field
                    v-model="roleForm.name"
                    label="Название"
                    required
                    variant="outlined"
                />
                <v-select
                    v-model="roleForm.scope"
                    item-title="title"
                    item-value="value"
                    :items="meta?.role_scopes ?? []"
                    label="Область доступа"
                    variant="outlined"
                />
                <div class="access-list">
                    <div class="text-subtitle-2 mb-2">Права роли</div>
                    <v-checkbox
                        v-for="permission in meta?.permissions ?? []"
                        :key="permission.key"
                        v-model="roleForm.permissions"
                        :label="permission.name"
                        :value="permission.key"
                        hide-details
                        :disabled="
                            permission.key === 'employees.manage' ||
                            permission.key === 'access.manage'
                        "
                    />
                </div>
            </v-card-text>
            <v-card-actions class="dialog-actions">
                <v-btn variant="text" @click="roleDialogOpen = false"
                    >Отмена</v-btn
                >
                <v-btn color="primary" :loading="isSaving" @click="saveRole"
                    >Сохранить</v-btn
                >
            </v-card-actions>
        </v-card>
    </v-dialog>

    <v-dialog v-model="departmentDialogOpen" max-width="520">
        <v-card rounded="lg">
            <v-card-title class="dialog-title">
                <strong>{{
                    editingDepartment ? 'Редактировать отдел' : 'Добавить отдел'
                }}</strong>
                <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="departmentDialogOpen = false"
                />
            </v-card-title>
            <v-divider />
            <v-card-text class="dialog-body">
                <v-text-field
                    v-model="departmentForm.code"
                    :disabled="!!editingDepartment"
                    hint="Латинские буквы, цифры, дефис или подчёркивание"
                    label="Код"
                    persistent-hint
                    required
                    variant="outlined"
                />
                <v-text-field
                    v-model="departmentForm.name"
                    label="Название"
                    required
                    variant="outlined"
                />
                <v-switch
                    v-model="departmentForm.is_active"
                    color="primary"
                    hide-details
                    label="Активный отдел"
                />
            </v-card-text>
            <v-card-actions class="dialog-actions">
                <v-btn variant="text" @click="departmentDialogOpen = false">
                    Отмена
                </v-btn>
                <v-btn
                    color="primary"
                    :loading="isSaving"
                    @click="saveDepartment"
                >
                    Сохранить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
