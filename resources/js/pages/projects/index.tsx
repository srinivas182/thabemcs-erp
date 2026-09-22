import { Head, Link, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatRand, PageHeader, Pager, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

interface Row {
    id: string;
    code: string;
    name: string;
    type: string | null;
    stage: { key: string; label: string };
    location: string;
    value: string | null;
    manager: string | null;
    openTasks: number;
}

interface Props {
    projects: Paginated<Row>;
    filters: { stage: string | null; status: string; q: string };
    stages: { key: string; label: string; count: number }[];
    statuses: string[];
    canCreate: boolean;
}

const STATUS_LABELS: Record<string, string> = { active: 'Active', on_hold: 'On hold', completed: 'Completed', cancelled: 'Cancelled' };

export default function ProjectsIndex({ projects, filters, stages, statuses, canCreate }: Props) {
    const [q, setQ] = useState(filters.q);

    function apply(next: Partial<Props['filters']>) {
        router.get('/projects', { ...filters, ...next }, { preserveState: true, preserveScroll: true, replace: true });
    }

    function search(e: FormEvent) {
        e.preventDefault();
        apply({ q });
    }

    return (
        <>
            <Head title="Projects" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title="Projects"
                    action={
                        canCreate && (
                            <Button asChild>
                                <Link href="/projects/create">New project</Link>
                            </Button>
                        )
                    }
                />

                <div className="flex flex-wrap gap-1.5" role="group" aria-label="Filter by stage">
                    <button
                        onClick={() => apply({ stage: null })}
                        className={cn('rounded-full border px-3 py-1 text-sm', !filters.stage ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}
                    >
                        All stages
                    </button>
                    {stages.map((s) => (
                        <button
                            key={s.key}
                            onClick={() => apply({ stage: s.key })}
                            className={cn('rounded-full border px-3 py-1 text-sm', filters.stage === s.key ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}
                        >
                            {s.label} <span className="tabular-nums opacity-70">{s.count}</span>
                        </button>
                    ))}
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <form onSubmit={search} className="flex-1">
                        <input
                            value={q}
                            onChange={(e) => setQ(e.target.value)}
                            placeholder="Search by name, code or town"
                            className="h-10 w-full max-w-md rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm focus:border-line focus:outline-none"
                        />
                    </form>
                    <select
                        aria-label="Status"
                        value={filters.status}
                        onChange={(e) => apply({ status: e.target.value })}
                        className="h-10 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm"
                    >
                        {statuses.map((s) => (
                            <option key={s} value={s}>
                                {STATUS_LABELS[s] ?? s}
                            </option>
                        ))}
                    </select>
                </div>

                {projects.data.length === 0 ? (
                    <div className="rounded-[var(--radius-panel)] border border-dashed border-concrete p-10 text-center">
                        <p className="font-semibold">No projects match</p>
                        <p className="mt-1 text-ink-soft">{canCreate ? 'Create a project to start its Plan checklist.' : 'Try a different stage or search.'}</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead>
                                <tr>
                                    <th>Project</th>
                                    <th>Stage</th>
                                    <th>Location</th>
                                    <th className="text-right">Estimated value</th>
                                    <th>Manager</th>
                                    <th className="text-right">Open tasks</th>
                                </tr>
                            </thead>
                            <tbody>
                                {projects.data.map((p) => (
                                    <tr key={p.id} className="hover:bg-plaster">
                                        <td>
                                            <Link href={`/projects/${p.id}`} className="font-semibold hover:underline">
                                                {p.name}
                                            </Link>
                                            <p className="text-ink-soft">
                                                {p.code}
                                                {p.type && `, ${p.type}`}
                                            </p>
                                        </td>
                                        <td>{p.stage.label}</td>
                                        <td className="text-ink-soft">{p.location}</td>
                                        <td className="text-right tabular-nums">{formatRand(p.value)}</td>
                                        <td>{p.manager ?? <span className="text-ink-soft">Not assigned</span>}</td>
                                        <td className="text-right tabular-nums">{p.openTasks}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                <Pager prev={projects.prev_page_url} next={projects.next_page_url} page={projects.current_page} last={projects.last_page} />
            </div>
        </>
    );
}

ProjectsIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
