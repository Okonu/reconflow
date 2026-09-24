import { router, useForm } from '@inertiajs/react';
import { useState } from 'react';
import { FieldError } from '@/components/field-error';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { Label } from '@/components/ui/label';
import { NativeSelect } from '@/components/ui/native-select';
import { Textarea } from '@/components/ui/textarea';
import type { ContributionProps } from '@/lib/contributions';
import { formatDateTime } from '@/lib/format';
import { AiLabel } from '../components/AiLabel';
import type { AiSuggestion } from '../types';

interface AiContribution {
    enabled: boolean;
    reason: string | null;
    model: string;
    latest: AiSuggestion | null;
    actions: { value: string; label: string }[];
    can_request: boolean;
}

export const key = 'ai';
export const order = 10;

function Decision({ suggestion, actions }: { suggestion: AiSuggestion; actions: AiContribution['actions'] }) {
    const [overriding, setOverriding] = useState(false);
    const form = useForm({ decision: 'override', action: '', reason: '' });

    if (!suggestion.can.decide) {
        return null;
    }
    if (!overriding) {
        return (
            <div className="flex gap-2">
                <Button size="sm" onClick={() => router.post(route('ai.decide', suggestion.id), { decision: 'accept' }, { preserveScroll: true })}>
                    Accept suggestion
                </Button>
                <Button size="sm" variant="outline" onClick={() => setOverriding(true)}>
                    Override
                </Button>
            </div>
        );
    }

    return (
        <form
            className="space-y-2 rounded-md border p-3"
            onSubmit={(e) => {
                e.preventDefault();
                form.post(route('ai.decide', suggestion.id), { preserveScroll: true });
            }}
        >
            <Label htmlFor="ai-override-action">What will you do instead? (optional)</Label>
            <NativeSelect id="ai-override-action" className="w-full" value={form.data.action} onChange={(e) => form.setData('action', e.target.value)}>
                <option value="">Not specified</option>
                {actions.map((a) => (
                    <option key={a.value} value={a.value}>
                        {a.label}
                    </option>
                ))}
            </NativeSelect>
            <Label htmlFor="ai-override-reason">Why is the suggestion wrong?</Label>
            <Textarea id="ai-override-reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} />
            <FieldError message={form.errors.reason} />
            <div className="flex gap-2">
                <Button size="sm" type="submit" disabled={form.processing}>
                    Record override
                </Button>
                <Button size="sm" type="button" variant="ghost" onClick={() => setOverriding(false)}>
                    Cancel
                </Button>
            </div>
        </form>
    );
}

export default function AiPanel({ data, exceptionId }: ContributionProps<AiContribution>) {
    const [requesting, setRequesting] = useState(false);
    const suggestion = data.latest;
    const request = () =>
        router.post(route('ai.triage', exceptionId), {}, { preserveScroll: true, onStart: () => setRequesting(true), onFinish: () => setRequesting(false) });

    return (
        <Card className="border-violet-200">
            <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle className="text-base">AI triage assistant</CardTitle>
                {data.can_request && (
                    <Button size="sm" variant="outline" onClick={request} disabled={requesting}>
                        {requesting ? 'Thinking…' : suggestion ? 'Ask again' : 'Suggest a cause and next step'}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                {!data.enabled && <p className="text-muted-foreground">{data.reason ?? 'The AI assistant is unavailable.'}</p>}
                {suggestion?.status === 'failed' && <p className="text-destructive">Last request failed: {suggestion.error}</p>}
                {suggestion && suggestion.status !== 'failed' && (
                    <div className="space-y-3">
                        <div className="flex flex-wrap items-center gap-2">
                            <AiLabel model={suggestion.model} label={suggestion.origin_label} />
                            <span className="text-xs text-muted-foreground">
                                prompt {suggestion.prompt_version} · requested by {suggestion.requested_by} {formatDateTime(suggestion.created_at)}
                            </span>
                        </div>
                        <dl className="grid gap-2 sm:grid-cols-[10rem_1fr]">
                            <dt className="text-muted-foreground">Likely cause</dt>
                            <dd className="font-medium">{suggestion.likely_cause_label}</dd>
                            <dt className="text-muted-foreground">Suggested next step</dt>
                            <dd className="font-medium">{suggestion.recommended_action_label}</dd>
                            <dt className="text-muted-foreground">Confidence</dt>
                            <dd>{suggestion.output.confidence === undefined ? '—' : `${Math.round(suggestion.output.confidence * 100)}%`}</dd>
                        </dl>
                        <p>{suggestion.output.explanation}</p>
                        {(suggestion.output.evidence?.length ?? 0) > 0 && (
                            <ul className="list-disc pl-5 text-muted-foreground">
                                {suggestion.output.evidence?.map((e) => (
                                    <li key={e}>{e}</li>
                                ))}
                            </ul>
                        )}
                        {suggestion.status === 'pending' ? (
                            <Decision suggestion={suggestion} actions={data.actions} />
                        ) : (
                            <p className="text-xs text-muted-foreground">
                                {suggestion.status_label} by {suggestion.decided_by} {formatDateTime(suggestion.decided_at)}
                                {suggestion.override_action_label && ` · chose: ${suggestion.override_action_label}`}
                                {suggestion.decision_reason && ` · “${suggestion.decision_reason}”`}
                            </p>
                        )}
                        <p className="text-xs text-muted-foreground">The assistant only sees pseudonymised records and cannot change anything. You decide and act.</p>
                    </div>
                )}
                {!suggestion && data.enabled && <p className="text-muted-foreground">No suggestion yet.</p>}
            </CardContent>
        </Card>
    );
}
