import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { Dashboard } from '../types';

export function LatestRunCard({ dashboard }: { dashboard: Dashboard }) {
    const { kpis, latest_run: run } = dashboard;

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Latest run</CardTitle>
            </CardHeader>
            <CardContent className="space-y-2 text-sm">
                {run ? (
                    <>
                        <p>
                            <Link href={route('runs.show', run.id)} className="font-medium text-primary hover:underline">
                                Version {run.version}
                            </Link>{' '}
                            · {run.status.replace('_', ' ')}
                            {run.provisional && ' · provisional'}
                            {run.stale && ' · stale'}
                        </p>
                        <p className="text-muted-foreground">
                            {run.duration_ms !== null && `${(run.duration_ms / 1000).toFixed(1)} s · `}
                            finished {formatDateTime(run.finished_at)}
                        </p>
                        {run.blocked_reason && <p className="text-destructive">{run.blocked_reason}</p>}
                        <ul className="space-y-1">
                            {run.batches.map((b) => (
                                <li key={b.source} className="flex justify-between">
                                    <span className="capitalize">
                                        {b.source}
                                        {b.manual && ' (upload)'}
                                    </span>
                                    <span className="tabular-nums">
                                        {b.rows === null ? 'missing' : `${b.rows.toLocaleString()} rows`}
                                        {b.quarantined ? <span className="text-amber-700"> · {b.quarantined} quarantined</span> : null}
                                    </span>
                                </li>
                            ))}
                        </ul>
                        <p>
                            Sign-off:{' '}
                            <Link href={route('signoff.show', dashboard.date)} className="text-primary hover:underline">
                                {run.signed_off ? 'signed off' : 'not signed off'}
                            </Link>
                        </p>
                        <p className="text-muted-foreground">
                            {kpis.pending_fuzzy} fuzzy matches to confirm · {kpis.pending_approvals} adjustments awaiting approval
                            {kpis.posting_failed > 0 && ` · ${kpis.posting_failed} ERP postings failed`}
                        </p>
                        {kpis.prior_day_cleared && kpis.prior_day_cleared.count > 0 && (
                            <p className="text-muted-foreground">
                                {kpis.prior_day_cleared.count} earlier items cleared ({formatMoney(kpis.prior_day_cleared.value)})
                            </p>
                        )}
                    </>
                ) : (
                    <p className="text-muted-foreground">No run yet.</p>
                )}
            </CardContent>
        </Card>
    );
}
