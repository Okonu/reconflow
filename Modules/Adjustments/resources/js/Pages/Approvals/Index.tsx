import { Head, Link, router } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { AdjustmentStateBadge } from '../../components/AdjustmentStateBadge';
import { DecisionButtons } from '../../components/DecisionButtons';
import { JournalPreview } from '../../components/JournalPreview';
import type { AdjustmentRow } from '../../types';

interface Props {
    pending: { data: AdjustmentRow[] };
    recent: { data: AdjustmentRow[] };
    erp: { simulating_failure: boolean; can_manage: boolean };
}

function ExceptionLink({ adjustment }: { adjustment: AdjustmentRow }) {
    return (
        <Link href={route('exceptions.show', adjustment.exception_id)} className="font-medium text-primary hover:underline">
            {adjustment.exception?.transaction_id ?? `Exception #${adjustment.exception_id}`}
        </Link>
    );
}

export default function ApprovalsIndex({ pending, recent, erp }: Props) {
    return (
        <AppLayout>
            <Head title="Approvals" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Approvals inbox</h1>
                        <p className="text-sm text-muted-foreground">Corrections proposed by analysts. Nothing reaches the ERP until someone other than the proposer approves it.</p>
                    </div>
                    {erp.can_manage && (
                        <Button variant={erp.simulating_failure ? 'destructive' : 'outline'} size="sm" onClick={() => router.post(route('adjustments.erp-failure'), {}, { preserveScroll: true })}>
                            {erp.simulating_failure ? 'Stop simulating ERP failure' : 'Simulate ERP failure'}
                        </Button>
                    )}
                </div>
                {erp.simulating_failure && (
                    <Alert variant="destructive">
                        <AlertDescription>The simulated ERP is rejecting postings. Approved adjustments will go to “Posting failed” until you retry.</AlertDescription>
                    </Alert>
                )}

                <Card>
                    <CardHeader>
                        <CardTitle>Waiting for approval ({pending.data.length})</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {pending.data.length === 0 ? (
                            <EmptyState title="Nothing to approve" description="Proposed adjustments appear here." />
                        ) : (
                            <ul className="space-y-4">
                                {pending.data.map((a) => (
                                    <li key={a.id} className="space-y-2 rounded-md border p-4">
                                        <div className="flex flex-wrap items-center justify-between gap-2">
                                            <p>
                                                <ExceptionLink adjustment={a} /> · {a.type_label} ·{' '}
                                                <span className="font-semibold tabular-nums">{formatMoney(a.amount)}</span>
                                                {a.high_value && <span className="ml-2 text-xs text-amber-700">High value</span>}
                                            </p>
                                            <span className="text-xs text-muted-foreground">
                                                {a.exception?.business_date} · {a.exception?.category}
                                            </span>
                                        </div>
                                        <p className="text-sm">{a.reason}</p>
                                        <p className="text-xs text-muted-foreground">
                                            Proposed by {a.proposed_by} {formatDateTime(a.created_at)}
                                        </p>
                                        <JournalPreview journal={a.journal} />
                                        <DecisionButtons adjustment={a} />
                                    </li>
                                ))}
                            </ul>
                        )}
                    </CardContent>
                </Card>

                <Card>
                    <CardHeader>
                        <CardTitle>Recent decisions</CardTitle>
                    </CardHeader>
                    <CardContent>
                        {recent.data.length === 0 ? (
                            <p className="text-sm text-muted-foreground">No decisions yet.</p>
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Exception</TableHead>
                                        <TableHead>Type</TableHead>
                                        <TableHead className="text-right">Amount</TableHead>
                                        <TableHead>Proposed / decided</TableHead>
                                        <TableHead>ERP journal</TableHead>
                                        <TableHead>State</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {recent.data.map((a) => (
                                        <TableRow key={a.id}>
                                            <TableCell>
                                                <ExceptionLink adjustment={a} />
                                            </TableCell>
                                            <TableCell>{a.type_label}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(a.amount)}</TableCell>
                                            <TableCell className="text-sm">
                                                {a.proposed_by} / {a.decided_by ?? '—'}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">{a.erp_journal_id ?? '—'}</TableCell>
                                            <TableCell>
                                                <AdjustmentStateBadge state={a.state} label={a.state_label} />
                                            </TableCell>
                                            <TableCell>
                                                <DecisionButtons adjustment={a} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
