import { Link } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import type { ReconRun } from '../types';
import { RunStatusBadge } from './RunStatusBadge';

export function RunHistory({ runs }: { runs: ReconRun[] }) {
    if (runs.length === 0) {
        return <EmptyState title="No runs yet" description="Runs appear here after the daily job or a Run now." />;
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>Business date</TableHead>
                    <TableHead>Version</TableHead>
                    <TableHead>Status</TableHead>
                    <TableHead className="text-right">Items</TableHead>
                    <TableHead className="text-right">Match rate</TableHead>
                    <TableHead className="text-right">Exceptions</TableHead>
                    <TableHead className="text-right">Duration</TableHead>
                    <TableHead>Started</TableHead>
                    <TableHead>By</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {runs.map((run) => (
                    <TableRow key={run.id}>
                        <TableCell className="whitespace-nowrap">
                            <Link href={route('runs.show', run.id)} className="text-primary hover:underline">
                                {formatDate(run.business_date)}
                            </Link>
                        </TableCell>
                        <TableCell className="tabular-nums">v{run.version}</TableCell>
                        <TableCell>
                            <RunStatusBadge run={run} />
                        </TableCell>
                        <TableCell className="text-right tabular-nums">{run.summary.items?.toLocaleString() ?? '—'}</TableCell>
                        <TableCell className="text-right tabular-nums">{run.summary.match_rate ? `${run.summary.match_rate}%` : '—'}</TableCell>
                        <TableCell className="text-right tabular-nums">{run.summary.exceptions?.toLocaleString() ?? '—'}</TableCell>
                        <TableCell className="text-right tabular-nums">{run.duration_ms === null ? '—' : `${(run.duration_ms / 1000).toFixed(1)} s`}</TableCell>
                        <TableCell className="whitespace-nowrap">{formatDateTime(run.started_at)}</TableCell>
                        <TableCell>{run.triggered_by ?? run.trigger}</TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
