import { Badge } from '@/components/ui/badge';
import type { ReconRun } from '../types';

const LABELS: Record<ReconRun['status'], { label: string; variant: 'default' | 'secondary' | 'outline' | 'destructive' }> = {
    queued: { label: 'Queued', variant: 'outline' },
    running: { label: 'Running', variant: 'outline' },
    completed: { label: 'Completed', variant: 'secondary' },
    failed: { label: 'Failed', variant: 'destructive' },
    blocked_data: { label: 'Blocked: data missing', variant: 'destructive' },
};

export function RunStatusBadge({ run }: { run: Pick<ReconRun, 'status' | 'provisional' | 'stale' | 'superseded'> }) {
    const config = LABELS[run.status];

    return (
        <span className="inline-flex flex-wrap gap-1">
            <Badge variant={config.variant}>{config.label}</Badge>
            {run.provisional && <Badge variant="outline">Provisional</Badge>}
            {run.stale && <Badge variant="outline">Stale: re-run needed</Badge>}
            {run.superseded && <Badge variant="outline">Superseded</Badge>}
        </span>
    );
}
