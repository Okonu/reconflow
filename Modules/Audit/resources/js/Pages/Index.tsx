import { Head, router } from '@inertiajs/react';
import { Fragment, useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';
import { http, HttpError } from '@/lib/http';

interface AuditRow {
    id: number;
    occurred_at: string;
    actor: string;
    action: string;
    entity_type: string | null;
    entity_id: string | null;
    request_id: string | null;
    payload: Record<string, unknown>;
    hash: string;
    prev_hash: string;
}

interface Filters {
    action: string | null;
    entity_type: string | null;
    entity_id: string | null;
    actor: string | null;
    from: string | null;
    to: string | null;
}

interface Verification {
    ok: boolean;
    events_checked: number;
    head_hash: string | null;
    broken_at_id: number | null;
    reason: string | null;
    verified_at: string;
}

interface Props {
    events: { data: AuditRow[]; meta: { current_page: number; last_page: number; total: number } };
    filters: Filters;
    can: { verify: boolean; export: boolean };
}

export default function AuditIndex({ events, filters, can }: Props) {
    const [draft, setDraft] = useState<Filters>(filters);
    const [expanded, setExpanded] = useState<number | null>(null);
    const [verification, setVerification] = useState<Verification | null>(null);
    const [verifyError, setVerifyError] = useState<string | null>(null);
    const [verifying, setVerifying] = useState(false);

    const apply = (next: Partial<Filters> & { page?: number }) => router.get(route('audit.index'), { ...draft, ...next }, { preserveState: true, preserveScroll: true });
    const clean = (f: Filters) => Object.fromEntries(Object.entries(f).filter(([, v]) => v)) as Record<string, string>;

    const verify = async () => {
        setVerifying(true);
        setVerifyError(null);
        try {
            const response = await http<{ data: Verification }>('POST', route('audit.verify'));
            setVerification(response.data);
        } catch (e) {
            setVerifyError(e instanceof HttpError ? e.message : 'Verification failed to run.');
        } finally {
            setVerifying(false);
        }
    };

    const field = (key: keyof Filters, placeholder: string, type = 'text') => (
        <Input type={type} aria-label={placeholder} placeholder={placeholder} className="w-44" value={draft[key] ?? ''} onChange={(e) => setDraft({ ...draft, [key]: e.target.value || null })} />
    );

    return (
        <AppLayout>
            <Head title="Audit log" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Audit log</h1>
                        <p className="text-sm text-muted-foreground">Append-only and hash-chained: every event includes the hash of the one before it, so any edit or deletion breaks the chain.</p>
                    </div>
                    <div className="flex gap-2">
                        {can.export && (
                            <Button variant="outline" size="sm" asChild>
                                <a href={route('audit.export', clean(filters))}>Export CSV</a>
                            </Button>
                        )}
                        {can.verify && (
                            <Button size="sm" onClick={verify} disabled={verifying}>
                                {verifying ? 'Verifying…' : 'Verify integrity'}
                            </Button>
                        )}
                    </div>
                </div>

                {verification && (
                    <Alert variant={verification.ok ? 'default' : 'destructive'}>
                        <AlertTitle>{verification.ok ? 'Integrity verified' : 'Integrity check FAILED'}</AlertTitle>
                        <AlertDescription>
                            {verification.ok
                                ? `${verification.events_checked.toLocaleString()} events checked; the chain is unbroken. Head hash ${verification.head_hash?.slice(0, 16)}…`
                                : `Chain broken at event #${verification.broken_at_id}: ${verification.reason}`}{' '}
                            ({formatDateTime(verification.verified_at)})
                        </AlertDescription>
                    </Alert>
                )}
                {verifyError && <p className="text-sm text-destructive">{verifyError}</p>}

                <form
                    className="flex flex-wrap items-center gap-3"
                    onSubmit={(e) => {
                        e.preventDefault();
                        apply({ page: 1 });
                    }}
                >
                    {field('action', 'Action, e.g. adjustment')}
                    {field('actor', 'Actor')}
                    {field('entity_type', 'Entity type')}
                    {field('entity_id', 'Entity ID')}
                    {field('from', 'From', 'date')}
                    {field('to', 'To', 'date')}
                    <Button type="submit" size="sm" variant="secondary">
                        Filter
                    </Button>
                    <Button
                        type="button"
                        size="sm"
                        variant="ghost"
                        onClick={() => {
                            const empty = { action: null, entity_type: null, entity_id: null, actor: null, from: null, to: null };
                            setDraft(empty);
                            router.get(route('audit.index'));
                        }}
                    >
                        Clear
                    </Button>
                </form>

                <Card>
                    <CardContent className="pt-6">
                        {events.data.length === 0 ? (
                            <EmptyState title="No audit events match these filters" />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>#</TableHead>
                                        <TableHead>When</TableHead>
                                        <TableHead>Actor</TableHead>
                                        <TableHead>Action</TableHead>
                                        <TableHead>Entity</TableHead>
                                        <TableHead />
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {events.data.map((event) => (
                                        <Fragment key={event.id}>
                                            <TableRow>
                                                <TableCell className="text-xs text-muted-foreground">{event.id}</TableCell>
                                                <TableCell className="whitespace-nowrap">{formatDateTime(event.occurred_at)}</TableCell>
                                                <TableCell>{event.actor}</TableCell>
                                                <TableCell className="font-mono text-xs">{event.action}</TableCell>
                                                <TableCell className="text-sm">{event.entity_type ? `${event.entity_type} ${event.entity_id ?? ''}` : '—'}</TableCell>
                                                <TableCell>
                                                    <button type="button" className="text-xs text-primary hover:underline" onClick={() => setExpanded(expanded === event.id ? null : event.id)}>
                                                        {expanded === event.id ? 'Hide' : 'Details'}
                                                    </button>
                                                </TableCell>
                                            </TableRow>
                                            {expanded === event.id && (
                                                <TableRow>
                                                    <TableCell colSpan={6} className="bg-muted/40">
                                                        <pre className="max-h-80 overflow-auto whitespace-pre-wrap break-all text-xs">{JSON.stringify(event.payload, null, 2)}</pre>
                                                        <p className="mt-2 break-all font-mono text-[11px] text-muted-foreground">
                                                            request {event.request_id ?? '—'} · prev {event.prev_hash} · hash {event.hash}
                                                        </p>
                                                    </TableCell>
                                                </TableRow>
                                            )}
                                        </Fragment>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <PaginationLinks page={events.meta.current_page} lastPage={events.meta.last_page} onChange={(page) => apply({ page })} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
