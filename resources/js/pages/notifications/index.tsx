import { Head, Link, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatDateTime, PageHeader, Pager } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

interface Item {
    id: string;
    data: { title: string; body: string; url: string | null; level: 'info' | 'warning' | 'danger' };
    read: boolean;
    createdAt: string | null;
}

export default function Notifications({ notifications }: { notifications: Paginated<Item> }) {
    const unread = notifications.data.some((n) => !n.read);

    return (
        <>
            <Head title="Notifications" />
            <div className="mx-auto grid max-w-3xl gap-6">
                <PageHeader
                    title="Notifications"
                    action={
                        unread && (
                            <Button variant="secondary" onClick={() => router.post('/notifications/read-all', {}, { preserveScroll: true })}>
                                Mark all as read
                            </Button>
                        )
                    }
                />

                {notifications.data.length === 0 ? (
                    <p className="text-ink-soft">You have no notifications. Approvals, expiring documents and project alerts will appear here.</p>
                ) : (
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {notifications.data.map((n) => (
                            <li key={n.id} className={cn('flex gap-3 p-4', !n.read && 'bg-line-wash/40')}>
                                <span
                                    aria-hidden
                                    className={cn('mt-1.5 size-2 shrink-0 rounded-full', n.read ? 'bg-transparent' : n.data.level === 'danger' ? 'bg-brick' : n.data.level === 'warning' ? 'bg-hivis' : 'bg-line')}
                                />
                                <div className="min-w-0 flex-1">
                                    <p className={cn(!n.read && 'font-semibold')}>{n.data.title}</p>
                                    <p className="text-sm text-ink-soft">{n.data.body}</p>
                                    <p className="mt-1 text-xs text-ink-soft">{formatDateTime(n.createdAt)}</p>
                                </div>
                                <div className="flex shrink-0 flex-col items-end gap-1">
                                    {n.data.url && (
                                        <Link href={n.data.url} className="text-sm font-medium text-line hover:underline" onClick={() => !n.read && router.post(`/notifications/${n.id}/read`)}>
                                            Open
                                        </Link>
                                    )}
                                    {!n.read && (
                                        <button className="text-sm text-ink-soft hover:text-ink" onClick={() => router.post(`/notifications/${n.id}/read`, {}, { preserveScroll: true })}>
                                            Mark read
                                        </button>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}

                <Pager prev={notifications.prev_page_url} next={notifications.next_page_url} page={notifications.current_page} last={notifications.last_page} />
            </div>
        </>
    );
}

Notifications.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
