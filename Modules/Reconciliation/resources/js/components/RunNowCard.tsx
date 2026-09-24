import { router, useForm } from '@inertiajs/react';
import type { FormEvent } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import type { Readiness } from '../types';

export function RunNowCard({ readiness }: { readiness: Readiness }) {
    const form = useForm<{ business_date: string; refresh: boolean; replace_manual: string[] }>({ business_date: readiness.date, refresh: false, replace_manual: [] });
    const manual = readiness.sources.filter((s) => s.manual);

    const changeDate = (date: string) => {
        form.setData('business_date', date);
        router.reload({ data: { date }, only: ['readiness'] });
    };
    const toggleReplace = (source: string, replace: boolean) =>
        form.setData('replace_manual', replace ? [...form.data.replace_manual, source] : form.data.replace_manual.filter((s) => s !== source));
    const submit = (event: FormEvent) => {
        event.preventDefault();
        form.post(route('runs.store'));
    };

    return (
        <Card>
            <CardHeader>
                <CardTitle>Run now</CardTitle>
                <CardDescription>Reconciles the data currently loaded for the date. The latest closed business date is selected by default.</CardDescription>
            </CardHeader>
            <CardContent>
                <form onSubmit={submit} className="space-y-4">
                    <div className="grid gap-4 sm:grid-cols-[12rem_1fr] sm:items-end">
                        <div className="space-y-2">
                            <Label htmlFor="business_date">Business date</Label>
                            <Input id="business_date" type="date" value={form.data.business_date} onChange={(e) => changeDate(e.target.value)} />
                        </div>
                        <ul className="flex flex-wrap gap-3 text-sm">
                            {readiness.sources.map((s) => (
                                <li key={s.source} className="rounded-md border px-3 py-1.5">
                                    <span className="font-medium">{s.label}</span>: {s.batch_id === null ? <span className="text-destructive">no data</span> : `v${s.version}, ${s.rows.toLocaleString()} rows${s.manual ? ' (manual upload)' : ''}`}
                                </li>
                            ))}
                        </ul>
                    </div>
                    {!readiness.closed && (
                        <p className="rounded-md border border-warning/40 bg-warning/10 px-3 py-2 text-sm">
                            This date's payment window is still open. The run will be provisional: timing exceptions are expected and it cannot be signed off.
                        </p>
                    )}
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" checked={form.data.refresh} onChange={(e) => form.setData('refresh', e.target.checked)} />
                        Refresh from source systems first
                    </label>
                    {form.data.refresh && manual.length > 0 && (
                        <fieldset className="space-y-2 rounded-md border px-3 py-2 text-sm">
                            <legend className="px-1 font-medium">These sources use a manual upload. Keep it or replace it with a fresh pull?</legend>
                            {manual.map((s) => (
                                <label key={s.source} className="flex items-center gap-3">
                                    <span className="w-28">{s.label}</span>
                                    <select className="h-9 rounded-md border border-input bg-background px-2" value={form.data.replace_manual.includes(s.source) ? 'replace' : 'keep'} onChange={(e) => toggleReplace(s.source, e.target.value === 'replace')}>
                                        <option value="keep">Keep the upload</option>
                                        <option value="replace">Replace with a pull</option>
                                    </select>
                                </label>
                            ))}
                        </fieldset>
                    )}
                    {form.errors.business_date && <p className="text-sm text-destructive">{form.errors.business_date}</p>}
                    <Button type="submit" disabled={form.processing}>
                        Run reconciliation
                    </Button>
                </form>
            </CardContent>
        </Card>
    );
}
