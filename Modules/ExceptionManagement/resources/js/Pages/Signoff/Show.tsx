import { Head, Link, router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { Textarea } from '@/components/ui/textarea';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { SeverityBadge } from '../../components/SeverityBadge';
import type { ReconExceptionRow } from '../../types';

interface Props {
    date: string;
    run: { id: number; version: number; summary: { match_rate?: number; items?: number } } | null;
    signoff: { signed_by: string | null; signed_at: string; comment: string | null; carried: number } | null;
    blockers: string[];
    blocking_exceptions: { data: ReconExceptionRow[] };
    to_acknowledge: { data: ReconExceptionRow[] };
    pending_fuzzy: number;
    can: { sign: boolean; reopen: boolean };
}

function ExceptionTable({ rows }: { rows: ReconExceptionRow[] }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Severity</TableHead>
                    <TableHead>Item</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">At risk</TableHead>
                    <TableHead>Owner</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((e) => (
                    <TableRow key={e.id}>
                        <TableCell>
                            <SeverityBadge severity={e.severity} />
                        </TableCell>
                        <TableCell>
                            <Link href={route('exceptions.show', e.id)} className="text-primary hover:underline">
                                {e.transaction_id ?? e.payment_ids.join(', ')}
                            </Link>
                        </TableCell>
                        <TableCell>{e.status_label}</TableCell>
                        <TableCell className="text-right tabular-nums">{formatMoney(e.amount_at_risk)}</TableCell>
                        <TableCell>{e.owner?.name ?? 'Unassigned'}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}

export default function SignoffShow({ date, run, signoff, blockers, blocking_exceptions, to_acknowledge, pending_fuzzy, can }: Props) {
    const sign = useForm({ comment: '' });
    const reopen = useForm({ reason: '' });
    const [reopening, setReopening] = useState(false);
    const needsAcknowledgement = to_acknowledge.data.length > 0;

    return (
        <AppLayout>
            <Head title={`Sign-off ${date}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Sign-off for {date}</h1>
                        <p className="text-sm text-muted-foreground">Signing off locks the date: no re-runs or uploads until a Finance Manager reopens it with a reason.</p>
                    </div>
                    <Input type="date" aria-label="Choose date" className="w-44" value={date} onChange={(e) => e.target.value && router.get(route('signoff.show', e.target.value))} />
                </div>

                {run && (
                    <Card>
                        <CardContent className="flex flex-wrap gap-8 p-4 text-sm">
                            <p>
                                Run{' '}
                                <Link href={route('runs.show', run.id)} className="text-primary hover:underline">
                                    v{run.version}
                                </Link>
                            </p>
                            <p>Match rate {run.summary.match_rate ?? '—'}%</p>
                            <p>{run.summary.items ?? 0} items</p>
                        </CardContent>
                    </Card>
                )}

                {signoff ? (
                    <Alert>
                        <AlertTitle>
                            Signed off by {signoff.signed_by} on {formatDateTime(signoff.signed_at)}
                        </AlertTitle>
                        <AlertDescription>
                            {signoff.comment && <p className="whitespace-pre-line">{signoff.comment}</p>}
                            {signoff.carried > 0 && <p>{signoff.carried} exceptions were carried forward.</p>}
                        </AlertDescription>
                    </Alert>
                ) : blockers.length > 0 ? (
                    <Alert variant="destructive">
                        <AlertTitle>This date cannot be signed off yet</AlertTitle>
                        <AlertDescription>
                            <ul className="list-disc pl-5">
                                {blockers.map((b) => (
                                    <li key={b}>{b}</li>
                                ))}
                            </ul>
                            {pending_fuzzy > 0 && (
                                <Link href={route('matches.index', { date })} className="mt-2 inline-block underline">
                                    Review fuzzy matches
                                </Link>
                            )}
                        </AlertDescription>
                    </Alert>
                ) : null}

                {blocking_exceptions.data.length > 0 && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Blocking exceptions</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ExceptionTable rows={blocking_exceptions.data} />
                        </CardContent>
                    </Card>
                )}

                {!signoff && needsAcknowledgement && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">Open exceptions that will be carried forward</CardTitle>
                        </CardHeader>
                        <CardContent>
                            <ExceptionTable rows={to_acknowledge.data} />
                        </CardContent>
                    </Card>
                )}

                {can.sign && (
                    <Card>
                        <CardContent className="space-y-3 pt-6">
                            <form
                                className="space-y-3"
                                onSubmit={(e) => {
                                    e.preventDefault();
                                    sign.post(route('signoff.store', date), { preserveScroll: true });
                                }}
                            >
                                <Label htmlFor="signoff-comment">{needsAcknowledgement ? 'Acknowledge the carried exceptions (required)' : 'Comment (optional)'}</Label>
                                <Textarea id="signoff-comment" value={sign.data.comment} onChange={(e) => sign.setData('comment', e.target.value)} />
                                <FieldError message={sign.errors.comment} />
                                <Button type="submit" disabled={sign.processing || (needsAcknowledgement && sign.data.comment.trim() === '')}>
                                    Sign off {date}
                                </Button>
                            </form>
                        </CardContent>
                    </Card>
                )}

                {can.reopen && (
                    <Card>
                        <CardContent className="space-y-3 pt-6">
                            {reopening ? (
                                <form
                                    className="space-y-3"
                                    onSubmit={(e) => {
                                        e.preventDefault();
                                        reopen.post(route('signoff.reopen', date), { preserveScroll: true, onSuccess: () => setReopening(false) });
                                    }}
                                >
                                    <Label htmlFor="reopen-reason">Why must this date be reopened?</Label>
                                    <Textarea id="reopen-reason" value={reopen.data.reason} onChange={(e) => reopen.setData('reason', e.target.value)} />
                                    <FieldError message={reopen.errors.reason} />
                                    <div className="flex gap-2">
                                        <Button type="submit" variant="destructive" disabled={reopen.processing}>
                                            Reopen {date}
                                        </Button>
                                        <Button type="button" variant="ghost" onClick={() => setReopening(false)}>
                                            Cancel
                                        </Button>
                                    </div>
                                </form>
                            ) : (
                                <Button variant="outline" onClick={() => setReopening(true)}>
                                    Reopen this date
                                </Button>
                            )}
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
