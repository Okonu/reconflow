export interface AuthUser {
    id: number;
    name: string;
    email: string;
    roles: string[];
}

export interface SharedProps {
    [key: string]: unknown;
    app: { name: string; environment: string };
    auth: { user: AuthUser | null; permissions: string[] };
    flash: { success: string | null; error: string | null };
}
