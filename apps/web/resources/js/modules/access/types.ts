import type { SelectOption } from '@/shared/types/options';

export type RoleScope = 'global' | 'department';

export type AdminPermission = {
    key: string;
    name: string;
};

export type AdminRole = {
    key: string;
    name: string;
    scope: RoleScope;
    is_system: boolean;
    permissions: string[];
};

export type AdminEmployee = {
    id: number;
    name: string;
    email: string;
    department_id: string | null;
    is_super_admin: boolean;
    roles: Array<{ key: string; name: string; scope: RoleScope }>;
    permissions: string[];
};

export type AdminDepartment = {
    code: string;
    name: string;
    is_active: boolean;
    users_count: number;
    knowledge_count: number;
};

export type AdminMeta = {
    roles: AdminRole[];
    permissions: AdminPermission[];
    departments: SelectOption[];
    role_scopes: SelectOption[];
};

export type AdminEmployeesResponse = {
    data: AdminEmployee[];
    meta: AdminMeta;
};

export type AdminDepartmentsResponse = {
    data: AdminDepartment[];
};

export type DepartmentFormData = {
    code: string;
    name: string;
    is_active: boolean;
};

export type EmployeeFormData = {
    name: string;
    email: string;
    password?: string;
    department_id: string | null;
    roles: string[];
};

export type RoleFormData = {
    key: string;
    name: string;
    scope: RoleScope;
    permissions: string[];
};
