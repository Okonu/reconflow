import { useForm } from '@inertiajs/react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { ContributionProps } from '@/lib/contributions';
import { formatDateTime, formatMoney } from '@/lib/format';
import { AdjustmentStateBadge } from '../components/AdjustmentStateBadge';
import { DecisionButtons } from '../components/DecisionButtons';
import { JournalPreview } from '../components/JournalPreview';
import type { AdjustmentContribution } from '../types';

export const key = 'adjustments';
export const order = 20;

export default function AdjustmentsPanel({ data, exceptionId }: ContributionProps<AdjustmentContribution>) {
    const form = useForm({ type: data.types[0]?.value ?? '', amount: data.suggested_amount, reason: '' });
    const overThreshold = Number(form.data.amount) > Number(data.threshold);

    return (
        <Card>
            <CardHeader>
                <CardTitle className="text-base">Adjustments</CardTitle>
            </CardHeader>
            <CardContent className="space-y-6">
                {data.items.length > 0 && (
                    <ul className="space-y-4">
                        {data.items.map((a) => (
                            <li key={a.id} className="space-y-2 rounded-md border p-3">
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <p className="font-medium">
                                        {a.type_label} · {formatMoney(a.amount)} {a.high_value && <span className="text-xs text-amber-700">(Finance Manager approval)</span>}
                                    </p>
                                    <AdjustmentStateBadge state={a.state} label={a.state_label} />
                                </div>
                                <p className="text-sm">{a.reason}</p>
                                <p className="text-xs text-muted-foreground">
                                    Proposed by {a.proposed_by} {formatDateTime(a.created_at)}
                                    {a.decided_by && ` · decided by ${a.decided_by}`}
                                    {a.erp_journal_id && ` · ERP journal ${a.erp_journal_id}`}
                                    {` · idempotency key ${a.idempotency_key}`}
                                </p>
                                {a.decision_comment && <p className="text-sm italic">“{a.decision_comment}”</p>}
                                <JournalPreview journal={a.journal} />
                                <DecisionButtons adjustment={a} />
                            </li>
                        ))}
                    </ul>
                )}
                {data.can_propose && (
                    <form
                        className="space-y-3"
                        onSubmit={(e) => {
                            e.preventDefault();
                            form.post(route('adjustments.store', exceptionId), { preserveScroll: true, onSuccess: () => form.reset('reason') });
                        }}
                    >
                        <p className="text-sm font-medium">Propose a correction</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <div className="space-y-1">
                                <Label htmlFor="adj-type">Type</Label>
                                <NativeSelect id="adj-type" className="w-full" value={form.data.type} onChange={(e) => form.setData('type', e.target.value)}>
                                    {data.types.map((t) => (
                                        <option key={t.value} value={t.value}>
                                            {t.label}
                                        </option>
                                    ))}
                                </NativeSelect>
                                <FieldError message={form.errors.type} />
                            </div>
                            <div className="space-y-1">
                                <Label htmlFor="adj-amount">Amount (USD)</Label>
                                <Input id="adj-amount" inputMode="decimal" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} />
                                <FieldError message={form.errors.amount} />
                            </div>
                        </div>
                        <div className="space-y-1">
                            <Label htmlFor="adj-reason">Reason</Label>
                            <Textarea id="adj-reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="Evidence and justification for the approver" />
                            <FieldError message={form.errors.reason} />
                        </div>
                        {overThreshold && <p className="text-xs text-amber-700">Above {formatMoney(data.threshold)}: a Finance Manager must approve.</p>}
                        <Button type="submit" size="sm" disabled={form.processing}>
                            Submit for approval
                        </Button>
                        <p className="text-xs text-muted-foreground">Someone other than you must approve it before anything is posted to the ERP.</p>
                    </form>
                )}
                {data.items.length === 0 && !data.can_propose && <p className="text-sm text-muted-foreground">No adjustments for this exception.</p>}
            </CardContent>
        </Card>
    );
}
