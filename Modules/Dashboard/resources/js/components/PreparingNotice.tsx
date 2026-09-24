import { usePoll } from '@inertiajs/react';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';

export function PreparingNotice() {
    usePoll(5000, { only: ['dashboard'] });

    return (
        <Alert>
            <AlertTitle>Preparing demo data…</AlertTitle>
            <AlertDescription>
                The first start generates 14 days of synthetic sales, payments and ERP postings and reconciles them. This takes a few minutes; this page refreshes by itself.
            </AlertDescription>
        </Alert>
    );
}
