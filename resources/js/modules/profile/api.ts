import settingsRoutes from '@/routes/api/settings';
import { apiRequest } from '@/shared/api/http';
import type { AuthUser } from '@/shared/types/auth';

export const settingsApi = {
    async updateProfile(name: string, email: string): Promise<AuthUser> {
        const response = await apiRequest<{ data: AuthUser }>(
            settingsRoutes.profile.update.url(),
            {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ name, email }),
            },
        );

        return response.data;
    },

    async updatePassword(
        currentPassword: string,
        password: string,
        passwordConfirmation: string,
    ): Promise<void> {
        await apiRequest(settingsRoutes.password.update.url(), {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPassword,
                password,
                password_confirmation: passwordConfirmation,
            }),
        });
    },
};
