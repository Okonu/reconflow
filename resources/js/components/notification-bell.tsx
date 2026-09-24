import { Link, usePage } from '@inertiajs/react';
import { Bell } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { Button } from '@/components/ui/button';
import { formatDateTime } from '@/lib/format';
import { http } from '@/lib/http';
import { cn } from '@/lib/utils';
import type { SharedProps } from '@/types';

interface Item {
    id: string;
    kind: string;
    title: string;
    body: string;
    lines: string[];
    url: string | null;
    level: 'info' | 'warning' | 'critical';
    read: boolean;
    created_at: string | null;
}

export function NotificationBell() {
    const sharedUnread = usePage<SharedProps>().props.notifications?.unread ?? 0;
    const [local, setLocal] = useState<{ from: number; value: number } | null>(null);
    const unread = local !== null && local.from === sharedUnread ? local.value : sharedUnread;
    const setUnread = (value: number) => setLocal({ from: sharedUnread, value });
    const [items, setItems] = useState<Item[] | null>(null);
    const [open, setOpen] = useState(false);
    const panel = useRef<HTMLDivElement>(null);

    useEffect(() => {
        if (!open) {
            return;
        }
        http<{ unread: number; items: Item[] }>('GET', route('notifications.index')).then((response) => setItems(response.items));
        const close = (event: MouseEvent) => {
            if (panel.current && !panel.current.contains(event.target as Node)) {
                setOpen(false);
            }
        };
        document.addEventListener('mousedown', close);
        return () => document.removeEventListener('mousedown', close);
    }, [open]);

    const markRead = async (item: Item) => {
        if (!item.read) {
            const response = await http<{ unread: number }>('POST', route('notifications.read', item.id));
            setUnread(response.unread);
            setItems((current) => current?.map((i) => (i.id === item.id ? { ...i, read: true } : i)) ?? null);
        }
    };

    const markAll = async () => {
        await http('POST', route('notifications.read-all'));
        setUnread(0);
        setItems((current) => current?.map((i) => ({ ...i, read: true })) ?? null);
    };

    return (
        <div className="relative" ref={panel}>
            <Button variant="ghost" size="icon" aria-label={`Notifications, ${unread} unread`} onClick={() => setOpen((o) => !o)}>
                <Bell className="size-5" />
                {unread > 0 && <span className="absolute right-1.5 top-1.5 min-w-4 rounded-full bg-destructive px-1 text-[10px] leading-4 text-destructive-foreground">{unread > 99 ? '99+' : unread}</span>}
            </Button>
            {open && (
                <div className="absolute right-0 z-50 mt-2 w-96 max-w-[calc(100vw-2rem)] rounded-md border bg-popover text-popover-foreground shadow-lg">
                    <div className="flex items-center justify-between border-b px-3 py-2">
                        <p className="text-sm font-medium">Notifications</p>
                        {unread > 0 && (
                            <button type="button" className="text-xs text-primary hover:underline" onClick={markAll}>
                                Mark all read
                            </button>
                        )}
                    </div>
                    <ul className="max-h-96 overflow-y-auto">
                        {items === null && <li className="p-3 text-sm text-muted-foreground">Loading…</li>}
                        {items?.length === 0 && <li className="p-3 text-sm text-muted-foreground">No notifications yet.</li>}
                        {items?.map((item) => (
                            <li key={item.id} className={cn('border-b px-3 py-2 text-sm last:border-0', !item.read && 'bg-accent/40')}>
                                <Link href={item.url ?? '#'} onClick={() => markRead(item)} className="block">
                                    <p className={cn('font-medium', item.level === 'critical' && 'text-destructive')}>{item.title}</p>
                                    <p className="text-muted-foreground">{item.body}</p>
                                    {item.lines.length > 0 && (
                                        <ul className="mt-1 list-disc pl-4 text-xs text-muted-foreground">
                                            {item.lines.map((line) => (
                                                <li key={line}>{line}</li>
                                            ))}
                                        </ul>
                                    )}
                                    <p className="mt-1 text-xs text-muted-foreground">{formatDateTime(item.created_at)}</p>
                                </Link>
                            </li>
                        ))}
                    </ul>
                </div>
            )}
        </div>
    );
}
