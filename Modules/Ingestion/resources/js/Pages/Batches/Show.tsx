import { Head, Link } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { usePermissions } from '@/hooks/use-permissions';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';
import { QuarantineTable } from '../../components/QuarantineTable';
import { StateBadge } from '../../components/StateBadge';
import type { QuarantinedRow, SourceBatch } from '../../types';

interface Props {
    batch: { data: SourceBatch };
    quarantined: { data: QuarantinedRow[] };
}

export default function BatchShow({ batch: { data: batch }, quarantined }: Props) {
    const reasons = Object.entries(batch.quarantine_reasons);
    const { can } = usePermissions();

    return (
        <AppLayout>
            <Head title={`${batch.source_label} ${batch.business_date}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <Link href={route('ingestion.batches.index')} className="text-sm text-muted-foreground hover:underline">
                            ← Source batches
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {batch.source_label} · {formatDate(batch.business_date)} · v{batch.version}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {batch.origin === 'upload' ? `Uploaded file ${batch.filename} by ${batch.created_by}` : 'Pulled from the simulated source system'} · {formatDateTime(batch.created_at)}
                        </p>
                    </div>
                    <div className="flex items-center gap-2">
                        <StateBadge state={batch.status} />
                        {can('runs.trigger') && batch.status === 'active' && (
                            <Button asChild size="sm">
                                <Link href={route('runs.index', { date: batch.business_date })}>Run reconciliation for this date</Link>
                            </Button>
                        )}
                    </div>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    {[
                        ['Rows in', batch.rows_received],
                        ['Loaded', batch.rows_loaded],
                        ['Quarantined', batch.rows_quarantined],
                    ].map(([label, value]) => (
                        <Card key={label}>
                            <CardContent className="pt-6">
                                <p className="text-sm text-muted-foreground">{label}</p>
                                <p className="text-2xl font-semibold tabular-nums">{Number(value).toLocaleString()}</p>
                            </CardContent>
                        </Card>
                    ))}
                </div>
                {reasons.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Why rows were quarantined</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ul className="space-y-1 text-sm">
                                {reasons.map(([reason, count]) => (
                                    <li key={reason} className="flex justify-between gap-4">
                                        <span>{reason}</span>
                                        <span className="tabular-nums text-muted-foreground">{count}</span>
                                    </li>
                                ))}
                            </ul>
                        </CardContent>
                    </Card>
                )}
                <Card>
                    <CardHeader>
                        <CardTitle>Quarantined rows</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <QuarantineTable rows={quarantined.data} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
