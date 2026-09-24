import { Badge } from '@/components/ui/badge';

export function ExceptionStateBadge({ state, label }: { state: string; label: string }) {
    const variant = state === 'resolved' || state === 'closed' ? 'secondary' : state === 'posting_failed' ? 'destructive' : 'outline';
    return <Badge variant={variant}>{label}</Badge>;
}
