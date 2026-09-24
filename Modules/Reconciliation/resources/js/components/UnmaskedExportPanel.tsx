import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { downloadPost, HttpError } from '@/lib/http';

export function UnmaskedExportPanel({ filters, onDone }: { filters: object; onDone: () => void }) {
    const [reason, setReason] = useState('');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    const download = async (format: 'csv' | 'xlsx') => {
        setBusy(true);
        setError(null);
        try {
            await downloadPost(route('reports.reconciliation.export-unmasked'), { ...filters, format, reason });
            setReason('');
            onDone();
        } catch (e) {
            setError(e instanceof HttpError ? e.message : 'Export failed.');
        } finally {
            setBusy(false);
        }
    };

    return (
        <Card className="border-amber-300">
            <CardContent className="space-y-3 pt-6">
                <p className="text-sm">Unmasked exports contain customer phone numbers. State why you need them; the export and your reason are written to the audit log.</p>
                <Input placeholder="Reason (at least 10 characters)" value={reason} onChange={(e) => setReason(e.target.value)} />
                {error && <p className="text-sm text-destructive">{error}</p>}
                <div className="flex gap-2">
                    <Button size="sm" disabled={busy || reason.trim().length < 10} onClick={() => download('xlsx')}>
                        Download unmasked XLSX
                    </Button>
                    <Button size="sm" variant="outline" disabled={busy || reason.trim().length < 10} onClick={() => download('csv')}>
                        Download unmasked CSV
                    </Button>
                </div>
            </CardContent>
        </Card>
    );
}
