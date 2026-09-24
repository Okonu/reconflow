import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { usePermissions } from '@/hooks/use-permissions';
import { formatDateTime, formatMoney } from '@/lib/format';
import { http, HttpError } from '@/lib/http';
import type { SourceRecord, SourceRecordSet } from '../types';

const MONEY_FIELDS = ['expected_amount', 'amount'];
const TIME_FIELDS = ['timestamp'];

function Field({ name, value }: { name: string; value: string | number | null }) {
    const text = value === null ? null : String(value);
    const shown = text === null || text === '' ? '—' : MONEY_FIELDS.includes(name) ? formatMoney(text) : TIME_FIELDS.includes(name) ? formatDateTime(text) : text;
    return (
        <div className="grid grid-cols-[9rem_1fr] gap-2 py-1 text-sm">
            <dt className="text-muted-foreground">{name.replaceAll('_', ' ')}</dt>
            <dd className="break-all font-mono text-xs leading-5">{shown}</dd>
        </div>
    );
}

function Unmask({ dataset, record, onReveal }: { dataset: 'sales' | 'payments'; record: SourceRecord; onReveal: (values: Record<string, string>) => void }) {
    const [open, setOpen] = useState(false);
    const [purpose, setPurpose] = useState('');
    const [error, setError] = useState<string | null>(null);

    const reveal = async () => {
        try {
            const response = await http<{ values: Record<string, string> }>('POST', route('pii.unmask'), { dataset, record_id: record._id, purpose });
            onReveal(response.values);
            setOpen(false);
        } catch (e) {
            setError(e instanceof HttpError ? e.message : 'Could not unmask.');
        }
    };

    if (!open) {
        return (
            <button type="button" className="text-xs text-primary hover:underline" onClick={() => setOpen(true)}>
                Show phone number
            </button>
        );
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input className="h-8 flex-1 text-xs" placeholder="Purpose (audited)" value={purpose} onChange={(e) => setPurpose(e.target.value)} />
            <Button size="sm" className="h-8" disabled={purpose.trim().length < 5} onClick={reveal}>
                Unmask
            </Button>
            {error && <p className="w-full text-xs text-destructive">{error}</p>}
        </div>
    );
}

function RecordEntry({ record, dataset }: { record: SourceRecord; dataset?: 'sales' | 'payments' }) {
    const { can } = usePermissions();
    const [revealed, setRevealed] = useState<Record<string, string>>({});
    const shown = { ...record, ...revealed };

    return (
        <dl>
            {Object.entries(shown)
                .filter(([name]) => !name.startsWith('_'))
                .map(([name, value]) => (
                    <Field key={name} name={name} value={value} />
                ))}
            {dataset && record._id !== undefined && can('pii.unmask') && Object.keys(revealed).length === 0 && <Unmask dataset={dataset} record={record} onReveal={setRevealed} />}
        </dl>
    );
}

function RecordColumn({ title, records, empty, dataset }: { title: string; records: SourceRecord[]; empty: string; dataset?: 'sales' | 'payments' }) {
    return (
        <Card>
            <CardHeader className="pb-2">
                <CardTitle className="text-base">{title}</CardTitle>
            </CardHeader>
            <CardContent className="space-y-4">
                {records.length === 0 ? (
                    <p className="text-sm text-muted-foreground">{empty}</p>
                ) : (
                    records.map((record, index) => (
                        <div key={index} className={index > 0 ? 'border-t pt-3' : undefined}>
                            <RecordEntry record={record} dataset={dataset} />
                        </div>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

export function SourceRecords({ records }: { records: SourceRecordSet }) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <RecordColumn title="Sale" dataset="sales" records={records.sale ? [records.sale] : []} empty="No sale: this payment has no matching sale." />
            <RecordColumn title="Payments" dataset="payments" records={records.payments} empty="No payment received." />
            <RecordColumn title="ERP postings" records={records.postings} empty="Nothing posted to the ERP." />
        </div>
    );
}
