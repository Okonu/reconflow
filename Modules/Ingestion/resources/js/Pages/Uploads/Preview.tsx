import { Head, Link, router } from '@inertiajs/react';
import { PaginationLinks } from '@/components/pagination-links';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDate } from '@/lib/format';
import { ConfirmPanel } from '../../components/ConfirmPanel';
import { HeaderCheckAlert } from '../../components/HeaderCheckAlert';
import { PreviewTable } from '../../components/PreviewTable';
import { StateBadge } from '../../components/StateBadge';
import { UploadSummary } from '../../components/UploadSummary';
import type { PreviewRow, StagedUpload } from '../../types';

interface Props {
    upload: { data: StagedUpload };
    columns: string[];
    rows: PreviewRow[];
    pagination: { page: number; last_page: number; total: number };
    append_conflicts: number;
    filters: { invalid_only: boolean };
}

export default function UploadPreview({ upload: { data: upload }, columns, rows, pagination, append_conflicts, filters }: Props) {
    const reload = (params: { page?: number; invalid_only?: boolean }) =>
        router.get(route('ingestion.uploads.show', upload.id), { invalid_only: filters.invalid_only ? 1 : 0, ...params }, { preserveState: true, preserveScroll: true, only: ['rows', 'pagination', 'filters'] });

    return (
        <AppLayout>
            <Head title={`Preview: ${upload.filename}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-2">
                    <div>
                        <Link href={route('ingestion.uploads.index')} className="text-sm text-muted-foreground hover:underline">
                            ← Data uploads
                        </Link>
                        <h1 className="text-2xl font-semibold">{upload.filename}</h1>
                        <p className="text-sm text-muted-foreground">
                            {upload.source_label} for {formatDate(upload.business_date)} · uploaded by {upload.uploaded_by}
                        </p>
                    </div>
                    <StateBadge state={upload.state} />
                </div>
                <HeaderCheckAlert check={upload.header_check} />
                {upload.header_check.ok && <UploadSummary upload={upload} appendConflicts={append_conflicts} />}
                {upload.state === 'confirmed' && upload.batch_id !== null && (
                    <Link href={route('ingestion.batches.show', upload.batch_id)} className="text-sm text-primary hover:underline">
                        View the imported batch and its data-quality report →
                    </Link>
                )}
                <ConfirmPanel upload={upload} />
                {upload.header_check.ok && upload.state === 'staged' && (
                    <Card>
                        <CardHeader className="flex flex-row items-center justify-between space-y-0">
                            <CardTitle>Rows ({pagination.total.toLocaleString()})</CardTitle>
                            <label className="flex items-center gap-2 text-sm">
                                <input type="checkbox" checked={filters.invalid_only} onChange={(e) => reload({ invalid_only: e.target.checked, page: 1 })} />
                                Show invalid rows only
                            </label>
                        </CardHeader>
                        <CardContent>
                            <PreviewTable columns={columns} rows={rows} />
                            <PaginationLinks page={pagination.page} lastPage={pagination.last_page} onChange={(page) => reload({ page })} />
                        </CardContent>
                    </Card>
                )}
            </div>
        </AppLayout>
    );
}
