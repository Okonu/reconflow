import { Badge } from '@/components/ui/badge';
import { cn } from '@/lib/utils';
import type { Severity } from '../types';

const STYLES: Record<Severity, string> = {
    low: 'border-transparent bg-slate-100 text-slate-700',
    medium: 'border-transparent bg-amber-100 text-amber-800',
    high: 'border-transparent bg-orange-100 text-orange-800',
    critical: 'border-transparent bg-red-600 text-white',
};

export function SeverityBadge({ severity }: { severity: Severity }) {
    return <Badge className={cn('capitalize', STYLES[severity])}>{severity}</Badge>;
}
