export interface RoleOption {
    id: number;
    label: string;
}

export interface UserRow {
    id: number;
    name: string;
    email: string;
    region: string | null;
    is_active: boolean;
    roles: { id: number; code: string; label: string }[];
    last_login_at: string | null;
    can: { update: boolean };
}

