import { router, useForm } from '@inertiajs/react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import type { StagedUpload } from '../types';

export function ConfirmPanel({ upload }: { upload: StagedUpload }) {
    const form = useForm<{ mode: 'replace' | 'append' | null }>({ mode: upload.date_has_data ? null : 'replace' });

    if (!upload.can.confirm && !upload.can.cancel) {
        return null;
    }

    return (
        <Card>
            <CardHeader>
                <CardTitle>Import this file</CardTitle>
                <CardDescription>Valid rows are loaded; invalid rows are quarantined with their reasons.</CardDescription>
            </CardHeader>
            <CardContent className="space-y-4">
                {upload.date_has_data && upload.can.confirm && (
                    <fieldset className="space-y-2 text-sm">
                        <legend className="mb-2 font-medium">This date already has {upload.source_label.toLowerCase()} data. How should this file be imported?</legend>
                        <label className="flex items-start gap-2">
                            <input type="radio" name="mode" checked={form.data.mode === 'replace'} onChange={() => form.setData('mode', 'replace')} />
                            <span>
                                <strong>Replace</strong>: this file becomes the data for the date. The previous batch is kept for audit.
                            </span>
                        </label>
                        <label className="flex items-start gap-2">
                            <input type="radio" name="mode" checked={form.data.mode === 'append'} onChange={() => form.setData('mode', 'append')} />
                            <span>
                                <strong>Append</strong>: add these rows to the existing data.
                            </span>
                        </label>
                    </fieldset>
                )}
                <div className="flex flex-wrap gap-2">
                    {upload.can.confirm && (
                        <Button disabled={form.processing || form.data.mode === null} onClick={() => form.post(route('ingestion.uploads.confirm', upload.id))}>
                            Confirm import
                        </Button>
                    )}
                    {upload.can.cancel && (
                        <Button variant="outline" onClick={() => router.post(route('ingestion.uploads.cancel', upload.id))}>
                            Cancel
                        </Button>
                    )}
                </div>
            </CardContent>
        </Card>
    );
}
