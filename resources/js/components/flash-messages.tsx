import { usePage } from '@inertiajs/react';
import type { SharedProps } from '@/types';

export function FlashMessages() {
    const { flash } = usePage<SharedProps>().props;

    if (!flash.success && !flash.error) {
        return null;
    }

    return (
        <div className="mb-6 space-y-2">
            {flash.success && <div className="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm">{flash.success}</div>}
            {flash.error && <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">{flash.error}</div>}
        </div>
    );
}
