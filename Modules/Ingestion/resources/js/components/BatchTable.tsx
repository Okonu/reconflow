import { Link } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import type { SourceBatch } from '../types';
import { StateBadge } from './StateBadge';

const ORIGIN: Record<SourceBatch['origin'], string> = { source_system: 'Source system', upload: 'Upload' };

export function BatchTable({ batches }: { batches: SourceBatch[] }) {
    if (batches.length === 0) {
        return <EmptyState title="No source batches" description="Batches appear when data is pulled from the source systems or a file upload is confirmed." />;
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Business date</TableHead>
                    <TableHead>Source</TableHead>
                    <TableHead>Version</TableHead>
                    <TableHead>Origin</TableHead>
                    <TableHead className="text-right">Rows in</TableHead>
                    <TableHead className="text-right">Loaded</TableHead>
                    <TableHead className="text-right">Quarantined</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Received</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {batches.map((batch) => (
                    <TableRow key={batch.id}>
                        <TableCell className="whitespace-nowrap">{formatDate(batch.business_date)}</TableCell>
                        <TableCell>
                            <Link href={route('ingestion.batches.show', batch.id)} className="text-primary hover:underline">
                                {batch.source_label}
                            </Link>
                        </TableCell>
                        <TableCell className="tabular-nums">v{batch.version}</TableCell>
                        <TableCell>{ORIGIN[batch.origin]}</TableCell>
                        <TableCell className="text-right tabular-nums">{batch.rows_received.toLocaleString()}</TableCell>
                        <TableCell className="text-right tabular-nums">{batch.rows_loaded.toLocaleString()}</TableCell>
                        <TableCell className={batch.rows_quarantined > 0 ? 'text-right text-destructive tabular-nums' : 'text-right tabular-nums'}>{batch.rows_quarantined.toLocaleString()}</TableCell>
                        <TableCell>
                            <StateBadge state={batch.status} />
                        </TableCell>
                        <TableCell className="whitespace-nowrap">{formatDateTime(batch.created_at)}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
