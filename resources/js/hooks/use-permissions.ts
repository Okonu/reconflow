import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function usePermissions() {
    const { auth } = usePage<SharedProps>().props;
    return {
        can: (permission: string) => auth.permissions.includes(permission),
        user: auth.user,
    };
}
