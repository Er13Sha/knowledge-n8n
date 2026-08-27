import { apiRequest } from '@/shared/api/http';
import type {
    AdminEmployeesResponse,
    AdminEmployee,
    AdminRole,
    EmployeeFormData,
} from './types';

export const adminApi = {
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

    async updateRole(
        role: AdminRole,
        permissions: string[],
    ): Promise<AdminRole> {
        const response = await apiRequest<{ data: AdminRole }>(
            `/api/admin/roles/${role.key}/permissions`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ permissions }),
            },
        );

        return response.data;
    },

    async updateDepartment(
        departmentId: string,
        permissions: string[],
    ): Promise<string[]> {
        const response = await apiRequest<{ data: string[] }>(
            `/api/admin/departments/${departmentId}/permissions`,
            {
                method: 'PUT',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ permissions }),
            },
        );

        return response.data;
    },
};
