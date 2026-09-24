import { Card, CardContent } from '@/components/ui/card';
import { formatMoney } from '@/lib/format';
import type { RunSummary } from '../types';

export function RunMetrics({ summary }: { summary: Partial<RunSummary> }) {
    const tiles: [string, string][] = [
        ['Match rate', summary.match_rate ? `${summary.match_rate}%` : '—'],
        ['Value reconciled', formatMoney(summary.value_reconciled)],
        ['Value at variance', formatMoney(summary.value_at_variance)],
        ['Exceptions', summary.exceptions?.toLocaleString() ?? '—'],
        ['Prior-day items cleared', summary.prior_day_cleared ? `${summary.prior_day_cleared.count} · ${formatMoney(summary.prior_day_cleared.value)}` : '—'],
        ['Escalated from prior day', summary.escalated_from_prior_day?.toLocaleString() ?? '—'],
    ];

    return (
        <div className="grid gap-4 sm:grid-cols-3 lg:grid-cols-6">
            {tiles.map(([label, value]) => (
                <Card key={label}>
                    <CardContent className="pt-6">
                        <p className="text-sm text-muted-foreground">{label}</p>
                        <p className="text-xl font-semibold tabular-nums">{value}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
