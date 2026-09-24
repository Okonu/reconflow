import { Head, Link, router } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import type { AiStatus, AiSuggestion } from '../../types';

interface Stats {
    days: number;
    triage: { total: number; pending: number; accepted: number; overridden: number; failed: number; acceptance_rate: number | null };
    summaries: number;
    avg_latency_ms: number;
    input_tokens: number;
    output_tokens: number;
    fallback_served: number;
    avg_confidence: number | null;
    failure_rate: number | null;
    by_category: { category: string; accepted: number; overridden: number }[];
}

interface EvalRun {
    id: number;
    eval_set: string;
    model: string;
    prompt_version: string;
    cases: number;
    action_correct: number;
    cause_correct: number;
    errors: number;
    created_at: string | null;
}

interface Props {
    status: AiStatus;
    accountability: { owner_role: string; last_review_date: string };
    stats: Stats;
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

function Subject({ s }: { s: AiSuggestion }) {
    if (s.exception_id) {
        return (
            <Link href={route('exceptions.show', s.exception_id)} className="text-primary hover:underline">
                Exception #{s.exception_id}
            </Link>
        );
    }
    if (s.run_id) {
        return (
            <Link href={route('runs.show', s.run_id)} className="text-primary hover:underline">
                Run #{s.run_id}
            </Link>
        );
    }
    return <span>—</span>;
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

                {stats.by_category.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle>Decisions by exception category</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Category</TableHead>
                                        <TableHead>Accepted</TableHead>
                                        <TableHead>Overridden</TableHead>
                                        <TableHead>Override rate</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {stats.by_category.map((c) => (
                                        <TableRow key={c.category}>
                                            <TableCell>{c.category || '—'}</TableCell>
                                            <TableCell>{c.accepted}</TableCell>
                                            <TableCell>{c.overridden}</TableCell>
                                            <TableCell>{c.accepted + c.overridden === 0 ? '—' : `${Math.round((c.overridden * 100) / (c.accepted + c.overridden))}%`}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        </CardContent>
                    </Card>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Overrides</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {overrides.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No overrides yet.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Subject</TableHead>
                                        <TableHead>AI suggested</TableHead>
                                        <TableHead>Person chose</TableHead>
                                        <TableHead>Reason</TableHead>
                                        <TableHead>By</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {overrides.data.map((s) => (
                                        <TableRow key={s.id}>
                                            <TableCell>
                                                <Subject s={s} />
                                            </TableCell>
                                            <TableCell>{s.recommended_action_label}</TableCell>
                                            <TableCell>{s.override_action_label ?? '—'}</TableCell>
                                            <TableCell className="max-w-sm">{s.decision_reason}</TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {s.decided_by} · {formatDateTime(s.decided_at)}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Evaluation runs</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {evals.length === 0 ? (
                            <p className="text-sm text-muted-foreground">
                                No evaluation runs yet. Run <code className="rounded bg-muted px-1">php artisan reconflow:ai-eval</code>.
                            </p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>When</TableHead>
                                        <TableHead>Set</TableHead>
                                        <TableHead>Model / prompt</TableHead>
                                        <TableHead>Action correct</TableHead>
                                        <TableHead>Cause correct</TableHead>
                                        <TableHead>Errors</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {evals.map((e) => (
                                        <TableRow key={e.id}>
                                            <TableCell>{formatDateTime(e.created_at)}</TableCell>
                                            <TableCell>{e.eval_set}</TableCell>
                                            <TableCell>
                                                {e.model} / {e.prompt_version}
                                            </TableCell>
                                            <TableCell>
                                                {e.action_correct}/{e.cases}
                                            </TableCell>
                                            <TableCell>
                                                {e.cause_correct}/{e.cases}
                                            </TableCell>
                                            <TableCell>{e.errors}</TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent requests</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <Table>
                            <TableHeader>
                                <TableRow>
                                    <TableHead>When</TableHead>
                                    <TableHead>Kind</TableHead>
                                    <TableHead>Subject</TableHead>
                                    <TableHead>Result</TableHead>
                                    <TableHead>Status</TableHead>
                                    <TableHead>Requested by</TableHead>
                                </TableRow>
                            </TableHeader>
                            <TableBody>
                                {recent.data.map((s) => (
                                    <TableRow key={s.id}>
                                        <TableCell className="whitespace-nowrap">{formatDateTime(s.created_at)}</TableCell>
                                        <TableCell>{s.kind === 'triage' ? 'Triage' : 'Run summary'}</TableCell>
                                        <TableCell>
                                            <Subject s={s} />
                                        </TableCell>
                                        <TableCell className="max-w-sm">{s.error ?? s.recommended_action_label ?? s.output.headline}</TableCell>
                                        <TableCell>{s.status_label}</TableCell>
                                        <TableCell>{s.requested_by}</TableCell>
                                    </TableRow>
                                ))}
                            </TableBody>
                        </Table>
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
