import { Head } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import AppLayout from '@/layouts/app-layout';

interface Stage {
    key: string;
    label: string;
    count: number;
}

interface Props {
    greetingName: string;
    pipeline: Stage[];
    approvals: unknown[];
    tasks: unknown[];
    alerts: unknown[];
}

function greeting(): string {
    const hour = Number(
        new Intl.DateTimeFormat('en-ZA', { hour: 'numeric', hour12: false, timeZone: 'Africa/Johannesburg' }).format(new Date()),
    );
    if (hour < 12) return 'Good morning';
    if (hour < 17) return 'Good afternoon';
    return 'Good evening';
}

/**
 * The development line: every active project sits at a station on the cycle.
 * This is the one bold element of the home page; everything else stays quiet.
 */
function DevelopmentLine({ stages }: { stages: Stage[] }) {
    const total = stages.reduce((sum, s) => sum + s.count, 0);

    return (
        <section aria-labelledby="line-heading" className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-5 lg:p-7">
            <div className="flex flex-wrap items-baseline justify-between gap-2">
                <h2 id="line-heading" className="text-lg font-bold">
                    Active projects across the cycle
                </h2>
                <p className="text-sm text-ink-soft">{total === 1 ? '1 active project' : `${total} active projects`}</p>
            </div>

            <ol className="relative mt-8 grid grid-cols-7 gap-1">
                <span aria-hidden className="absolute top-[18px] right-[7%] left-[7%] h-[3px] rounded bg-line/25" />
                {stages.map((stage) => {
                    const active = stage.count > 0;
                    return (
                        <li key={stage.key} className="relative flex flex-col items-center text-center">
                            <span
                                className={cn(
                                    'z-10 grid size-9 place-items-center rounded-full border-[3px] text-sm font-bold tabular-nums',
                                    active ? 'border-line bg-line text-white' : 'border-line/30 bg-surface text-ink-soft',
                                )}
                            >
                                {stage.count}
                            </span>
                            <span className={cn('mt-2 text-xs leading-tight sm:text-sm', active ? 'font-semibold text-ink' : 'text-ink-soft')}>
                                {stage.label}
                            </span>
                        </li>
                    );
                })}
            </ol>
        </section>
    );
}

function Panel({ title, empty, items }: { title: string; empty: string; items: unknown[] }) {
    return (
        <section className="border-t-2 border-ink pt-3">
            <h2 className="font-bold">{title}</h2>
            {items.length === 0 ? <p className="mt-2 text-sm text-ink-soft">{empty}</p> : null}
        </section>
    );
}

export default function MyDay({ greetingName, pipeline, approvals, tasks, alerts }: Props) {
    const today = new Intl.DateTimeFormat('en-ZA', {
        weekday: 'long',
        day: 'numeric',
        month: 'long',
        timeZone: 'Africa/Johannesburg',
    }).format(new Date());

    return (
        <>
            <Head title="My day" />
            <div className="mx-auto grid max-w-6xl gap-8">
                <header>
                    <p className="text-sm text-ink-soft">{today}</p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%] sm:text-4xl">
                        {greeting()}, {greetingName}
                    </h1>
                </header>

                <DevelopmentLine stages={pipeline} />

                <div className="grid gap-8 md:grid-cols-3">
                    <Panel title="Waiting for your approval" items={approvals} empty="Nothing needs your approval. Purchase orders, variations and payments will appear here." />
                    <Panel title="Your tasks" items={tasks} empty="No tasks assigned to you. Tasks from projects and meetings will appear here." />
                    <Panel title="Alerts" items={alerts} empty="No alerts. Expiring supplier documents, overdue approvals and budget warnings will appear here." />
                </div>
            </div>
        </>
    );
}

MyDay.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
