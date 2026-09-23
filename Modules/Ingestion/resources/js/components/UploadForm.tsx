import { useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { SourceOption } from '../types';

export function UploadForm({ sources, defaultDate }: { sources: SourceOption[]; defaultDate: string }) {
    const form = useForm<{ source: string; business_date: string; file: File | null }>({
        source: sources[0]?.value ?? 'sales',
        business_date: defaultDate,
        file: null,
    });

    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('ingestion.uploads.store'), { forceFormData: true });
    };

    return (
        <form onSubmit={submit} className="grid gap-4 sm:grid-cols-4 sm:items-end">
            <div className="space-y-2">
                <Label htmlFor="source">Source</Label>
                <select
                    id="source"
                    className="h-10 w-full rounded-md border border-input bg-background px-3 text-sm"
                    value={form.data.source}
                    onChange={(e) => form.setData('source', e.target.value)}
                >
                    {sources.map((s) => (
                        <option key={s.value} value={s.value}>
                            {s.label}
                        </option>
                    ))}
                </select>
            </div>
            <div className="space-y-2">
                <Label htmlFor="business_date">Business date</Label>
                <Input id="business_date" type="date" value={form.data.business_date} onChange={(e) => form.setData('business_date', e.target.value)} />
                {form.errors.business_date && <p className="text-sm text-destructive">{form.errors.business_date}</p>}
            </div>
            <div className="space-y-2">
                <Label htmlFor="file">File (.xlsx or .csv, max 10 MB)</Label>
                <Input id="file" type="file" accept=".xlsx,.csv" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} />
                {form.errors.file && <p className="text-sm text-destructive">{form.errors.file}</p>}
            </div>
            <Button type="submit" disabled={form.processing || form.data.file === null}>
                Upload and preview
            </Button>
        </form>
    );
}
