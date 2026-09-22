import { Head, Link, router, useForm, usePage } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Check, Lock } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { formatDate, formatDateTime, formatRand, selectClass, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

type Option = { key: string; label: string };

interface GateItem {
    id: number;
    title: string;
    required: boolean;
    completedAt: string | null;
    completedBy: string | null;
}

interface Props {
    project: {
        id: string;
        code: string;
        name: string;
        type: string | null;
        stage: string;
        status: string;
        province: string | null;
        town: string | null;
        region: string | null;
        value: string | null;
        plannedStart: string | null;
        plannedCompletion: string | null;
        description: string | null;
        manager: string | null;
    };
    stages: { key: string; label: string; items: GateItem[] }[];
    blockers: string[];
    transitions: { from: string; to: string; by: string | null; comment: string | null; at: string }[];
    milestones: { id: number; title: string; stage: string | null; plannedDate: string; forecastDate: string | null; completedOn: string | null; daysLate: number | null }[];
    tasks: { id: string; title: string; assignee: string | null; assigneeId: string | null; dueDate: string | null; status: string; priority: string; overdue: boolean }[];
    risks: { id: string; kind: string; title: string; likelihood: number; impact: number; score: number; rating: string; mitigation: string | null; owner: string | null; status: string; reviewDate: string | null }[];
    openRiskCount: number;
    people: Option[];
    can: { manage: boolean; approve: boolean };
}

const TABS = [
    { key: 'gate', label: 'Stage gate' },
    { key: 'programme', label: 'Programme' },
    { key: 'tasks', label: 'Tasks' },
    { key: 'risks', label: 'Risks & issues' },
] as const;
type Tab = (typeof TABS)[number]['key'];

const RATING_STYLE: Record<string, string> = {
    low: 'bg-line-wash text-line-deep',
    medium: 'bg-hivis-wash text-ink',
    high: 'bg-brick-wash text-brick',
    critical: 'bg-brick text-white',
};

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function ProjectShow(props: Props) {
    const { project, stages, can } = props;
    const [tab, setTab] = useState<Tab>('gate');
    const modules = new Set(usePage<SharedProps>().props.company?.modules.map((m) => m.key) ?? []);

    useEffect(() => {
        const hash = window.location.hash.replace('#', '') as Tab;
        if (TABS.some((t) => t.key === hash)) setTab(hash);
    }, []);

    const currentIndex = stages.findIndex((s) => s.key === project.stage);
    const facts = [
        project.type,
        [project.town, project.province].filter(Boolean).join(', '),
        project.value ? formatRand(project.value) : null,
        project.manager ? `PM: ${project.manager}` : 'No project manager',
    ].filter(Boolean);

    return (
        <>
            <Head title={project.name} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft">
                            <Link href="/projects" className="hover:underline">
                                Projects
                            </Link>{' '}
                            / {project.code}
                        </p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{project.name}</h1>
                        <p className="mt-1 text-ink-soft">{facts.join('  |  ')}</p>
                    </div>
                    {can.manage && (
                        <Button variant="secondary" asChild>
                            <Link href={`/projects/${project.id}/edit`}>Edit project</Link>
                        </Button>
                    )}
                </header>

                {/* Stage track: where this project sits in the development cycle. */}
                <ol className="grid grid-cols-7 overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-surface text-center text-xs sm:text-sm" aria-label="Development stage">
                    {stages.map((s, i) => (
                        <li
                            key={s.key}
                            aria-current={i === currentIndex ? 'step' : undefined}
                            className={cn(
                                'border-r border-concrete px-1 py-3 last:border-r-0',
                                i < currentIndex && 'bg-line-wash text-line-deep',
                                i === currentIndex && 'bg-line font-semibold text-white',
                                i > currentIndex && 'text-ink-soft',
                            )}
                        >
                            {i < currentIndex && <Check className="mx-auto mb-0.5 size-3.5" aria-hidden />}
                            {s.label}
                        </li>
                    ))}
                </ol>

                <nav className="flex gap-1 overflow-x-auto border-b border-concrete" aria-label="Project sections">
                    {TABS.map((t) => (
                        <button
                            key={t.key}
                            onClick={() => {
                                setTab(t.key);
                                window.history.replaceState(null, '', `#${t.key}`);
                            }}
                            className={cn('-mb-px border-b-2 px-3 py-2 text-sm font-medium', tab === t.key ? 'border-line text-ink' : 'border-transparent text-ink-soft hover:text-ink')}
                        >
                            {t.label}
                            {t.key === 'tasks' && ` (${props.tasks.filter((x) => x.status !== 'done').length})`}
                            {t.key === 'risks' && ` (${props.openRiskCount})`}
                        </button>
                    ))}
                    {modules.has('feasibility') && (
                        <Link href={`/projects/${project.id}/feasibility`} className="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-soft hover:text-ink">
                            Feasibility
                        </Link>
                    )}
                    {modules.has('approvals') && (
                        <Link href={`/approvals?project=${project.id}`} className="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-soft hover:text-ink">
                            Approvals
                        </Link>
                    )}
                    <Link href={`/projects/${project.id}/team`} className="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-soft hover:text-ink">
                        Team
                    </Link>
                    {modules.has('funding') && (
                        <Link href={`/projects/${project.id}/funding`} className="-mb-px border-b-2 border-transparent px-3 py-2 text-sm font-medium text-ink-soft hover:text-ink">
                            Funding
                        </Link>
                    )}
                </nav>

                {tab === 'gate' && <GateTab {...props} currentIndex={currentIndex} />}
                {tab === 'programme' && <ProgrammeTab {...props} />}
                {tab === 'tasks' && <TasksTab {...props} />}
                {tab === 'risks' && <RisksTab {...props} />}
            </div>
        </>
    );
}

function GateTab({ project, stages, blockers, transitions, can, currentIndex }: Props & { currentIndex: number }) {
    const current = stages[currentIndex];
    const next = stages[currentIndex + 1];
    const [comment, setComment] = useState('');
    const done = current?.items.filter((i) => i.completedAt).length ?? 0;

    function toggle(item: GateItem) {
        router.post(`/projects/${project.id}/gate-items/${item.id}`, { complete: !item.completedAt }, { preserveScroll: true });
    }

    function approve(e: FormEvent) {
        e.preventDefault();
        router.post(`/projects/${project.id}/advance`, { comment }, { preserveScroll: true, onSuccess: () => setComment('') });
    }

    return (
        <div className="grid gap-8 lg:grid-cols-[1fr_320px]">
            <section>
                <div className="flex items-baseline justify-between">
                    <h2 className="text-lg font-bold">{current?.label} checklist</h2>
                    <p className="text-sm text-ink-soft tabular-nums">
                        {done} of {current?.items.length} done
                    </p>
                </div>
                <ul className="mt-3 divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    {current?.items.map((item) => (
                        <li key={item.id} className="flex items-start gap-3 p-3">
                            <input
                                type="checkbox"
                                className="mt-1 size-5 accent-line"
                                checked={item.completedAt !== null}
                                disabled={!can.manage}
                                onChange={() => toggle(item)}
                                aria-label={item.title}
                            />
                            <div className="min-w-0 flex-1">
                                <p className={cn(item.completedAt && 'text-ink-soft line-through decoration-ink-soft/40')}>{item.title}</p>
                                <p className="text-xs text-ink-soft">
                                    {item.completedAt ? `Done by ${item.completedBy ?? 'someone'}, ${formatDateTime(item.completedAt)}` : item.required ? 'Required' : 'Where applicable'}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>

                {next && can.approve && (
                    <form onSubmit={approve} className="mt-5 grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <p className="font-semibold">Approve and move to {next.label}</p>
                        {blockers.length > 0 ? (
                            <p className="text-sm text-ink-soft">
                                {blockers.length === 1 ? '1 required item is' : `${blockers.length} required items are`} still open. Complete {blockers.length === 1 ? 'it' : 'them'} to approve this gate.
                            </p>
                        ) : (
                            <>
                                <textarea
                                    value={comment}
                                    onChange={(e) => setComment(e.target.value)}
                                    rows={2}
                                    placeholder="Approval comment (optional)"
                                    className="rounded-[var(--radius-control)] border border-concrete p-3 text-sm focus:border-line focus:outline-none"
                                />
                                <div>
                                    <Button type="submit">Approve {current?.label} gate</Button>
                                </div>
                            </>
                        )}
                    </form>
                )}
                {!next && <p className="mt-5 text-ink-soft">This project is at the final stage.</p>}
            </section>

            <aside className="grid content-start gap-6">
                <section>
                    <h2 className="font-bold">Approval history</h2>
                    {transitions.length === 0 ? (
                        <p className="mt-1 text-sm text-ink-soft">No stage gates approved yet.</p>
                    ) : (
                        <ul className="mt-2 grid gap-3">
                            {transitions.map((t) => (
                                <li key={t.at} className="border-l-2 border-line pl-3 text-sm">
                                    <p className="font-medium">
                                        {t.from} approved, moved to {t.to}
                                    </p>
                                    <p className="text-ink-soft">
                                        {t.by}, {formatDateTime(t.at)}
                                    </p>
                                    {t.comment && <p className="mt-0.5 italic">{t.comment}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
                {currentIndex > 0 && (
                    <section>
                        <h2 className="flex items-center gap-1.5 font-bold">
                            <Lock className="size-3.5" aria-hidden /> Earlier stages
                        </h2>
                        <ul className="mt-2 grid gap-1 text-sm">
                            {stages.slice(0, currentIndex).map((s) => (
                                <li key={s.key} className="flex justify-between">
                                    <span>{s.label}</span>
                                    <span className="text-ink-soft tabular-nums">
                                        {s.items.filter((i) => i.completedAt).length}/{s.items.length} items
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </aside>
        </div>
    );
}

function ProgrammeTab({ project, milestones, stages, can }: Props) {
    const form = useForm({ title: '', stage: project.stage, planned_date: '' });

    function add(e: FormEvent) {
        e.preventDefault();
        form.post(`/projects/${project.id}/milestones`, { preserveScroll: true, onSuccess: () => form.reset('title', 'planned_date') });
    }

    return (
        <section className="grid gap-5">
            <div className="flex flex-wrap gap-6 text-sm">
                <p>
                    <span className="text-ink-soft">Planned start </span>
                    {formatDate(project.plannedStart) || 'not set'}
                </p>
                <p>
                    <span className="text-ink-soft">Planned completion </span>
                    {formatDate(project.plannedCompletion) || 'not set'}
                </p>
            </div>

            {milestones.length === 0 ? (
                <p className="text-ink-soft">No milestones yet. Add the key dates for this project, such as land transfer, plan approval or practical completion.</p>
            ) : (
                <ol className="relative grid gap-0 border-l-2 border-concrete pl-5">
                    {milestones.map((m) => (
                        <li key={m.id} className="relative pb-5">
                            <span
                                aria-hidden
                                className={cn('absolute top-1.5 -left-[27px] size-3 rounded-full border-2', m.completedOn ? 'border-line bg-line' : m.daysLate ? 'border-brick bg-surface' : 'border-ink-soft bg-surface')}
                            />
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p className="font-medium">{m.title}</p>
                                <div className="flex items-center gap-3 text-sm">
                                    <span className="tabular-nums">{formatDate(m.plannedDate)}</span>
                                    {m.completedOn ? (
                                        <span className="text-line-deep">Done {formatDate(m.completedOn)}</span>
                                    ) : (
                                        m.daysLate && <span className="font-semibold text-brick">{m.daysLate} days late</span>
                                    )}
                                    {can.manage && !m.completedOn && (
                                        <button className="text-line hover:underline" onClick={() => router.patch(`/milestones/${m.id}`, { completed_on: today() }, { preserveScroll: true })}>
                                            Mark done
                                        </button>
                                    )}
                                </div>
                            </div>
                            {m.stage && <p className="text-sm text-ink-soft">{m.stage} stage</p>}
                        </li>
                    ))}
                </ol>
            )}

            {can.manage && (
                <form onSubmit={add} className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-[1fr_160px_170px_auto]">
                    <Field label="Milestone" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} />
                    <SelectField label="Stage" name="stage" value={form.data.stage} onChange={(v) => form.setData('stage', v)} options={stages.map((s) => ({ key: s.key, label: s.label }))} />
                    <Field label="Planned date" name="planned_date" type="date" value={form.data.planned_date} onChange={(e) => form.setData('planned_date', e.target.value)} error={form.errors.planned_date} />
                    <Button type="submit" disabled={form.processing}>
                        Add
                    </Button>
                </form>
            )}
        </section>
    );
}

function TasksTab({ project, tasks, people, can }: Props) {
    const form = useForm({ title: '', assignee: '', due_date: '', priority: 'normal' });

    function add(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, assignee: d.assignee || null, due_date: d.due_date || null }));
        form.post(`/projects/${project.id}/tasks`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <section className="grid gap-5">
            {can.manage && (
                <form onSubmit={add} className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-[1fr_200px_160px_auto]">
                    <Field label="Task" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} />
                    <SelectField label="Assign to" name="assignee" value={form.data.assignee} onChange={(v) => form.setData('assignee', v)} options={people} placeholder="Nobody yet" error={form.errors.assignee} />
                    <Field label="Due" name="due_date" type="date" value={form.data.due_date} onChange={(e) => form.setData('due_date', e.target.value)} />
                    <Button type="submit" disabled={form.processing}>
                        Add task
                    </Button>
                </form>
            )}

            {tasks.length === 0 ? (
                <p className="text-ink-soft">No tasks on this project yet.</p>
            ) : (
                <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    {tasks.map((t) => (
                        <li key={t.id} className={cn('flex flex-wrap items-center gap-3 p-3', t.status === 'done' && 'opacity-60')}>
                            <input
                                type="checkbox"
                                className="size-5 accent-line"
                                checked={t.status === 'done'}
                                onChange={() => router.patch(`/tasks/${t.id}`, { status: t.status === 'done' ? 'open' : 'done' }, { preserveScroll: true })}
                                aria-label={`Mark ${t.title} as done`}
                            />
                            <div className="min-w-0 flex-1">
                                <p className={cn('font-medium', t.status === 'done' && 'line-through')}>
                                    {t.priority === 'high' && <span className="mr-1.5 rounded bg-brick-wash px-1.5 text-xs font-semibold text-brick">High</span>}
                                    {t.title}
                                </p>
                                <p className="text-sm text-ink-soft">
                                    {t.assignee ?? 'Unassigned'}
                                    {t.dueDate && <span className={cn(t.overdue && 'font-semibold text-brick')}>, due {formatDate(t.dueDate)}</span>}
                                </p>
                            </div>
                            {can.manage && (
                                <select
                                    aria-label={`Status of ${t.title}`}
                                    className={selectClass + ' h-9 w-36 text-sm'}
                                    value={t.status}
                                    onChange={(e) => router.patch(`/tasks/${t.id}`, { status: e.target.value }, { preserveScroll: true })}
                                >
                                    <option value="open">To do</option>
                                    <option value="in_progress">In progress</option>
                                    <option value="done">Done</option>
                                </select>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </section>
    );
}

function RisksTab({ project, risks, people, can }: Props) {
    const form = useForm({ kind: 'risk', title: '', likelihood: '3', impact: '3', mitigation: '', owner: '' });
    const scale = [1, 2, 3, 4, 5].map((n) => ({ key: String(n), label: String(n) }));

    function add(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, likelihood: Number(d.likelihood), impact: Number(d.impact), owner: d.owner || null }));
        form.post(`/projects/${project.id}/risks`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <section className="grid gap-5">
            {risks.length === 0 ? (
                <p className="text-ink-soft">The register is empty. Record risks early: land, approvals, funding, contractor and market risks.</p>
            ) : (
                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className="w-full text-left text-sm [&_td]:px-3 [&_td]:py-3 [&_th]:px-3 [&_th]:py-2.5 [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                        <thead>
                            <tr>
                                <th>Risk or issue</th>
                                <th>Rating</th>
                                <th>Owner</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody>
                            {risks.map((r) => (
                                <tr key={r.id} className={cn(r.status === 'closed' && 'opacity-60')}>
                                    <td>
                                        <p className="font-medium">
                                            <span className="mr-1.5 text-xs font-semibold text-ink-soft capitalize">{r.kind}:</span>
                                            {r.title}
                                        </p>
                                        {r.mitigation && <p className="text-ink-soft">Mitigation: {r.mitigation}</p>}
                                    </td>
                                    <td>
                                        <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold capitalize', RATING_STYLE[r.rating])}>
                                            {r.rating} ({r.likelihood}×{r.impact})
                                        </span>
                                    </td>
                                    <td>{r.owner ?? <span className="text-ink-soft">None</span>}</td>
                                    <td>
                                        {can.manage ? (
                                            <select
                                                aria-label={`Status of ${r.title}`}
                                                className={selectClass + ' h-9 w-32 text-sm'}
                                                value={r.status}
                                                onChange={(e) => router.patch(`/risks/${r.id}`, { status: e.target.value }, { preserveScroll: true })}
                                            >
                                                <option value="open">Open</option>
                                                <option value="monitoring">Monitoring</option>
                                                <option value="closed">Closed</option>
                                            </select>
                                        ) : (
                                            <span className="capitalize">{r.status}</span>
                                        )}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {can.manage && (
                <form onSubmit={add} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                    <p className="font-semibold">Add to the register</p>
                    <div className="grid gap-4 sm:grid-cols-[140px_1fr]">
                        <SelectField label="Type" name="kind" value={form.data.kind} onChange={(v) => form.setData('kind', v)} options={[{ key: 'risk', label: 'Risk' }, { key: 'issue', label: 'Issue' }]} />
                        <Field label="Title" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} />
                    </div>
                    <div className="grid gap-4 sm:grid-cols-3">
                        <SelectField label="Likelihood (1 to 5)" name="likelihood" value={form.data.likelihood} onChange={(v) => form.setData('likelihood', v)} options={scale} />
                        <SelectField label="Impact (1 to 5)" name="impact" value={form.data.impact} onChange={(v) => form.setData('impact', v)} options={scale} />
                        <SelectField label="Owner" name="owner" value={form.data.owner} onChange={(v) => form.setData('owner', v)} options={people} placeholder="Nobody yet" error={form.errors.owner} />
                    </div>
                    <Field label="Mitigation or action" name="mitigation" value={form.data.mitigation} onChange={(e) => form.setData('mitigation', e.target.value)} />
                    <div>
                        <Button type="submit" disabled={form.processing}>
                            Add
                        </Button>
                    </div>
                </form>
            )}
        </section>
    );
}

ProjectShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
