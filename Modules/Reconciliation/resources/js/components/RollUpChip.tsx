import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';

const STYLES: Record<string, string> = {
    Match: 'border-transparent bg-emerald-100 text-emerald-800',
    'Match (flagged)': 'border-transparent bg-sky-100 text-sky-800',
    'Match (prior day)': 'border-transparent bg-teal-100 text-teal-800',
    Variance: 'border-transparent bg-amber-100 text-amber-800',
    Exception: 'border-transparent bg-red-100 text-red-800',
    'Exception (soft)': 'border-transparent bg-slate-100 text-slate-700',
};

export function RollUpChip({ rollUp }: { rollUp: string }) {
    return <Badge className={cn('whitespace-nowrap', STYLES[rollUp])}>{rollUp}</Badge>;
}
