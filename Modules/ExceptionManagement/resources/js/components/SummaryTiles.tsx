import { Card, CardContent } from '@/components/ui/card';

interface Summary {
    open: number;
    overdue: number;
    by_severity: Record<string, number>;
    needs_review: number;
}

export function SummaryTiles({ summary }: { summary: Summary }) {
    const tiles = [
        { label: 'Open', value: summary.open },
        { label: 'Past SLA', value: summary.overdue, alert: summary.overdue > 0 },
        { label: 'Critical', value: summary.by_severity.critical ?? 0, alert: (summary.by_severity.critical ?? 0) > 0 },
        { label: 'High', value: summary.by_severity.high ?? 0 },
        { label: 'Needs review', value: summary.needs_review },
    ];

    return (
        <div className="grid grid-cols-2 gap-3 sm:grid-cols-5">
            {tiles.map((tile) => (
                <Card key={tile.label}>
                    <CardContent className="p-4">
                        <p className="text-xs uppercase tracking-wide text-muted-foreground">{tile.label}</p>
                        <p className={tile.alert ? 'text-2xl font-semibold text-destructive' : 'text-2xl font-semibold'}>{tile.value}</p>
                    </CardContent>
                </Card>
            ))}
        </div>
    );
}
