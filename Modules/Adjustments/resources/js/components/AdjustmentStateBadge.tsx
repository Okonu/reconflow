import { Badge } from '@/components/ui/badge';

export function AdjustmentStateBadge({ state, label }: { state: string; label: string }) {
    const variant = state === 'posted' ? 'default' : state === 'posting_failed' || state === 'rejected' ? 'destructive' : 'outline';
    return <Badge variant={variant}>{label}</Badge>;
}
