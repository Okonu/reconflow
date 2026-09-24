import { router } from '@inertiajs/react';
import { useState } from 'react';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import type { AdjustmentRow } from '../types';

export function DecisionButtons({ adjustment }: { adjustment: AdjustmentRow }) {
    const [comment, setComment] = useState('');
    const [processing, setProcessing] = useState(false);
    const decide = (action: 'approve' | 'reject' | 'retry') =>
        router.post(route(`adjustments.${action}`, adjustment.id), action === 'retry' ? {} : { comment }, {
            preserveScroll: true,
            onStart: () => setProcessing(true),
            onFinish: () => setProcessing(false),
        });

    if (adjustment.state === 'posting_failed') {
        return adjustment.can.retry ? (
            <Button size="sm" onClick={() => decide('retry')} disabled={processing}>
                Retry posting
            </Button>
        ) : null;
    }
    if (adjustment.state !== 'pending_approval') {
        return null;
    }
    if (!adjustment.can.approve && !adjustment.can.reject) {
        return adjustment.why_not ? <p className="text-xs text-muted-foreground">{adjustment.why_not}</p> : null;
    }

    return (
        <div className="flex flex-wrap items-center gap-2">
            <Input className="h-9 min-w-48 flex-1" placeholder="Comment (required to reject)" value={comment} onChange={(e) => setComment(e.target.value)} />
            {adjustment.can.approve && (
                <Button size="sm" onClick={() => decide('approve')} disabled={processing}>
                    Approve and post
                </Button>
            )}
            {adjustment.can.reject && (
                <Button size="sm" variant="outline" onClick={() => decide('reject')} disabled={processing || comment.trim() === ''}>
                    Reject
                </Button>
            )}
        </div>
    );
}
