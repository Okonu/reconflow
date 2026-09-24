import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { RunHistory } from '../../components/RunHistory';
import { RunNowCard } from '../../components/RunNowCard';
import type { ReconRun, Readiness } from '../../types';

interface Props {
    runs: { data: ReconRun[] };
    readiness: Readiness;
    can: { trigger: boolean };
}

export default function RunsIndex({ runs, readiness, can }: Props) {
    return (
        <AppLayout>
            <Head title="Reconciliation runs" />
            <div className="space-y-6">
                <div>
                    <h1 className="text-2xl font-semibold">Reconciliation runs</h1>
                    <p className="text-sm text-muted-foreground">Every run is kept. Re-running a date creates a new version; the previous one stays for audit.</p>
                </div>
                {can.trigger && <RunNowCard readiness={readiness} />}
                <Card>
                    <CardHeader>
                        <CardTitle>History</CardTitle>
                    </CardHeader>
                    <CardContent>
                        <RunHistory runs={runs.data} />
                    </CardContent>
                </Card>
            </div>
        </AppLayout>
    );
}
