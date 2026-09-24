import { Head, Link, router } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { CategoryChart } from '../components/CategoryChart';
import { KpiTile } from '../components/KpiTile';
import { TrendChart } from '../components/TrendChart';

interface Dashboard {
    date: string;
    kpis: {
        match_rate: string | null;
        items: number | null;
        value_expected: string | null;
        value_reconciled: string | null;
        value_at_variance: string | null;
        value_unmatched_payments: string | null;
        open_exceptions: number;
        timing_items: number;
        overdue_exceptions: number;
        critical_open: number;
        pending_approvals: number;
        posting_failed: number;
        pending_fuzzy: number;
        minutes_saved: number | null;
        prior_day_cleared: { count: number; value: string } | null;
    };
    latest_run: {
        id: number;
        version: number;
        status: string;
        provisional: boolean;
        stale: boolean;
        duration_ms: number | null;
        finished_at: string | null;
        blocked_reason: string | null;
        batches: { source: string; rows: number | null; quarantined: number | null; manual: boolean }[];
        signed_off: boolean;
    } | null;
    trend: { date: string; match_rate: number | null; exceptions: number | null }[];
    by_category: { category: string; count: number; value: string }[];
    ageing: { bucket: string; count: number }[];
    manual_seconds_per_item: number;
}

function duration(minutes: number | null): string {
    if (minutes === null) {
        return '—';
    }
    const h = Math.floor(minutes / 60);
    return h > 0 ? `${h}h ${minutes % 60}m` : `${minutes}m`;
}

export default function DashboardIndex({ dashboard }: { dashboard: Dashboard | null }) {
    const { user, can } = usePermissions();

    if (dashboard === null) {
        return (
            <AppLayout>
                <Head title="Home" />
                <EmptyState title={`Welcome, ${user?.name ?? ''}`} description="Your role does not include the dashboard. Use the navigation above." />
            </AppLayout>
        );
    }

    const { kpis, latest_run: run } = dashboard;

    return (
        <AppLayout>
            <Head title="Dashboard" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Dashboard</h1>
                        <p className="text-sm text-muted-foreground">Business date {dashboard.date}. Values in USD.</p>
                    </div>
                    <Input type="date" aria-label="Business date" className="w-44" value={dashboard.date} onChange={(e) => e.target.value && router.get(route('home'), { date: e.target.value }, { preserveState: true })} />
                </div>

                {!run ? (
                    <EmptyState
                        title="No reconciliation for this date yet"
                        description="Run it from the Runs page once the source data has arrived."
                        action={
                            can('runs.view') ? (
                                <Link href={route('runs.index')} className="text-sm text-primary hover:underline">
                                    Go to runs
                                </Link>
                            ) : undefined
                        }
                    />
                ) : null}

                <div className="grid grid-cols-2 gap-3 md:grid-cols-3 xl:grid-cols-6">
                    <KpiTile label="Match rate" value={kpis.match_rate === null ? '—' : `${kpis.match_rate}%`} hint={kpis.items === null ? undefined : `${kpis.items.toLocaleString()} items`} tone="good" />
                    <KpiTile label="Value reconciled" value={formatMoney(kpis.value_reconciled)} hint={kpis.value_expected ? `of ${formatMoney(kpis.value_expected)} expected` : undefined} />
                    <KpiTile label="Value at variance" value={formatMoney(kpis.value_at_variance)} hint={kpis.value_unmatched_payments ? `${formatMoney(kpis.value_unmatched_payments)} unmatched payments` : undefined} />
                    <KpiTile
                        label="Open exceptions"
                        value={
                            <Link href={route('exceptions.index')} className="hover:underline">
                                {kpis.open_exceptions}
                            </Link>
                        }
                        hint={`${kpis.critical_open} critical · ${kpis.timing_items} timing`}
                        tone={kpis.critical_open > 0 ? 'alert' : undefined}
                    />
                    <KpiTile
                        label="Overdue"
                        value={
                            <Link href={route('exceptions.index', { overdue: 1 })} className="hover:underline">
                                {kpis.overdue_exceptions}
                            </Link>
                        }
                        hint="past SLA"
                        tone={kpis.overdue_exceptions > 0 ? 'alert' : undefined}
                    />
                    <KpiTile label="Time saved today" value={duration(kpis.minutes_saved)} hint={`vs manual baseline (${dashboard.manual_seconds_per_item}s per matched item)`} tone="good" />
                </div>

                <div className="grid gap-6 lg:grid-cols-3">
                    <Card className="lg:col-span-2">
                        <CardHeader>
                            <CardTitle className="text-base">Last 14 days</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {dashboard.trend.some((p) => p.match_rate !== null) ? <TrendChart points={dashboard.trend} /> : <EmptyState title="No completed runs in the last 14 days" />}
                        </CardContent>
                    </Card>
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
                </div>

                <div className="grid gap-6 lg:grid-cols-2">
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Open exceptions by category</CardTitle>
                        </CardHeader>
                        <CardContent>
                            {dashboard.by_category.length === 0 ? <EmptyState title="No open exceptions" /> : <CategoryChart data={dashboard.by_category} dataKey="count" nameKey="category" />}
                        </CardContent>
                    </Card>
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Exception ageing</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <CategoryChart data={dashboard.ageing} dataKey="count" nameKey="bucket" />
                        </CardContent>
                    </Card>
                </div>
            </div>
        </AppLayout>
    );
}
