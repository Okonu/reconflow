import { Head, router } from '@inertiajs/react';
import { useState } from 'react';
import { EmptyState } from '@/components/empty-state';
import { PaginationLinks } from '@/components/pagination-links';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import AppLayout from '@/layouts/app-layout';
import { formatMoney } from '@/lib/format';

interface FuzzyMatch {
    id: number;
    business_date: string;
    section: string;
    transaction_id: string | null;
    payment_ids: string[];
    expected_amount: string | null;
    actual_amount: string | null;
    confidence: number | string | null;
    tag: string | null;
}

interface Props {
    matches: { data: FuzzyMatch[]; meta: { current_page: number; last_page: number; total: number } };
    filters: { date: string | null };
    can: { review: boolean };
}

export default function MatchesIndex({ matches, filters, can }: Props) {
    const [selected, setSelected] = useState<number[]>([]);
    const [rejecting, setRejecting] = useState<number | null>(null);
    const [reason, setReason] = useState('');

    const apply = (changes: { date?: string | null; page?: number }) => router.get(route('matches.index'), { ...filters, ...changes }, { preserveState: true });
    const confirm = (ids: number[]) => router.post(route('matches.confirm'), { result_ids: ids }, { preserveScroll: true, onSuccess: () => setSelected([]) });
    const reject = (id: number) =>
        router.post(route('matches.reject', id), { reason }, {
            preserveScroll: true,
            onSuccess: () => {
                setRejecting(null);
                setReason('');
            },
        });

    return (
        <AppLayout>
            <Head title="Confirm fuzzy matches" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Confirm fuzzy matches</h1>
                    <p className="text-sm text-muted-foreground">
                        These payments had no usable reference and were matched on phone, amount and time. A person must confirm or reject each one before the date can be signed off. Rejecting splits it into a missing
                        payment and an unmatched payment.
                    </p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Input type="date" aria-label="Business date" className="w-44" value={filters.date ?? ''} onChange={(e) => apply({ date: e.target.value || null })} />
                    {can.review && selected.length > 0 && <Button onClick={() => confirm(selected)}>Confirm {selected.length} selected</Button>}
                </div>
                <Card>
                    <CardContent className="pt-6">
                        {matches.data.length === 0 ? (
                            <EmptyState title="No fuzzy matches waiting" description="Every fuzzy match for this filter has been reviewed." />
                        ) : (
                            <Table>
                                <TableHeader>
                                    <TableRow>
                                        {can.review && (
                                            <TableHead className="w-8">
                                                <Checkbox aria-label="Select all" checked={selected.length === matches.data.length} onCheckedChange={(v) => setSelected(v === true ? matches.data.map((m) => m.id) : [])} />
                                            </TableHead>
                                        )}
                                        <TableHead>Date</TableHead>
                                        <TableHead>Sale</TableHead>
                                        <TableHead>Payment</TableHead>
                                        <TableHead className="text-right">Expected</TableHead>
                                        <TableHead className="text-right">Received</TableHead>
                                        <TableHead>Confidence</TableHead>
                                        {can.review && <TableHead />}
                                    </TableRow>
                                </TableHeader>
                                <TableBody>
                                    {matches.data.map((m) => (
                                        <TableRow key={m.id}>
                                            {can.review && (
                                                <TableCell>
                                                    <Checkbox
                                                        aria-label={`Select match ${m.id}`}
                                                        checked={selected.includes(m.id)}
                                                        onCheckedChange={(v) => setSelected((s) => (v === true ? [...s, m.id] : s.filter((x) => x !== m.id)))}
                                                    />
                                                </TableCell>
                                            )}
                                            <TableCell className="whitespace-nowrap">
                                                {m.business_date}
                                                {m.section !== 'current' && <span className="block text-xs text-amber-700">Carried item</span>}
                                            </TableCell>
                                            <TableCell className="font-mono text-xs">{m.transaction_id}</TableCell>
                                            <TableCell className="font-mono text-xs">{m.payment_ids.join(', ')}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(m.expected_amount)}</TableCell>
                                            <TableCell className="text-right tabular-nums">{formatMoney(m.actual_amount)}</TableCell>
                                            <TableCell>{m.confidence === null ? '—' : `${Math.round(Number(m.confidence) * 100)}%`}</TableCell>
                                            {can.review && (
                                                <TableCell className="whitespace-nowrap">
                                                    {rejecting === m.id ? (
                                                        <div className="flex items-center gap-2">
                                                            <Input className="h-9 w-56" placeholder="Why is this not a match?" value={reason} onChange={(e) => setReason(e.target.value)} />
                                                            <Button size="sm" variant="destructive" disabled={reason.trim().length < 5} onClick={() => reject(m.id)}>
                                                                Reject
                                                            </Button>
                                                            <Button size="sm" variant="ghost" onClick={() => setRejecting(null)}>
                                                                Cancel
                                                            </Button>
                                                        </div>
                                                    ) : (
                                                        <div className="flex gap-2">
                                                            <Button size="sm" onClick={() => confirm([m.id])}>
                                                                Confirm
                                                            </Button>
                                                            <Button size="sm" variant="outline" onClick={() => setRejecting(m.id)}>
                                                                Reject
                                                            </Button>
                                                        </div>
                                                    )}
                                                </TableCell>
                                            )}
                                        </TableRow>
                                    ))}
                                </TableBody>
                            </Table>
                        )}
                        <PaginationLinks page={matches.meta.current_page} lastPage={matches.meta.last_page} onChange={(page) => apply({ page })} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
