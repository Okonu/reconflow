import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDateTime, formatMoney } from '@/lib/format';
import { http, HttpError } from '@/lib/http';

interface Candidate {
    payment_result_id: number;
    payment_id: string;
    payment_date: string;
    paid_at: string;
    amount: string;
    reference: string | null;
    days_late: number;
}

export function PossibleMatches({ resultId }: { resultId: number }) {
    const [candidates, setCandidates] = useState<Candidate[] | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);
    const [chosen, setChosen] = useState<number | null>(null);
    const [reason, setReason] = useState('');

    const load = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await http<{ data: Candidate[] }>('GET', route('results.possible-matches', resultId));
            setCandidates(response.data);
        } catch (e) {
            setError(e instanceof HttpError ? e.message : 'Could not load possible matches.');
        } finally {
            setLoading(false);
        }
    };

    const confirm = () =>
        router.post(route('results.manual-match', resultId), { payment_result_id: chosen, reason }, { preserveScroll: true, onSuccess: () => setCandidates(null) });

    if (candidates === null) {
        return (
            <div className="space-y-2">
                <p className="text-sm text-muted-foreground">Look for unmatched payments from the same phone within tolerance received in the next 7 days.</p>
                <Button size="sm" variant="outline" onClick={load} disabled={loading}>
                    {loading ? 'Searching…' : 'Find possible late payments'}
                </Button>
                {error && <p className="text-sm text-destructive">{error}</p>}
            </div>
        );
    }

    if (candidates.length === 0) {
        return <p className="text-sm text-muted-foreground">No unmatched payment from this customer fits within tolerance.</p>;
    }

    return (
        <div className="space-y-3">
            <Table>
                <TableHeader>
                    <TableRow>
                        <TableHead />
                        <TableHead>Payment</TableHead>
                        <TableHead>Received</TableHead>
                        <TableHead className="text-right">Amount</TableHead>
                        <TableHead>Late by</TableHead>
                    </TableRow>
                </TableHeader>
                <TableBody>
                    {candidates.map((c) => (
                        <TableRow key={c.payment_result_id}>
                            <TableCell>
                                <input type="radio" name="candidate" aria-label={`Choose ${c.payment_id}`} checked={chosen === c.payment_result_id} onChange={() => setChosen(c.payment_result_id)} />
                            </TableCell>
                            <TableCell className="font-mono text-xs">{c.payment_id}</TableCell>
                            <TableCell>{formatDateTime(c.paid_at)}</TableCell>
                            <TableCell className="text-right tabular-nums">{formatMoney(c.amount)}</TableCell>
                            <TableCell>{c.days_late} days</TableCell>
                        </TableRow>
                    ))}
                </TableBody>
            </Table>
            <div className="flex flex-wrap items-center gap-2">
                <Input className="flex-1" placeholder="Reason for the manual match (required)" value={reason} onChange={(e) => setReason(e.target.value)} />
                <Button size="sm" disabled={chosen === null || reason.trim().length < 5} onClick={confirm}>
                    Confirm manual match
                </Button>
            </div>
        </div>
    );
}
