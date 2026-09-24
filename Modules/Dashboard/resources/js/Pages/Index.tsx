import { Head, Link, router } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { CategoryChart } from '../components/CategoryChart';
import { KpiTile } from '../components/KpiTile';
import { LatestRunCard } from '../components/LatestRunCard';
import type { Dashboard } from '../types';
import { PreparingNotice } from '../components/PreparingNotice';
import { TrendChart } from '../components/TrendChart';


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

                {dashboard.preparing && <PreparingNotice />}

                {!run && !dashboard.preparing ? (
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
                    <LatestRunCard dashboard={dashboard} />
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
