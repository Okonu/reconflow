import { Head, Link, usePoll } from '@inertiajs/react';
import { RunNarrative } from '@modules/AI/resources/js/components/RunNarrative';
import { usePermissions } from '@/hooks/use-permissions';
import { useEffect } from 'react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate, formatDateTime } from '@/lib/format';
import { RunBatches } from '../../components/RunBatches';
import { RunMetrics } from '../../components/RunMetrics';
import { RunStatusBadge } from '../../components/RunStatusBadge';
import type { ReconRun } from '../../types';

export default function RunShow({ run: { data: run } }: { run: { data: ReconRun } }) {
    const active = run.status === 'queued' || run.status === 'running';
    const { can } = usePermissions();
    const { start, stop } = usePoll(1500, { only: ['run'] }, { autoStart: false });
    useEffect(() => {
        if (active) {
            start();
        } else {
            stop();
        }
    }, [active, start, stop]);

    return (
        <AppLayout>
            <Head title={`Run ${run.business_date} v${run.version}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <Link href={route('runs.index')} className="text-sm text-muted-foreground hover:underline">
                            ← Runs
                        </Link>
                        <h1 className="text-2xl font-semibold">
                            {formatDate(run.business_date)} · version {run.version}
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {run.triggered_by ?? run.trigger} · started {formatDateTime(run.started_at)}
                            {run.duration_ms !== null && ` · ${(run.duration_ms / 1000).toFixed(1)} s`}
                        </p>
                    </div>
                    <div className="flex flex-col items-end gap-2">
                        <RunStatusBadge run={run} />
                        {run.status === 'completed' && can('exceptions.view') && (
                            <div className="flex gap-3 text-sm">
                                <Link href={route('exceptions.index', { business_date: run.business_date, state: 'all' })} className="text-primary hover:underline">
                                    Exceptions for this date
                                </Link>
                                <Link href={route('signoff.show', run.business_date)} className="text-primary hover:underline">
                                    Sign-off
                                </Link>
                            </div>
                        )}
                    </div>
                </div>
                {active && <p className="rounded-md border px-4 py-3 text-sm">Reconciling… this page updates automatically.</p>}
                {run.provisional && <p className="rounded-md border border-warning/40 bg-warning/10 px-4 py-3 text-sm">Provisional run: the payment window for this date was still open. Timing exceptions are expected and it cannot be signed off.</p>}
                {run.stale && <p className="rounded-md border border-warning/40 bg-warning/10 px-4 py-3 text-sm">The previous business date was re-run after this run. Re-run this date to pick up the change.</p>}
                {run.blocked_reason && <p className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">Blocked: {run.blocked_reason}.</p>}
                {run.error && <p className="rounded-md border border-destructive/30 bg-destructive/10 px-4 py-3 text-sm text-destructive">Failed: {run.error}</p>}
                {run.status === 'completed' && <RunMetrics summary={run.summary} />}
                {run.status === 'completed' && <RunNarrative runId={run.id} />}
                <Card>
                    <CardHeader>
                        <CardTitle>Source data used</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RunBatches batches={run.batches} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
