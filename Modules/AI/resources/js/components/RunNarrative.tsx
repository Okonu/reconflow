import { useEffect, useState } from 'react';
import { Button } from '@/components/ui/button';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { formatDateTime } from '@/lib/format';
import { http, HttpError } from '@/lib/http';
import type { AiStatus, AiSuggestion } from '../types';
import { AiLabel } from './AiLabel';

interface Response {
    summary: AiSuggestion | null;
    status: AiStatus;
    can_request: boolean;
}

export function RunNarrative({ runId }: { runId: number }) {
    const [state, setState] = useState<Response | null>(null);
    const [error, setError] = useState<string | null>(null);
    const [loading, setLoading] = useState(false);

    useEffect(() => {
        http<Response>('GET', route('ai.summary.show', runId))
            .then(setState)
            .catch(() => setState(null));
    }, [runId]);

    if (state === null || (!state.summary && !state.can_request)) {
        return null;
    }

    const generate = async () => {
        setLoading(true);
        setError(null);
        try {
            const response = await http<{ summary: AiSuggestion }>('POST', route('ai.summary.store', runId));
            setState({ ...state, summary: response.summary });
        } catch (e) {
            setError(e instanceof HttpError ? e.message : 'Could not generate the summary.');
        } finally {
            setLoading(false);
        }
    };

    const summary = state.summary;

    return (
        <Card className="border-violet-200">
            <CardHeader className="flex flex-row items-center justify-between gap-2 space-y-0">
                <CardTitle className="text-base">Narrative summary</CardTitle>
                {state.can_request && state.status.enabled && (
                    <Button size="sm" variant="outline" onClick={generate} disabled={loading}>
                        {loading ? 'Writing…' : summary ? 'Regenerate' : 'Generate summary'}
                    </Button>
                )}
            </CardHeader>
            <CardContent className="space-y-3 text-sm">
                {!state.status.enabled && !summary && <p className="text-muted-foreground">{state.status.reason}</p>}
                {error && <p className="text-destructive">{error}</p>}
                {summary && (
                    <>
                        <div className="flex flex-wrap items-center gap-2">
                            <AiLabel model={summary.model} />
                            <span className="text-xs text-muted-foreground">
                                {summary.requested_by} · {formatDateTime(summary.created_at)} · built from aggregates only
                            </span>
                        </div>
                        <p className="font-medium">{summary.output.headline}</p>
                        {summary.output.paragraphs?.map((p) => (
                            <p key={p}>{p}</p>
                        ))}
                        {(summary.output.watch_items?.length ?? 0) > 0 && (
                            <ul className="list-disc pl-5">
                                {summary.output.watch_items?.map((w) => (
                                    <li key={w}>{w}</li>
                                ))}
                            </ul>
                        )}
                    </>
                )}
            </CardContent>
        </Card>
    );
}
