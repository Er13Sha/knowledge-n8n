import type { SelectOption } from '@/shared/types/options';

export type AdminPermission = {
    key: string;
    name: string;
};

export type AdminRole = {
    key: string;
    name: string;
    permissions: string[];
};

export type AdminEmployee = {
    id: number;
    name: string;
    email: string;
    department_id: string | null;
    is_super_admin: boolean;
    roles: Array<{ key: string; name: string }>;
    permissions: string[];
};

export type AdminMeta = {
    roles: AdminRole[];
    permissions: AdminPermission[];
    departments: SelectOption[];
    department_permissions: Record<string, string[]>;
};

export type AdminEmployeesResponse = {
    data: AdminEmployee[];
    meta: AdminMeta;
};

export type EmployeeFormData = {
    name: string;
    email: string;
    password?: string;
    department_id: string | null;
    roles: string[];
    permissions: string[];
};
