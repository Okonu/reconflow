import { Head, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardDescription, CardHeader, CardTitle } from '@/components/ui/card';
import { Checkbox } from '@/components/ui/checkbox';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';

type FieldValue = string | number | boolean;

interface Field {
    name: string;
    label: string;
    type: 'money' | 'number' | 'time' | 'text' | 'date' | 'select' | 'boolean';
    help?: string;
    options?: string[];
}

interface Version {
    version: number;
    values: Record<string, FieldValue>;
    comment: string;
    by: string | null;
    at: string | null;
}

interface Section {
    key: string;
    label: string;
    description: string;
    fields: Field[];
    values: Record<string, FieldValue>;
    history: Version[];
    can_manage: boolean;
}

function FieldInput({ field, value, disabled, onChange }: { field: Field; value: FieldValue; disabled: boolean; onChange: (v: FieldValue) => void }) {
    const id = `field-${field.name}`;
    if (field.type === 'boolean') {
        return (
            <label className="flex items-center gap-2 text-sm">
                <Checkbox id={id} checked={value === true} disabled={disabled} onCheckedChange={(v) => onChange(v === true)} />
                {field.label}
            </label>
        );
    }
    if (field.type === 'select') {
        return (
            <NativeSelect id={id} className="w-full" value={String(value)} disabled={disabled} onChange={(e) => onChange(e.target.value)}>
                {field.options?.map((o) => (
                    <option key={o} value={o}>
                        {o}
                    </option>
                ))}
            </NativeSelect>
        );
    }
    const type = field.type === 'number' ? 'number' : field.type === 'time' ? 'time' : field.type === 'date' ? 'date' : 'text';
    return <Input id={id} type={type} inputMode={field.type === 'money' ? 'decimal' : undefined} value={String(value ?? '')} disabled={disabled} onChange={(e) => onChange(e.target.value)} />;
}

function SectionCard({ section }: { section: Section }) {
    const form = useForm<Record<string, FieldValue>>({ ...section.values, comment: '' });
    const [showHistory, setShowHistory] = useState(false);
    const errors = form.errors as Record<string, string | undefined>;
    const latest = section.history[0];

    return (
        <Card>
            <CardHeader>
                <CardTitle>{section.label}</CardTitle>
                <CardDescription>
                    {section.description}
                    {latest && ` Current version ${latest.version}, set by ${latest.by ?? 'System'} ${formatDateTime(latest.at)}.`}
                </CardDescription>
            </CardHeader>
            <CardContent>
                <form
                    className="space-y-4"
                    onSubmit={(e) => {
                        e.preventDefault();
                        form.put(route('settings.update', section.key), { preserveScroll: true, onSuccess: () => form.setData('comment', '') });
                    }}
                >
                    <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {section.fields.map((field) => (
                            <div key={field.name} className="space-y-1">
                                {field.type !== 'boolean' && <Label htmlFor={`field-${field.name}`}>{field.label}</Label>}
                                <FieldInput field={field} value={form.data[field.name]} disabled={!section.can_manage} onChange={(v) => form.setData(field.name, v)} />
                                {field.help && <p className="text-xs text-muted-foreground">{field.help}</p>}
                                <FieldError message={errors[field.name]} />
                            </div>
                        ))}
                    </div>
                    {section.can_manage && (
                        <div className="flex flex-wrap items-end gap-3">
                            <div className="min-w-64 flex-1 space-y-1">
                                <Label htmlFor={`comment-${section.key}`}>Reason for the change</Label>
                                <Input id={`comment-${section.key}`} value={String(form.data.comment ?? '')} onChange={(e) => form.setData('comment', e.target.value)} />
                                <FieldError message={errors.comment} />
                            </div>
                            <Button type="submit" disabled={form.processing || !form.isDirty}>
                                Save new version
                            </Button>
                        </div>
                    )}
                </form>
                {section.history.length > 0 && (
                    <div className="mt-4 border-t pt-3">
                        <button type="button" className="text-sm text-primary hover:underline" onClick={() => setShowHistory((s) => !s)}>
                            {showHistory ? 'Hide history' : `Show history (${section.history.length})`}
                        </button>
                        {showHistory && (
                            <ol className="mt-2 space-y-2 text-sm">
                                {section.history.map((v) => (
                                    <li key={v.version} className="rounded-md bg-muted/40 p-2">
                                        <p>
                                            <span className="font-medium">v{v.version}</span> · {v.by ?? 'System'} · {formatDateTime(v.at)}
                                            {v.comment && ` · “${v.comment}”`}
                                        </p>
                                        <p className="font-mono text-xs text-muted-foreground">
                                            {Object.entries(v.values)
                                                .map(([k, val]) => `${k}=${String(val)}`)
                                                .join('  ')}
                                        </p>
                                    </li>
                                ))}
                            </ol>
                        )}
                    </div>
                )}
            </CardContent>
        </Card>
    );
}

export default function Settings({ sections }: { sections: Section[] }) {
    return (
        <AppLayout>
            <Head title="Settings" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Settings</h1>
                    <p className="text-sm text-muted-foreground">Every change creates a new version with your name and reason, and is written to the audit log.</p>
                </div>
                {sections.map((section) => (
                    <SectionCard key={section.key} section={section} />
                ))}
            </div>
        </AppLayout>
    );
}
