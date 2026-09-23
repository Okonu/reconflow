import { Badge } from '@/components/ui/badge';

const LABELS: Record<string, { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' }> = {
    staged: { label: 'Awaiting confirmation', variant: 'outline' },
    confirmed: { label: 'Imported', variant: 'secondary' },
    cancelled: { label: 'Cancelled', variant: 'outline' },
    expired: { label: 'Expired', variant: 'outline' },
    active: { label: 'Active', variant: 'secondary' },
    superseded: { label: 'Superseded', variant: 'outline' },
    valid: { label: 'Valid', variant: 'secondary' },
    invalid: { label: 'Invalid', variant: 'destructive' },
};

export function StateBadge({ state }: { state: string }) {
    const config = LABELS[state] ?? { label: state, variant: 'outline' as const };

    return <Badge variant={config.variant}>{config.label}</Badge>;
}
