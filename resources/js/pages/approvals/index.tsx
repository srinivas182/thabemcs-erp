import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, PageHeader, selectClass, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Application {
    id: string;
    type: string;
    typeLabel: string;
    description: string | null;
    authority: string | null;
    reference: string | null;
    status: string;
    project: { id: string; name: string; code: string };
    submittedOn: string | null;
    expectedDecisionOn: string | null;
    decisionOn: string | null;
    validUntil: string | null;
    daysToExpiry: number | null;
    decisionOverdue: boolean;
    conditions: string | null;
    responsible: string | null;
}

interface Props {
    applications: Application[];
    filters: { project: string | null; view: string };
    projectName: string | null;
    projects: Option[];
    types: (Option & { authority: string })[];
    statuses: Option[];
    people: Option[];
    canManage: boolean;
}

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function Approvals({ applications, filters, projectName, projects, types, statuses, people, canManage }: Props) {
    const [adding, setAdding] = useState(false);

    return (
        <>
            <Head title="Statutory approvals" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title={projectName ? `Approvals: ${projectName}` : 'Statutory approvals'}
                    description="Town planning, building plans, environmental, NHBRC and other applications, from submission to decision and expiry."
                    action={canManage && <Button onClick={() => setAdding(!adding)}>Add application</Button>}
                />

                <div className="flex flex-wrap items-center gap-3">
                    <select aria-label="Project" className={selectClass + ' h-10 w-64 text-sm'} value={filters.project ?? ''} onChange={(e) => router.get('/approvals', { ...filters, project: e.target.value || undefined })}>
                        <option value="">All projects</option>
                        {projects.map((p) => (<option key={p.key} value={p.key}>{p.label}</option>))}
                    </select>
                    <label className="flex items-center gap-2 text-sm">
                        <input type="checkbox" className="size-4 accent-line" checked={filters.view === 'attention'} onChange={(e) => router.get('/approvals', { ...filters, view: e.target.checked ? 'attention' : undefined })} />
                        Only those needing attention
                    </label>
                </div>

                {adding && <NewApplication filters={filters} projects={projects} types={types} people={people} onDone={() => setAdding(false)} />}

                {applications.length === 0 ? (
                    <p className="text-ink-soft">No applications here.</p>
                ) : (
                    <ul className="grid gap-3">
                        {applications.map((a) => (
                            <li key={a.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-4', a.decisionOverdue || (a.daysToExpiry !== null && a.daysToExpiry <= 60) ? 'border-hivis' : 'border-concrete')}>
                                <div className="flex flex-wrap items-start justify-between gap-3">
                                    <div className="min-w-0">
                                        <p className="font-semibold">{a.typeLabel}{a.description && `: ${a.description}`}</p>
                                        <p className="text-sm text-ink-soft">
                                            <Link href={`/projects/${a.project.id}`} className="hover:underline">{a.project.name}</Link>
                                            {a.authority && `, ${a.authority}`}
                                            {a.reference && `, ref ${a.reference}`}
                                            {a.responsible && `, ${a.responsible}`}
                                        </p>
                                        <p className="mt-1 text-sm">
                                            {[a.submittedOn && `Submitted ${formatDate(a.submittedOn)}`, a.expectedDecisionOn && !a.decisionOn && `decision expected ${formatDate(a.expectedDecisionOn)}`, a.decisionOn && `decided ${formatDate(a.decisionOn)}`, a.validUntil && `valid until ${formatDate(a.validUntil)}`].filter(Boolean).join(', ')}
                                        </p>
                                        {a.decisionOverdue && <p className="mt-1 text-sm font-semibold text-brick">Decision is overdue. Follow up with the authority.</p>}
                                        {a.daysToExpiry !== null && a.daysToExpiry <= 60 && (
                                            <p className="mt-1 text-sm font-semibold text-brick">{a.daysToExpiry < 0 ? 'This approval has lapsed.' : `Lapses in ${a.daysToExpiry} days.`}</p>
                                        )}
                                        {a.conditions && <p className="mt-1 text-sm"><span className="text-ink-soft">Conditions: </span>{a.conditions}</p>}
                                    </div>
                                    {canManage ? (
                                        <select aria-label="Status" className={selectClass + ' h-9 w-52 text-sm'} value={a.status}
                                            onChange={(e) => router.patch(`/approvals/${a.id}`, { status: e.target.value, ...(e.target.value === 'submitted' && !a.submittedOn ? { submitted_on: today() } : {}), ...(['approved', 'refused'].includes(e.target.value) && !a.decisionOn ? { decision_on: today() } : {}) }, { preserveScroll: true })}>
                                            {statuses.map((s) => (<option key={s.key} value={s.key}>{s.label}</option>))}
                                        </select>
                                    ) : (
                                        <span className="text-sm">{statuses.find((s) => s.key === a.status)?.label}</span>
                                    )}
                                </div>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function NewApplication({ filters, projects, types, people, onDone }: { filters: Props['filters']; projects: Option[]; types: Props['types']; people: Option[]; onDone: () => void }) {
    const form = useForm({ project: filters.project ?? '', type: 'building_plans', description: '', authority: types.find((t) => t.key === 'building_plans')?.authority ?? '', reference_number: '', expected_decision_on: '', valid_until: '', responsible: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.post('/approvals', { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField label="Project" name="project" value={form.data.project} onChange={(v) => form.setData('project', v)} options={projects} placeholder="Choose a project" error={form.errors.project} />
                <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => { form.setData('type', v); form.setData('authority', types.find((t) => t.key === v)?.authority ?? ''); }} options={types} />
                <Field label="Authority" name="authority" value={form.data.authority} onChange={(e) => form.setData('authority', e.target.value)} />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Description" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="e.g. Rezoning to General Residential 3" />
                <Field label="Reference number" name="reference_number" value={form.data.reference_number} onChange={(e) => form.setData('reference_number', e.target.value)} />
                <Field label="Decision expected" name="expected_decision_on" type="date" value={form.data.expected_decision_on} onChange={(e) => form.setData('expected_decision_on', e.target.value)} />
                <SelectField label="Responsible" name="responsible" value={form.data.responsible} onChange={(v) => form.setData('responsible', v)} options={people} placeholder="Nobody yet" />
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>Add to register</Button>
                <Button type="button" variant="secondary" onClick={onDone}>Cancel</Button>
            </div>
        </form>
    );
}

Approvals.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
