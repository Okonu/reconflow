import { EmptyState } from '@/components/empty-state';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import type { PreviewRow } from '../types';
import { StateBadge } from './StateBadge';

export function PreviewTable({ columns, rows }: { columns: string[]; rows: PreviewRow[] }) {
    if (rows.length === 0) {
        return <EmptyState title="No rows to show" description="Adjust the filter to see all rows." />;
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Row</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead>Problems</TableHead>
                    {columns.map((column) => (
                        <TableHead key={column}>{column}</TableHead>
                    ))}
                </TableRow>
            </TableHeader>
            <TableBody>
                {rows.map((row) => (
                    <TableRow key={row.row}>
                        <TableCell className="tabular-nums">{row.row}</TableCell>
                        <TableCell>
                            <StateBadge state={row.status} />
                        </TableCell>
                        <TableCell className="min-w-56 text-destructive">{row.errors.join('; ')}</TableCell>
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
