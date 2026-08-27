<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { adminApi } from '@/modules/access/api';
import type {
    AdminEmployee,
    AdminMeta,
    EmployeeFormData,
} from '@/modules/access/types';

const emit = defineEmits<{
    notify: [message: string, color?: string];
}>();

const employees = ref<AdminEmployee[]>([]);
const meta = ref<AdminMeta | null>(null);
const isLoading = ref(false);
const isSaving = ref(false);
const activeTab = ref('employees');
const employeeDialogOpen = ref(false);
const editingEmployee = ref<AdminEmployee | null>(null);
const selectedRoleKey = ref('');
const selectedDepartmentId = ref('');
const rolePermissionDraft = ref<Record<string, string[]>>({});
const departmentPermissionDraft = ref<Record<string, string[]>>({});
const form = ref<EmployeeFormData>(emptyForm());

const selectedRole = computed(
    () =>
        meta.value?.roles.find((role) => role.key === selectedRoleKey.value) ??
        null,
);
const selectedDepartmentPermissions = computed(
    () => departmentPermissionDraft.value[selectedDepartmentId.value] ?? [],
);

function emptyForm(): EmployeeFormData {
    return {
        name: '',
        email: '',
        password: '',
        department_id: null,
        roles: [],
        permissions: [],
    };
}

function notify(message: string, color = 'success'): void {
    emit('notify', message, color);
}

async function load(): Promise<void> {
    isLoading.value = true;

    try {
        const response = await adminApi.employees();
        employees.value = response.data;
        meta.value = response.meta;
        rolePermissionDraft.value = Object.fromEntries(
            response.meta.roles.map((role) => [
                role.key,
                [...role.permissions],
            ]),
        );
        departmentPermissionDraft.value = {
            ...response.meta.department_permissions,
        };
        selectedRoleKey.value ||= response.meta.roles[0]?.key ?? '';
        selectedDepartmentId.value ||=
            response.meta.departments[0]?.value ?? '';
    } catch (error) {
        notify((error as Error).message, 'error');
    } finally {
        isLoading.value = false;
    }
}

function openCreate(): void {
    editingEmployee.value = null;
    form.value = emptyForm();
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
        permissions: [...employee.permissions],
    };
    employeeDialogOpen.value = true;
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

function togglePermission(
    target: string[],
    key: string,
    enabled: boolean,
): void {
    const index = target.indexOf(key);

    if (enabled && index === -1) {
        target.push(key);
    }

    if (!enabled && index !== -1) {
        target.splice(index, 1);
    }
}

function toggleRolePermission(
    key: string,
    permission: string,
    enabled: boolean,
): void {
    rolePermissionDraft.value[key] ??= [];
    togglePermission(rolePermissionDraft.value[key], permission, enabled);
}

function toggleDepartmentPermission(
    permission: string,
    enabled: boolean,
): void {
    departmentPermissionDraft.value[selectedDepartmentId.value] ??= [];
    togglePermission(
        departmentPermissionDraft.value[selectedDepartmentId.value],
        permission,
        enabled,
    );
}

async function saveRolePermissions(): Promise<void> {
    if (!selectedRole.value || !meta.value) {
        return;
    }

    try {
        const role = await adminApi.updateRole(
            selectedRole.value,
            rolePermissionDraft.value[selectedRole.value.key] ?? [],
        );
        const roleIndex = meta.value.roles.findIndex(
            (item) => item.key === role.key,
        );

        if (roleIndex !== -1) {
            meta.value.roles[roleIndex] = role;
        }

        notify('Права роли обновлены.');
    } catch (error) {
        notify((error as Error).message, 'error');
    }
}

async function saveDepartmentPermissions(): Promise<void> {
    if (!selectedDepartmentId.value) {
        return;
    }

    try {
        departmentPermissionDraft.value[selectedDepartmentId.value] =
            await adminApi.updateDepartment(
                selectedDepartmentId.value,
                selectedDepartmentPermissions.value,
            );
        notify('Права отдела обновлены.');
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
                                    meta?.departments.find(
                                        (department) =>
                                            department.value ===
                                            employee.department_id,
                                    )?.title || '—'
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
                <v-select
                    v-model="selectedRoleKey"
                    item-title="name"
                    item-value="key"
                    :items="meta?.roles ?? []"
                    label="Роль"
                    variant="outlined"
                />
                <div v-if="selectedRole" class="access-list">
                    <v-checkbox
                        v-for="permission in meta?.permissions ?? []"
                        :key="permission.key"
                        :label="permission.name"
                        :model-value="
                            rolePermissionDraft[selectedRole.key]?.includes(
                                permission.key,
                            )
                        "
                        hide-details
                        @update:model-value="
                            toggleRolePermission(
                                selectedRole.key,
                                permission.key,
                                $event === true,
                            )
                        "
                    />
                </div>
                <v-btn color="primary" @click="saveRolePermissions">
                    Сохранить права роли
                </v-btn>
            </v-window-item>

            <v-window-item value="departments" class="pa-5">
                <v-select
                    v-model="selectedDepartmentId"
                    item-title="title"
                    item-value="value"
                    :items="meta?.departments ?? []"
                    label="Отдел"
                    variant="outlined"
                />
                <div class="access-list">
                    <v-checkbox
                        v-for="permission in meta?.permissions ?? []"
                        :key="permission.key"
                        :label="permission.name"
                        :model-value="
                            selectedDepartmentPermissions.includes(
                                permission.key,
                            )
                        "
                        hide-details
                        @update:model-value="
                            toggleDepartmentPermission(
                                permission.key,
                                $event === true,
                            )
                        "
                    />
                </div>
                <v-btn color="primary" @click="saveDepartmentPermissions">
                    Сохранить права отдела
                </v-btn>
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
                <div class="access-list">
                    <div class="text-subtitle-2 mb-2">
                        Прямые права пользователя
                    </div>
                    <v-checkbox
                        v-for="permission in meta?.permissions ?? []"
                        :key="permission.key"
                        :label="permission.name"
                        :model-value="form.permissions.includes(permission.key)"
                        hide-details
                        @update:model-value="
                            togglePermission(
                                form.permissions,
                                permission.key,
                                $event === true,
                            )
                        "
                    />
                </div>
            </v-card-text>
            <v-card-actions class="dialog-actions">
                <v-btn variant="text" @click="employeeDialogOpen = false"
                    >Отмена</v-btn
                >
                <v-btn
                    color="primary"
                    :loading="isSaving"
                    @click="saveEmployee"
                >
                    Сохранить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
