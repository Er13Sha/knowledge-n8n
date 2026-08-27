import { apiRequest } from '@/shared/api/http';
import type {
    AdminDepartment,
    AdminDepartmentsResponse,
    AdminEmployeesResponse,
    AdminEmployee,
    AdminRole,
    DepartmentFormData,
    EmployeeFormData,
    RoleFormData,
} from './types';

export const adminApi = {
    async departments(): Promise<AdminDepartmentsResponse> {
        return apiRequest<AdminDepartmentsResponse>('/api/admin/departments');
    },

    async createDepartment(data: DepartmentFormData): Promise<AdminDepartment> {
        const response = await apiRequest<{ data: AdminDepartment }>(
            '/api/admin/departments',
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            },
        );

        return response.data;
    },

    async updateDepartment(
        code: string,
        data: DepartmentFormData,
    ): Promise<AdminDepartment> {
        const response = await apiRequest<{ data: AdminDepartment }>(
            `/api/admin/departments/${code}`,
            {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: data.name,
                    is_active: data.is_active,
                }),
            },
        );

        return response.data;
    },

    async deleteDepartment(code: string): Promise<void> {
        await apiRequest(`/api/admin/departments/${code}`, {
            method: 'DELETE',
        });
    },

    async employees(): Promise<AdminEmployeesResponse> {
        return apiRequest<AdminEmployeesResponse>('/api/admin/employees');
    },

    async createEmployee(data: EmployeeFormData): Promise<AdminEmployee> {
        const response = await apiRequest<{ data: AdminEmployee }>(
            '/api/admin/employees',
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            },
        );

        return response.data;
    },

    async updateEmployee(
        employeeId: number,
        data: EmployeeFormData,
    ): Promise<AdminEmployee> {
        const response = await apiRequest<{ data: AdminEmployee }>(
            `/api/admin/employees/${employeeId}`,
            {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            },
        );

        return response.data;
    },

    async createRole(data: RoleFormData): Promise<AdminRole> {
        const response = await apiRequest<{ data: AdminRole }>(
            '/api/admin/roles',
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            },
        );

        return response.data;
    },

    async updateRole(role: AdminRole, data: RoleFormData): Promise<AdminRole> {
        const response = await apiRequest<{ data: AdminRole }>(
            `/api/admin/roles/${role.key}`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(data),
            },
        );

        return response.data;
    },

    async deleteRole(role: AdminRole): Promise<void> {
        await apiRequest(`/api/admin/roles/${role.key}`, { method: 'DELETE' });
    },
};
