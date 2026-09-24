import { Link } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { cn } from '@/lib/utils';

const LINKS = [
    { label: 'Runs', route: 'runs.index', permission: 'runs.view' },
    { label: 'Exceptions', route: 'exceptions.index', permission: 'exceptions.view' },
    { label: 'Fuzzy matches', route: 'matches.index', permission: 'results.view' },
    { label: 'Approvals', route: 'adjustments.index', permission: 'adjustments.view' },
    { label: 'Data uploads', route: 'ingestion.uploads.index', permission: 'uploads.view' },
    { label: 'Source batches', route: 'ingestion.batches.index', permission: 'batches.view' },
    { label: 'Audit log', route: 'audit.index', permission: 'audit.view' },
    { label: 'Users', route: 'users.index', permission: 'users.view' },
    { label: 'Roles', route: 'rbac.roles.index', permission: 'roles.view' },
];

export function MainNav() {
    const { can } = usePermissions();

    return (
        <nav className="flex flex-wrap items-center gap-1 text-sm">
            {LINKS.filter((link) => can(link.permission)).map((link) => (
                <Link
                    key={link.route}
                    href={route(link.route)}
                    className={cn(
                        'rounded-md px-3 py-1.5 text-muted-foreground hover:bg-accent hover:text-accent-foreground',
                        route().current(`${link.route.split('.').slice(0, -1).join('.')}.*`) && 'bg-accent text-accent-foreground',
                    )}
                >
                    {link.label}
                </Link>
            ))}
        </nav>
    );
}
