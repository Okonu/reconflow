import { EmptyState } from '@/components/empty-state';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { QuarantinedRow } from '../types';

export function QuarantineTable({ rows }: { rows: QuarantinedRow[] }) {
    if (rows.length === 0) {
        return <EmptyState title="No quarantined rows" description="Every row in this batch passed the data-quality checks." />;
    }
    const columns = Object.keys(rows[0].values);

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Row</TableHead>
                    <TableHead>Record</TableHead>
                    <TableHead>Reason</TableHead>
                    {columns.map((column) => (
                        <TableHead key={column}>{column}</TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.row_number}>
                        <TableCell className="tabular-nums">{row.row_number}</TableCell>
                        <TableCell className="font-mono text-xs">{row.record_key}</TableCell>
                        <TableCell className="min-w-56 text-destructive">{row.reasons.join('; ')}</TableCell>
                        {columns.map((column) => (
                            <TableCell key={column} className="whitespace-nowrap">
                                {row.values[column] ?? ''}
                            </TableCell>
                        ))}
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
