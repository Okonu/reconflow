import { Link, router } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { SyntheticDataNotice } from '@/components/synthetic-data-notice';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';

export default function AppLayout({ children }: PropsWithChildren) {
    const { user } = usePermissions();

    return (
        <div className="flex min-h-svh flex-col">
            <header className="border-b bg-card">
                <div className="mx-auto flex h-14 max-w-7xl items-center justify-between px-4 sm:px-6">
                    <Link href={route('home')} className="font-semibold text-primary">
                        ReconFlow
                    </Link>
                    <div className="flex items-center gap-3 text-sm">
                        <span className="hidden text-muted-foreground sm:inline">{user?.name}</span>
                        <Button variant="outline" size="sm" onClick={() => router.post(route('logout'))}>
                            Sign out
                        </Button>
                    </div>
                </div>
            </header>
            <main className="mx-auto w-full max-w-7xl flex-1 px-4 py-8 sm:px-6">{children}</main>
            <footer className="border-t py-4">
                <SyntheticDataNotice />
            </footer>
        </div>
    );
}
