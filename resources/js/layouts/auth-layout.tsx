import type { PropsWithChildren } from 'react';
import { SyntheticDataNotice } from '@/components/synthetic-data-notice';

export default function AuthLayout({ title, description, children }: PropsWithChildren<{ title: string; description: string }>) {
    return (
        <div className="flex min-h-svh flex-col items-center justify-center bg-muted/40 px-4">
            <div className="w-full max-w-sm space-y-6">
                <div className="space-y-2 text-center">
                    <div className="text-sm font-semibold tracking-wide text-primary">ReconFlow</div>
                    <h1 className="text-2xl font-semibold">{title}</h1>
                    <p className="text-sm text-muted-foreground">{description}</p>
                </div>
                {children}
            </div>
            <SyntheticDataNotice className="mt-10" />
        </div>
    );
}
