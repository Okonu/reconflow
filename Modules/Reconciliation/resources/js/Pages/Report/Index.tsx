import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { NativeSelect } from '@/components/ui/native-select';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';
import { RollUpChip } from '../../components/RollUpChip';
import { UnmaskedExportPanel } from '../../components/UnmaskedExportPanel';

interface Row {
    id: number;
    section: string;
    transaction_id: string | null;
    payment_ids: string[];
    expected_amount: string | null;
    actual_amount: string | null;
    posted_amount: string | null;
    variance: string | null;
    status: string;
    original_status: string | null;
    roll_up: string;
    rule_id: string;
    tag: string | null;
    prior_date: string | null;
    customer_phone: string | null;
    region: string | null;
}

interface Filters {
    date: string;
    section: string;
    roll_up: string | null;
    status: string | null;
    search: string | null;
}

interface Props {
    filters: Filters;
    run: { id: number; version: number; provisional: boolean; stale: boolean } | null;
    counts: Record<string, number>;
    rows: { data: Row[]; current_page: number; last_page: number; total: number } | null;
    options: { roll_ups: string[]; statuses: { value: string; label: string }[] };
    can: { export: boolean; export_unmasked: boolean };
}

export default function ReportIndex({ filters, run, counts, rows, options, can }: Props) {
    const [search, setSearch] = useState(filters.search ?? '');
    const [unmasking, setUnmasking] = useState(false);

    const apply = (changes: Partial<Filters> & { page?: number }) => router.get(route('reports.reconciliation'), { ...filters, ...changes }, { preserveState: true, preserveScroll: true });
    const exportUrl = (format: 'csv' | 'xlsx') => route('reports.reconciliation.export', { ...filters, format });


    return (
        <AppLayout>
            <Head title="Reconciliation report" />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-semibold">Reconciliation report</h1>
                        <p className="text-sm text-muted-foreground">
                            {run ? `Business date ${filters.date}, run version ${run.version}${run.provisional ? ' (provisional)' : ''}${run.stale ? ' (stale)' : ''}.` : `No completed run for ${filters.date}.`} Phone numbers are
                            masked.
                        </p>
                    </div>
                    {run && can.export && (
                        <div className="flex flex-wrap gap-2">
                            <Button variant="outline" size="sm" asChild>
                                <a href={exportUrl('xlsx')}>Export XLSX</a>
                            </Button>
                            <Button variant="outline" size="sm" asChild>
                                <a href={exportUrl('csv')}>Export CSV</a>
                            </Button>
                            {can.export_unmasked && (
                                <Button variant="ghost" size="sm" onClick={() => setUnmasking((u) => !u)}>
                                    Unmasked export…
                                </Button>
                            )}
                        </div>
                    )}
                </div>

                {unmasking && <UnmaskedExportPanel filters={filters} onDone={() => setUnmasking(false)} />}

                <div className="flex flex-wrap items-center gap-3">
                    <Input type="date" aria-label="Business date" className="w-44" value={filters.date} onChange={(e) => e.target.value && apply({ date: e.target.value, page: 1 })} />
                    <NativeSelect aria-label="Section" value={filters.section} onChange={(e) => apply({ section: e.target.value, page: 1 })}>
                        <option value="current">This date</option>
                        <option value="prior_day">Cleared from earlier days</option>
                        <option value="all">Both sections</option>
                    </NativeSelect>
                    <NativeSelect aria-label="Status" value={filters.roll_up ?? ''} onChange={(e) => apply({ roll_up: e.target.value || null, page: 1 })}>
                        <option value="">All statuses</option>
                        {options.roll_ups.map((r) => (
                            <option key={r} value={r}>
                                {r} {counts[r] !== undefined ? `(${counts[r]})` : ''}
                            </option>
                        ))}
                    </NativeSelect>
                    <NativeSelect aria-label="Detailed status" value={filters.status ?? ''} onChange={(e) => apply({ status: e.target.value || null, page: 1 })}>
                        <option value="">Any detailed status</option>
                        {options.statuses.map((s) => (
                            <option key={s.value} value={s.value}>
                                {s.label}
                            </option>
                        ))}
                    </NativeSelect>
                    <form
                        onSubmit={(e) => {
                            e.preventDefault();
                            apply({ search: search || null, page: 1 });
                        }}
                    >
                        <Input placeholder="Transaction or payment ID" className="w-56" value={search} onChange={(e) => setSearch(e.target.value)} />
                    </form>
                </div>

                <Card>
                    <CardContent className="pt-6">
                        {!rows || rows.data.length === 0 ? (
                            <EmptyState title={run ? 'No items match these filters' : 'Nothing reconciled for this date yet'} />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        <TableHead>Transaction ID</TableHead>
                                        <TableHead className="text-right">Expected amount</TableHead>
                                        <TableHead className="text-right">Actual amount</TableHead>
                                        <TableHead className="text-right">Posted amount</TableHead>
                                        <TableHead>Status</TableHead>
                                        <TableHead>Detail</TableHead>
                                        <TableHead>Customer</TableHead>
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {rows.data.map((r) => (
                                        <TableRow key={r.id}>
                                            <TableCell>
                                                <span className="font-mono text-xs">{r.transaction_id ?? '—'}</span>
                                                {r.payment_ids.length > 0 && <span className="block font-mono text-xs text-muted-foreground">{r.payment_ids.join(', ')}</span>}
                                            </TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(r.expected_amount)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(r.actual_amount)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(r.posted_amount)}</TableCell>
                                            <TableCell>
                                                <RollUpChip rollUp={r.roll_up} />
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                <span className="font-mono text-xs">{r.status}</span> <span className="text-muted-foreground">({r.rule_id})</span>
                                                {r.original_status && <span className="block text-xs text-muted-foreground">was {r.original_status}</span>}
                                                {r.tag && <span className="block text-xs text-muted-foreground">{r.tag}</span>}
                                            </TableCell>
                                            <TableCell className="text-sm">
                                                <span className="font-mono text-xs">{r.customer_phone ?? '—'}</span>
                                                {r.region && <span className="block text-xs text-muted-foreground">{r.region}</span>}
                                            </TableCell>
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        {rows && <PaginationLinks page={rows.current_page} lastPage={rows.last_page} onChange={(page) => apply({ page })} />}
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
