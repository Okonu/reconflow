import { Head, router } from '@inertiajs/react';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import AppLayout from '@/layouts/app-layout';
import { BatchTable } from '../../components/BatchTable';
import type { SourceBatch } from '../../types';

interface Props {
    batches: { data: SourceBatch[] };
    filters: { business_date: string | null; source: string | null; include_superseded: boolean };
}

export default function BatchesIndex({ batches, filters }: Props) {
    const apply = (changes: Partial<Props['filters']>) =>
        router.get(route('ingestion.batches.index'), { ...filters, ...changes, include_superseded: (changes.include_superseded ?? filters.include_superseded) ? 1 : 0 }, { preserveState: true });

    return (
        <AppLayout>
            <Head title="Source batches" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Source batches</h1>
                    <p className="text-sm text-muted-foreground">Every ingestion from a source system or confirmed upload, with its data-quality result.</p>
                </div>
                <div className="flex flex-wrap items-center gap-3">
                    <Input type="date" className="w-44" value={filters.business_date ?? ''} onChange={(e) => apply({ business_date: e.target.value || null })} />
                    <select className="h-10 rounded-md border border-input bg-background px-3 text-sm" value={filters.source ?? ''} onChange={(e) => apply({ source: e.target.value || null })}>
                        <option value="">All sources</option>
                        <option value="sales">Sales</option>
                        <option value="payments">Payments</option>
                        <option value="postings">ERP postings</option>
                    </select>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={filters.include_superseded} onChange={(e) => apply({ include_superseded: e.target.checked })} />
                        Include superseded versions
                    </label>
                </div>
                <Card>
                    <CardContent className="pt-6">
                        <BatchTable batches={batches.data} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
