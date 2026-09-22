import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDate, formatDateTime, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Incident {
    id: string; type: string; typeLabel: string; at: string; location: string | null; description: string; immediateAction: string | null;
    person: string | null; reportable: boolean; reviewReportable: boolean; reportedAt: string | null; rootCause: string | null;
    correctiveAction: string | null; status: string; by: string | null;
}

interface Props {
    project: { id: string; name: string; code: string };
    stats: { daysSinceLostTime: number | null; nearMisses90: number; open: number; talks90: number };
    incidents: Incident[];
    inspections: { id: string; title: string; location: string | null; result: string; findings: string | null; on: string }[];
    talks: { id: number; topic: string; on: string; attendees: number; presenter: string | null }[];
    canManage: boolean;
}

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function Safety({ project, stats, incidents, inspections, talks, canManage }: Props) {
    const talk = useForm({ topic: '', held_on: today(), attendees: '', presenter: '' });
    const insp = useForm({ kind: 'safety', title: '', location: '', result: 'pass', findings: '', inspected_on: today() });
    const figures = [
        { label: 'Days since lost-time injury', value: stats.daysSinceLostTime ?? 'None recorded' },
        { label: 'Near misses (90 days)', value: stats.nearMisses90 },
        { label: 'Open incidents', value: stats.open },
        { label: 'Toolbox talks (90 days)', value: stats.talks90 },
    ];

    return (
        <>
            <Head title={`Safety: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Health and safety</h1>
                    <p className="text-ink-soft">{project.name}. Keep the safety file in <Link href={`/documents?project=${project.id}&folder=Health and Safety`} className="text-line hover:underline">Documents / Health and Safety</Link>.</p>
                </header>

                <section className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete sm:grid-cols-4">
                    {figures.map((f) => (<div key={f.label} className="bg-surface p-3"><p className="text-xs text-ink-soft">{f.label}</p><p className="mt-1 text-xl font-bold tabular-nums">{f.value}</p></div>))}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Incidents and near misses</h2>
                    <p className="text-sm text-ink-soft">Reported from the site app. Record the investigation here and close each incident once corrective action is in place.</p>
                    {incidents.length === 0 ? <p className="text-ink-soft">No incidents reported.</p> : (
                        <ul className="grid gap-3">{incidents.map((i) => (<IncidentCard key={i.id} incident={i} canManage={canManage} />))}</ul>
                    )}
                </section>

                <div className="grid gap-8 lg:grid-cols-2">
                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-bold">Toolbox talks</h2>
                        {canManage && (
                            <form onSubmit={(e) => { e.preventDefault(); talk.post(`/projects/${project.id}/toolbox-talks`, { preserveScroll: true, onSuccess: () => talk.reset('topic', 'attendees') }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                                <Field label="Topic" name="topic" value={talk.data.topic} onChange={(e) => talk.setData('topic', e.target.value)} error={talk.errors.topic} placeholder="e.g. Working at height" />
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Field label="Date" name="held_on" type="date" value={talk.data.held_on} onChange={(e) => talk.setData('held_on', e.target.value)} />
                                    <Field label="Attendees" name="attendees" type="number" min={1} value={talk.data.attendees} onChange={(e) => talk.setData('attendees', e.target.value)} error={talk.errors.attendees} />
                                    <Field label="Presenter" name="presenter" value={talk.data.presenter} onChange={(e) => talk.setData('presenter', e.target.value)} />
                                </div>
                                <div><Button type="submit" disabled={talk.processing}>Record talk</Button></div>
                            </form>
                        )}
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {talks.map((t) => (<li key={t.id} className="flex justify-between gap-2 p-3"><span className="font-medium">{t.topic}</span><span className="text-ink-soft">{formatDate(t.on)}, {t.attendees} people{t.presenter && `, ${t.presenter}`}</span></li>))}
                            {talks.length === 0 && <li className="p-3 text-ink-soft">No talks recorded.</li>}
                        </ul>
                    </section>
                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-bold">Safety inspections</h2>
                        {canManage && (
                            <form onSubmit={(e) => { e.preventDefault(); insp.post(`/projects/${project.id}/inspections`, { preserveScroll: true, onSuccess: () => insp.reset('title', 'location', 'findings') }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                                <Field label="Inspection" name="title" value={insp.data.title} onChange={(e) => insp.setData('title', e.target.value)} error={insp.errors.title} placeholder="e.g. Scaffold inspection, Block B" />
                                <div className="grid gap-3 sm:grid-cols-3">
                                    <Field label="Location" name="location" value={insp.data.location} onChange={(e) => insp.setData('location', e.target.value)} />
                                    <SelectField label="Result" name="result" value={insp.data.result} onChange={(v) => insp.setData('result', v)} options={[{ key: 'pass', label: 'Pass' }, { key: 'partial', label: 'Partial' }, { key: 'fail', label: 'Fail' }]} />
                                    <Field label="Date" name="inspected_on" type="date" value={insp.data.inspected_on} onChange={(e) => insp.setData('inspected_on', e.target.value)} />
                                </div>
                                {insp.data.result !== 'pass' && <Field label="Findings" name="findings" value={insp.data.findings} onChange={(e) => insp.setData('findings', e.target.value)} error={insp.errors.findings} />}
                                <div><Button type="submit" disabled={insp.processing}>Record inspection</Button></div>
                            </form>
                        )}
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {inspections.map((i) => (<li key={i.id} className="p-3"><p className="flex justify-between"><span className="font-medium">{i.title}</span><span className={cn('font-semibold capitalize', i.result === 'fail' ? 'text-brick' : 'text-line-deep')}>{i.result}</span></p><p className="text-ink-soft">{[i.location, formatDate(i.on)].filter(Boolean).join(', ')}</p>{i.findings && <p>{i.findings}</p>}</li>))}
                            {inspections.length === 0 && <li className="p-3 text-ink-soft">No safety inspections recorded.</li>}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}

function IncidentCard({ incident: i, canManage }: { incident: Incident; canManage: boolean }) {
    const [open, setOpen] = useState(false);
    const [rootCause, setRootCause] = useState(i.rootCause ?? '');
    const [action, setAction] = useState(i.correctiveAction ?? '');
    const serious = i.reportable || ['lost_time', 'fatality', 'dangerous_occurrence'].includes(i.type);
    const save = (data: Record<string, string | boolean | null>) => router.patch(`/safety-incidents/${i.id}`, data, { preserveScroll: true });

    return (
        <li className={cn('rounded-[var(--radius-panel)] border bg-surface p-4', serious ? 'border-brick' : 'border-concrete', i.status === 'closed' && 'opacity-70')}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="font-semibold">{i.typeLabel}{i.location && `, ${i.location}`}</p>
                    <p className="text-sm text-ink-soft">{formatDateTime(i.at)}, reported by {i.by}{i.person && `. Person involved: ${i.person}`}</p>
                    <p className="mt-1 whitespace-pre-line text-sm">{i.description}</p>
                    {i.reportable && (
                        <p className="mt-1 text-sm font-semibold text-brick">
                            Reportable to the Department of Employment and Labour: {i.reportedAt ? `reported ${formatDateTime(i.reportedAt)}` : 'not yet reported'}
                        </p>
                    )}
                    {!i.reportable && i.reviewReportable && <p className="mt-1 text-sm text-ink">Check whether this injury is reportable under section 24 of the OHS Act.</p>}
                </div>
                <span className="rounded-full bg-concrete-soft px-2 py-0.5 text-xs font-semibold capitalize">{i.status}</span>
            </div>
            {canManage && (
                <>
                    <button className="mt-2 text-sm font-medium text-line hover:underline" onClick={() => setOpen(!open)}>{open ? 'Hide investigation' : 'Investigation and actions'}</button>
                    {open && (
                        <div className="mt-3 grid gap-3 border-t border-concrete pt-3">
                            <Field label="Root cause" name="root_cause" value={rootCause} onChange={(e) => setRootCause(e.target.value)} />
                            <Field label="Corrective action" name="corrective_action" value={action} onChange={(e) => setAction(e.target.value)} />
                            <div className="flex flex-wrap items-center gap-3 text-sm">
                                <label className="flex items-center gap-2"><input type="checkbox" className="accent-brick" checked={i.reportable} onChange={(e) => save({ reportable: e.target.checked })} /> Reportable incident</label>
                                {i.reportable && !i.reportedAt && <Button size="sm" variant="secondary" onClick={() => save({ reported_to_authority_at: new Date().toISOString() })}>Mark reported today</Button>}
                            </div>
                            <div className="flex flex-wrap gap-2">
                                <Button size="sm" onClick={() => save({ root_cause: rootCause || null, corrective_action: action || null, status: i.status === 'open' ? 'investigating' : i.status })}>Save</Button>
                                {i.status !== 'closed' && <Button size="sm" variant="secondary" onClick={() => save({ root_cause: rootCause || null, corrective_action: action || null, status: 'closed' })}>Close incident</Button>}
                            </div>
                        </div>
                    )}
                </>
            )}
        </li>
    );
}

Safety.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
