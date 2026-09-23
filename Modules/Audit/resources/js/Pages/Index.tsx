import { Head } from '@inertiajs/react';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import AppLayout from '@/layouts/app-layout';
import { formatDateTime } from '@/lib/format';

interface AuditRow {
    id: number;
    occurred_at: string;
    actor: string;
    action: string;
    entity_type: string | null;
    entity_id: string | null;
}

export default function AuditIndex({ events }: { events: { data: AuditRow[] } }) {
    return (
        <AppLayout>
            <Head title="Audit log" />
            <Card>
                <CardHeader>
                    <CardTitle>Audit log</CardTitle>
                </CardHeader>
                <CardContent className="overflow-x-auto">
                    <table className="w-full text-sm">
                        <thead className="text-left text-muted-foreground">
                            <tr>
                                <th className="py-2">When</th>
                                <th>Actor</th>
                                <th>Action</th>
                                <th>Entity</th>
                            </tr>
                        </thead>
                        <tbody>
                            {events.data.map((event) => (
                                <tr key={event.id} className="border-t">
                                    <td className="py-2">{formatDateTime(event.occurred_at)}</td>
                                    <td>{event.actor}</td>
                                    <td className="font-mono text-xs">{event.action}</td>
                                    <td>{event.entity_type ? `${event.entity_type} ${event.entity_id ?? ''}` : '—'}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </CardContent>
            </Card>
        </AppLayout>
    );
}
