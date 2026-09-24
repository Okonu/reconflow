import { Link } from '@inertiajs/react';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { ReconRun } from '../types';

const LABELS: Record<string, string> = { sales: 'Sales', payments: 'Payments', postings: 'ERP postings' };

export function RunBatches({ batches }: { batches: ReconRun['batches'] }) {
    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Source</TableHead>
                    <TableHead>Batch</TableHead>
                    <TableHead>How it arrived</TableHead>
                    <TableHead className="text-right">Rows loaded</TableHead>
                    <TableHead className="text-right">Quarantined</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {Object.entries(batches).map(([source, batch]) => (
                    <TableRow key={source}>
                        <TableCell>{LABELS[source] ?? source}</TableCell>
                        <TableCell>
                            {batch === null ? (
                                <span className="text-destructive">No data</span>
                            ) : (
                                <Link href={route('ingestion.batches.show', batch.id)} className="text-primary hover:underline">
                                    #{batch.id} v{batch.version}
                                </Link>
                            )}
                        </TableCell>
                        <TableCell>{batch === null ? '—' : batch.manual ? `Manual (${batch.mode.replace('_', ' ')})` : 'Pulled from source system'}</TableCell>
                        <TableCell className="text-right tabular-nums">{batch?.rows.toLocaleString() ?? '—'}</TableCell>
                        <TableCell className="text-right tabular-nums">{batch?.quarantined.toLocaleString() ?? '—'}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
