import { Head } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { formatDateTime, PageHeader, Pager, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

interface Entry {
    id: number;
    description: string;
    log: string | null;
    subject: string | null;
    by: string;
    at: string | null;
}

export default function Activity({ activities }: { activities: Paginated<Entry> }) {
    return (
        <>
            <Head title="Audit log" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader title="Audit log" description="Every change made in the system, who made it and when. Entries cannot be edited or deleted." />
                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className={tableClass}>
                        <thead>
                            <tr>
                                <th>When</th>
                                <th>Who</th>
                                <th>What happened</th>
                                <th>Record</th>
                            </tr>
                        </thead>
                        <tbody>
                            {activities.data.map((a) => (
                                <tr key={a.id}>
                                    <td className="whitespace-nowrap text-ink-soft">{formatDateTime(a.at)}</td>
                                    <td>{a.by}</td>
                                    <td className="first-letter:uppercase">{a.description}</td>
                                    <td className="text-ink-soft">{a.subject ?? ''}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                    {activities.data.length === 0 && <p className="p-5 text-ink-soft">Nothing has been recorded yet.</p>}
                </div>
                <Pager prev={activities.prev_page_url} next={activities.next_page_url} page={activities.current_page} last={activities.last_page} />
            </div>
        </>
    );
}

Activity.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
