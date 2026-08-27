export type AuthUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
    department_id?: string | null;
    is_super_admin?: boolean;
    roles?: Array<{
        key: string;
        name: string;
        scope: 'global' | 'department';
    }>;
    permissions?: string[];
};
