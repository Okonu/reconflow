import { Head, router } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { CategoryDecisionsTable, EvalRunsTable, OverridesTable, RecentRequestsTable } from '../../components/OversightTables';
import type { AiStatus, AiSuggestion, EvalRun, OversightStats } from '../../types';

interface Props {
    status: AiStatus;
    accountability: { owner_role: string; last_review_date: string };
    stats: OversightStats;
    overrides: { data: AiSuggestion[] };
    recent: { data: AiSuggestion[] };
    evals: EvalRun[];
    prompt_version: string;
    retention_months: number;
    can: { manage: boolean };
}

function Tile({ label, value }: { label: string; value: string | number }) {
    return (
        <Card>
            <CardContent className="p-4">
                <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
                <p className="text-2xl font-semibold">{value}</p>
            </CardContent>
        </Card>
    );
}

export default function AiOversight({ status, accountability, stats, overrides, recent, evals, prompt_version, retention_months, can }: Props) {
    return (
        <AppLayout>
            <Head title="AI oversight" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">AI oversight</h1>
                        <p className="text-sm text-muted-foreground">
                            How the assistant is used and how often people agree with it. Model {status.model}, prompt {prompt_version}. Logs are kept {retention_months} months.
                        </p>
                    </div>
                    {can.manage && (
                        <Button variant={status.killed ? 'default' : 'destructive'} onClick={() => router.post(route('ai.kill-switch'), {}, { preserveScroll: true })}>
                            {status.killed ? 'Switch the AI assistant back on' : 'Kill switch: turn the AI assistant off'}
                        </Button>
                    )}
                </div>
                <Alert variant={status.enabled ? 'default' : 'destructive'}>
                    <AlertDescription>
                        {status.enabled ? 'The AI assistant is on.' : status.reason}
                        {status.toggled_by && ` Last toggled by ${status.toggled_by} ${formatDateTime(status.toggled_at)}.`} Accountable owner: {accountability.owner_role}. Last review:{' '}
                        {accountability.last_review_date || 'not recorded'}.
                    </AlertDescription>
                </Alert>

                <div className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    <Tile label={`Triage (${stats.days}d)`} value={stats.triage.total} />
                    <Tile label="Accepted" value={stats.triage.accepted} />
                    <Tile label="Overridden" value={stats.triage.overridden} />
                    <Tile label="Acceptance rate" value={stats.triage.acceptance_rate === null ? '—' : `${stats.triage.acceptance_rate}%`} />
                    <Tile label="Avg confidence" value={stats.avg_confidence === null ? '—' : `${stats.avg_confidence}%`} />
                    <Tile label="Failure rate" value={stats.failure_rate === null ? '—' : `${stats.failure_rate}%`} />
                    <Tile label="Avg latency" value={`${(stats.avg_latency_ms / 1000).toFixed(1)} s`} />
                    <Tile label="Tokens in / out" value={`${stats.input_tokens.toLocaleString()} / ${stats.output_tokens.toLocaleString()}`} />
                </div>

                {stats.by_category.length > 0 && <CategoryDecisionsTable stats={stats} />}
                <OverridesTable overrides={overrides.data} />
                <EvalRunsTable evals={evals} />
                <RecentRequestsTable recent={recent.data} />
            </div>
        </AppLayout>
    );
}
