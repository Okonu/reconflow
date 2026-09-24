import { Link } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime } from '@/lib/format';
import type { AiSuggestion, EvalRun, OversightStats } from '../types';

export function Subject({ s }: { s: AiSuggestion }) {
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


export function CategoryDecisionsTable({ stats }: { stats: OversightStats }) {
    return (
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
    );
}

export function OverridesTable({ overrides }: { overrides: AiSuggestion[] }) {
    return (
        <Card>
            <CardHeader>
                <CardTitle>Overrides</CardTitle>
            </CardHeader>
            <CardContent>
                {overrides.length === 0 ? (
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
                            {overrides.map((s) => (
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
    );
}

export function EvalRunsTable({ evals }: { evals: EvalRun[] }) {
    return (
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
    );
}

export function RecentRequestsTable({ recent }: { recent: AiSuggestion[] }) {
    return (
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
                        {recent.map((s) => (
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
    );
}
