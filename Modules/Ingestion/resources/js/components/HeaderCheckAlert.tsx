import type { HeaderCheck } from '../types';

export function HeaderCheckAlert({ check }: { check: HeaderCheck }) {
    if (check.ok) {
        return <div className="rounded-md border border-success/30 bg-success/10 px-4 py-3 text-sm">{check.message}</div>;
    }

    return (
        <div className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">
            <p className="font-medium">This file can't be imported: its columns don't match the template.</p>
            <p className="mt-1">{check.message}</p>
        </div>
    );
}
