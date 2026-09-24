import { Head, Link, router } from '@inertiajs/react';
import { usePermissions } from '@/hooks/use-permissions';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime, formatMoney } from '@/lib/format';
import { SeverityBadge } from '../../components/SeverityBadge';
import { ExceptionStateBadge } from '../../components/StateBadge';
import { SummaryTiles } from '../../components/SummaryTiles';
import type { Paginated, QueueFilters, ReconExceptionRow } from '../../types';

interface Props {
    exceptions: Paginated<ReconExceptionRow>;
    filters: QueueFilters;
    summary: { open: number; overdue: number; by_severity: Record<string, number>; needs_review: number };
    options: { categories: string[]; states: { value: string; label: string }[]; owners: { id: number; name: string }[] };
    can: { assign: boolean };
}

export default function ExceptionsIndex({ exceptions, filters, summary, options, can }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [assignee, setAssignee] = useState('');
    const [search, setSearch] = useState(filters.search ?? '');
    const { can: hasPermission } = usePermissions();

    const apply = (changes: Partial<QueueFilters> & { page?: number }) => {
        const next = { ...filters, ...changes };
        router.get(route('exceptions.index'), { ...next, overdue: next.overdue ? 1 : undefined }, { preserveState: true, preserveScroll: true });
        setSelected([]);
    };

    const toggle = (id: number, on: boolean) => setSelected((current) => (on ? [...current, id] : current.filter((x) => x !== id)));
    const allSelected = exceptions.data.length > 0 && selected.length === exceptions.data.length;

    const assign = () =>
        router.post(route('exceptions.assign'), { exception_ids: selected, owner_id: assignee === '' ? null : Number(assignee) }, { preserveScroll: true, onSuccess: () => setSelected([]) });

    return (
        <AppLayout>
            <Head title="Exceptions" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Exception queue</h1>
                    <p className="text-sm text-muted-foreground">Everything that did not reconcile cleanly, ordered by severity and SLA. Items carried from earlier days stay here until resolved.</p>
                </div>
                <SummaryTiles summary={summary} />
                {hasPermission('ai.use') && filters.business_date && (
                    <div className="flex items-center gap-3 rounded-md border border-violet-200 p-3 text-sm">
                        <span>Ask the AI assistant for a suggested cause and next step on every open exception for {filters.business_date}.</span>
                        <Button size="sm" variant="outline" onClick={() => router.post(route('ai.triage-batch'), { business_date: filters.business_date }, { preserveScroll: true })}>
                            Suggest for all
                        </Button>
                    </div>
                )}
                <div className="flex flex-wrap items-center gap-3">
                    <NativeSelect aria-label="State" value={filters.state ?? ''} onChange={(e) => apply({ state: e.target.value || null })}>
                        <option value="open">All open</option>
                        <option value="all">All states</option>
                        {options.states.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect aria-label="Severity" value={filters.severity ?? ''} onChange={(e) => apply({ severity: (e.target.value || null) as QueueFilters['severity'] })}>
                        <option value="">Any severity</option>
                        {['critical', 'high', 'medium', 'low'].map((s) => (
                            <option key={s} value={s} className="capitalize">
                                {s}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect aria-label="Category" value={filters.category ?? ''} onChange={(e) => apply({ category: e.target.value || null })}>
                        <option value="">Any category</option>
                        {options.categories.map((c) => (
                            <option key={c} value={c}>
                                {c}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect aria-label="Owner" value={filters.owner ?? ''} onChange={(e) => apply({ owner: e.target.value || null })}>
                        <option value="">Any owner</option>
                        <option value="me">Assigned to me</option>
                        <option value="unassigned">Unassigned</option>
                        {options.owners.map((o) => (
                            <option key={o.id} value={String(o.id)}>
                                {o.name}
                            </option>
                        ))}
                    </NativeSelect>
                    <Input type="date" aria-label="Business date" className="w-44" value={filters.business_date ?? ''} onChange={(e) => apply({ business_date: e.target.value || null })} />
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ search: search || null });
                        }}
                    >
                        <Input placeholder="Transaction or payment ID" className="w-56" value={search} onChange={(e) => setSearch(e.target.value)} />
                    </form>
                    <label className="flex items-center gap-2 text-sm">
                        <Checkbox checked={filters.overdue} onCheckedChange={(v) => apply({ overdue: v === true })} />
                        Past SLA only
                    </label>
                </div>

                {can.assign && selected.length > 0 && (
                    <div className="flex flex-wrap items-center gap-3 rounded-md border bg-muted/40 p-3 text-sm">
                        <span>{selected.length} selected</span>
                        <NativeSelect aria-label="Assign to" value={assignee} onChange={(e) => setAssignee(e.target.value)}>
                            <option value="">Unassign</option>
                            {options.owners.map((o) => (
                                <option key={o.id} value={String(o.id)}>
                                    {o.name}
                                </option>
                            ))}
                        </NativeSelect>
                        <Button size="sm" onClick={assign}>
                            Assign
                        </Button>
                    </div>
                )}

                <Card>
                    <CardContent className="pt-6">
                        {exceptions.data.length === 0 ? (
                            <EmptyState title="No exceptions match these filters" description="Run a reconciliation or widen the filters." />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {can.assign && (
                                            <TableHead className="w-8">
                                                <Checkbox aria-label="Select all" checked={allSelected} onCheckedChange={(v) => setSelected(v === true ? exceptions.data.map((e) => e.id) : [])} />
                                            </TableHead>
                                        )}
                                        <TableHead>Severity</TableHead>
                                        <TableHead>Date</TableHead>
                                        <TableHead>Item</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead className="text-right">At risk</TableHead>
                                        <TableHead>Owner</TableHead>
                                        <TableHead>Due</TableHead>
                                        <TableHead>State</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {exceptions.data.map((e) => (
                                        <TableRow key={e.id}>
                                            {can.assign && (
                                                <TableCell>
                                                    <Checkbox aria-label={`Select exception ${e.id}`} checked={selected.includes(e.id)} onCheckedChange={(v) => toggle(e.id, v === true)} />
                                                </TableCell>
                                            )}
                                            <TableCell>
                                                <SeverityBadge severity={e.severity} />
                                            </TableCell>
                                            <TableCell className="whitespace-nowrap">
                                                {e.business_date}
                                                {e.carried_from && <span className="block text-xs text-amber-700">Carried from {e.carried_from}</span>}
                                            </TableCell>
                                            <TableCell>
                                                <Link href={route('exceptions.show', e.id)} className="font-medium text-primary hover:underline">
                                                    {e.transaction_id ?? e.payment_ids.join(', ')}
                                                </Link>
                                                <span className="block text-xs text-muted-foreground">{e.category}</span>
                                            </TableCell>
                                            <TableCell>
                                                {e.status_label}
                                                {e.soft && <span className="block text-xs text-muted-foreground">Timing, no SLA yet</span>}
                                                {e.needs_review && <span className="block text-xs text-destructive">Needs review after re-run</span>}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(e.amount_at_risk)}</TableCell>
                                            <TableCell>{e.owner?.name ?? <span className="text-muted-foreground">Unassigned</span>}</TableCell>
                                            <TableCell className={e.overdue ? 'whitespace-nowrap font-medium text-destructive' : 'whitespace-nowrap'}>{formatDateTime(e.due_at)}</TableCell>
                                            <TableCell>
                                                <ExceptionStateBadge state={e.state} label={e.state_label} />
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <PaginationLinks page={exceptions.meta.current_page} lastPage={exceptions.meta.last_page} onChange={(page) => apply({ page })} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
