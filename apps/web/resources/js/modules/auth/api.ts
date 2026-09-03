import { apiRequest } from '@/shared/api/http';
import type { AuthUser } from '@/shared/types/auth';

export const authApi = {
    async currentUser(): Promise<AuthUser> {
        const response = await apiRequest<{ data: AuthUser }>('/api/auth/user');

        return response.data;
    },

    async login(credentials: {
        email: string;
        password: string;
        remember: boolean;
    }): Promise<AuthUser> {
        const response = await apiRequest<{ data: AuthUser }>(
            '/api/auth/login',
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify(credentials),
            },
        );

        return response.data;
    },

    async logout(): Promise<void> {
        await apiRequest('/api/auth/logout', { method: 'POST' });
    },
};
