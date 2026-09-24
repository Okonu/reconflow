import { Head, Link } from '@inertiajs/react';
import { Alert, AlertDescription } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { contributionPanels } from '@/lib/contributions';
import { formatDateTime, formatMoney } from '@/lib/format';
import { PossibleMatches } from '../../components/PossibleMatches';
import { SeverityBadge } from '../../components/SeverityBadge';
import { SourceRecords } from '../../components/SourceRecords';
import { ExceptionStateBadge } from '../../components/StateBadge';
import { Timeline } from '../../components/Timeline';
import { WorkActions } from '../../components/WorkActions';
import type { ReconExceptionRow, ResultDetail, SourceRecordSet, TimelineEvent } from '../../types';

interface Props {
    exception: { data: ReconExceptionRow };
    result: ResultDetail | null;
    records: SourceRecordSet;
    timeline: TimelineEvent[];
    contributions: Record<string, unknown>;
    can: { work: boolean; review: boolean; resolve: boolean; find_matches: boolean };
}

function Amount({ label, value, emphasis }: { label: string; value: string | null; emphasis?: boolean }) {
    return (
        <div>
            <p className="text-xs uppercase tracking-wide text-muted-foreground">{label}</p>
            <p className={emphasis ? 'text-lg font-semibold tabular-nums text-destructive' : 'text-lg font-semibold tabular-nums'}>{formatMoney(value)}</p>
        </div>
    );
}

export default function ExceptionShow({ exception: { data: exception }, result, records, timeline, contributions, can }: Props) {
    const panels = contributionPanels(contributions);
    const title = exception.transaction_id ?? exception.payment_ids.join(', ');

    return (
        <AppLayout>
            <Head title={`Exception ${title}`} />
            <div className="space-y-6">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <Link href={route('exceptions.index')} className="text-sm text-muted-foreground hover:underline">
                            ← Exception queue
                        </Link>
                        <h1 className="mt-1 flex flex-wrap items-center gap-3 text-2xl font-semibold">
                            {title}
                            <SeverityBadge severity={exception.severity} />
                            <ExceptionStateBadge state={exception.state} label={exception.state_label} />
                        </h1>
                        <p className="text-sm text-muted-foreground">
                            {exception.status_label} · {exception.category} · business date {exception.business_date}
                            {exception.carried_from && ` · carried from ${exception.carried_from}`}
                        </p>
                    </div>
                    <div className="text-right text-sm">
                        <p>Owner: {exception.owner?.name ?? 'Unassigned'}</p>
                        <p className={exception.overdue ? 'font-medium text-destructive' : 'text-muted-foreground'}>Due {formatDateTime(exception.due_at)}</p>
                    </div>
                </div>

                {exception.needs_review && (
                    <Alert variant="destructive">
                        <AlertDescription>A re-run changed the underlying result while an adjustment was in flight. Check the adjustment still makes sense before it is approved.</AlertDescription>
                    </Alert>
                )}
                {exception.resolution && (
                    <Alert>
                        <AlertDescription>{exception.resolution}</AlertDescription>
                    </Alert>
                )}

                {result && (
                    <Card>
                        <CardHeader>
                            <CardTitle className="text-base">
                                Why this is an exception <span className="font-mono text-xs text-muted-foreground">({result.rule_id}, run v{result.run_version})</span>
                            </CardTitle>
                        </CardHeader>
                        <CardContent className="space-y-4">
                            <p className="text-sm">{result.explanation}</p>
                            <div className="grid grid-cols-2 gap-4 sm:grid-cols-4">
                                <Amount label="Expected" value={result.expected_amount} />
                                <Amount label="Received" value={result.actual_amount} />
                                <Amount label="Posted" value={result.posted_amount} />
                                <Amount label="Variance" value={result.variance} emphasis={result.variance !== null && Number(result.variance) !== 0} />
                            </div>
                        </CardContent>
                    </Card>
                )}

                <SourceRecords records={records} />

                <div className="grid gap-6 lg:grid-cols-[2fr_1fr]">
                    <div className="space-y-6">
                        {panels.map(({ key, Component, data }) => (
                            <Component key={key} data={data} exceptionId={exception.id} />
                        ))}
                        {can.find_matches && result && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Possible late payments</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <PossibleMatches resultId={result.id} />
                                </CardContent>
                            </Card>
                        )}
                    </div>
                    <div className="space-y-6">
                        {(can.work || can.review || can.resolve) && (
                            <Card>
                                <CardHeader>
                                    <CardTitle className="text-base">Work this exception</CardTitle>
                                </CardHeader>
                                <CardContent>
                                    <WorkActions exceptionId={exception.id} can={can} />
                                </CardContent>
                            </Card>
                        )}
                        <Card>
                            <CardHeader>
                                <CardTitle className="text-base">Timeline</CardTitle>
                            </CardHeader>
                            <CardContent>
                                <Timeline events={timeline} />
                            </CardContent>
                        </Card>
                    </div>
                </div>
            </div>
        </AppLayout>
    );
}
