import { Link } from '@inertiajs/react';
import { EmptyState } from '@/components/empty-state';
import { Table, TableBody, TableCell, TableHead, TableHeader, TableRow } from '@/components/ui/table';
import { formatDate, formatDateTime } from '@/lib/format';
import type { StagedUpload } from '../types';
import { StateBadge } from './StateBadge';

export function UploadHistory({ uploads }: { uploads: StagedUpload[] }) {
    if (uploads.length === 0) {
        return <EmptyState title="No uploads yet" description="Uploaded files appear here with who uploaded them, when, and the outcome." />;
    }

    return (
        <Table>
            <TableHeader>
                <TableRow>
                    <TableHead>When</TableHead>
                    <TableHead>Who</TableHead>
                    <TableHead>Source</TableHead>
                    <TableHead>Business date</TableHead>
                    <TableHead>File</TableHead>
                    <TableHead className="text-right">Rows</TableHead>
                    <TableHead className="text-right">Invalid</TableHead>
                    <TableHead>Outcome</TableHead>
                </TableRow>
            </TableHeader>
            <TableBody>
                {uploads.map((upload) => (
                    <TableRow key={upload.id}>
                        <TableCell className="whitespace-nowrap">{formatDateTime(upload.created_at)}</TableCell>
                        <TableCell>{upload.uploaded_by ?? '—'}</TableCell>
                        <TableCell>{upload.source_label}</TableCell>
                        <TableCell className="whitespace-nowrap">{formatDate(upload.business_date)}</TableCell>
                        <TableCell className="max-w-48 truncate">
                            <Link href={route('ingestion.uploads.show', upload.id)} className="text-primary hover:underline">
                                {upload.filename}
                            </Link>
                        </TableCell>
                        <TableCell className="text-right tabular-nums">{upload.rows_read}</TableCell>
                        <TableCell className="text-right tabular-nums">{upload.rows_invalid}</TableCell>
                        <TableCell>
                            <StateBadge state={upload.state} />
                        </TableCell>
                    </TableRow>
                ))}
            </TableBody>
        </Table>
    );
}
