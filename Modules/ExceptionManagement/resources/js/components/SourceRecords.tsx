import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime, formatMoney } from '@/lib/format';
import type { SourceRecordSet } from '../types';

const MONEY_FIELDS = ['expected_amount', 'amount'];
const TIME_FIELDS = ['timestamp'];

function Field({ name, value }: { name: string; value: string | null }) {
    const shown = value === null || value === '' ? '—' : MONEY_FIELDS.includes(name) ? formatMoney(value) : TIME_FIELDS.includes(name) ? formatDateTime(value) : value;
    return (
        <div className="grid grid-cols-[9rem_1fr] gap-2 py-1 text-sm">
            <dt className="text-muted-foreground">{name.replaceAll('_', ' ')}</dt>
            <dd className="break-all font-mono text-xs leading-5">{shown}</dd>
        </div>
    );
}

function RecordColumn({ title, records, empty }: { title: string; records: Record<string, string | null>[]; empty: string }) {
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
                        <dl key={index} className={index > 0 ? 'border-t pt-3' : undefined}>
                            {Object.entries(record).map(([name, value]) => (
                                <Field key={name} name={name} value={value} />
                            ))}
                        </dl>
                    ))
                )}
            </CardContent>
        </Card>
    );
}

export function SourceRecords({ records }: { records: SourceRecordSet }) {
    return (
        <div className="grid gap-4 lg:grid-cols-3">
            <RecordColumn title="Sale" records={records.sale ? [records.sale] : []} empty="No sale: this payment has no matching sale." />
            <RecordColumn title="Payments" records={records.payments} empty="No payment received." />
            <RecordColumn title="ERP postings" records={records.postings} empty="Nothing posted to the ERP." />
        </div>
    );
}
