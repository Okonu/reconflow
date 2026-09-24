import { formatDateTime } from '@/lib/format';
import type { TimelineEvent } from '../types';

function describe(event: TimelineEvent): string {
    if (event.type === 'comment') {
        return 'commented';
    }
    if (event.from && event.to) {
        return `moved ${event.from.replaceAll('_', ' ')} → ${event.to.replaceAll('_', ' ')}`;
    }
    return event.type.replaceAll('_', ' ');
}

export function Timeline({ events }: { events: TimelineEvent[] }) {
    if (events.length === 0) {
        return <p className="text-sm text-muted-foreground">No activity yet.</p>;
    }

    return (
        <ol className="space-y-3">
            {events.map((event) => (
                <li key={event.id} className="border-l-2 pl-3 text-sm">
                    <p>
                        <span className="font-medium">{event.actor}</span> <span className="text-muted-foreground">{describe(event)}</span>
                    </p>
                    {event.comment && <p className="mt-1 whitespace-pre-line">{event.comment}</p>}
                    <p className="text-xs text-muted-foreground">{formatDateTime(event.at)}</p>
                </li>
            ))}
        </ol>
    );
}
