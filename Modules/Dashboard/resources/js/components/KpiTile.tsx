import type { ReactNode } from 'react';
import { Card, CardContent } from '@/components/ui/card';
import { cn } from '@/lib/utils';

export function KpiTile({ label, value, hint, tone }: { label: string; value: ReactNode; hint?: ReactNode; tone?: 'alert' | 'good' }) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
                <p className={cn('mt-1 text-2xl font-semibold tabular-nums', tone === 'alert' && 'text-destructive', tone === 'good' && 'text-emerald-700')}>{value}</p>
                {hint && <p className="mt-1 text-xs text-muted-foreground">{hint}</p>}
            </CardContent>
        </Card>
    );
}
