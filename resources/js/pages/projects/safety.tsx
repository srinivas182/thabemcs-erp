import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatDateTime, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Incident {
    id: string; type: string; typeLabel: string; at: string; location: string | null; description: string; immediateAction: string | null;
    person: string | null; reportable: boolean; reviewReportable: boolean; reportedAt: string | null; rootCause: string | null;
    correctiveAction: string | null; status: string; by: string | null;
}

interface Compliance {
    appointments: { id: string; type: string; label: string; reference: string | null; name: string; appointedOn: string; expires: string | null; expired: boolean; expiring: boolean }[];
    gaps: string[];
    file: { item: string; label: string; status: string; reviewDue: string | null; notes: string | null; hasDocument: boolean }[];
    fileComplete: number;
    fileTotal: number;
}

interface Props {
    compliance: Compliance;
    appointmentTypes: { key: string; label: string }[];
    project: { id: string; name: string; code: string };
    stats: { daysSinceLostTime: number | null; nearMisses90: number; open: number; talks90: number };
    incidents: Incident[];
    inspections: { id: string; title: string; location: string | null; result: string; findings: string | null; on: string }[];
    talks: { id: number; topic: string; on: string; attendees: number; presenter: string | null }[];
    canManage: boolean;
}

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function Safety({ project, stats, incidents, inspections, talks, compliance, appointmentTypes, canManage }: Props) {
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

                <Compliance project={project} compliance={compliance} types={appointmentTypes} canManage={canManage} />

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

function Compliance({ project, compliance: c, types, canManage }: { project: Props['project']; compliance: Compliance; types: Props['appointmentTypes']; canManage: boolean }) {
    const form = useForm<{ type: string; appointee_name: string; appointed_on: string; competency_expires_on: string; file: File | null }>({ type: types[0]?.key ?? '', appointee_name: '', appointed_on: today(), competency_expires_on: '', file: null });
    function appoint(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, competency_expires_on: d.competency_expires_on || null }));
        form.post(`/projects/${project.id}/safety-appointments`, { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset('appointee_name', 'competency_expires_on', 'file') });
    }
    const setItem = (item: string, status: string) => router.post(`/projects/${project.id}/safety-file`, { item, status }, { preserveScroll: true });

    return (
        <div className="grid gap-8 lg:grid-cols-2">
            <section className="grid content-start gap-3">
                <h2 className="text-lg font-bold">Legal appointments</h2>
                {c.gaps.length > 0 && <p className="rounded-[var(--radius-control)] bg-brick-wash px-3 py-2 text-sm text-brick"><strong>Not yet appointed:</strong> {c.gaps.join(', ')}.</p>}
                <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                    {c.appointments.map((a) => (
                        <li key={a.id} className="flex flex-wrap items-start justify-between gap-2 p-3">
                            <span>
                                <span className="font-medium">{a.label}</span> <span className="text-ink-soft">{a.reference}</span>
                                <span className="block">{a.name}, from {formatDate(a.appointedOn)}</span>
                                {a.expires && <span className={cn('block', a.expired ? 'font-semibold text-brick' : a.expiring ? 'font-semibold text-ink' : 'text-ink-soft')}>Competency {a.expired ? 'expired' : 'valid to'} {formatDate(a.expires)}</span>}
                            </span>
                            {canManage && <button className="text-xs text-brick hover:underline" onClick={() => window.confirm(`End ${a.name}'s appointment?`) && router.post(`/safety-appointments/${a.id}/end`, {}, { preserveScroll: true })}>End</button>}
                        </li>
                    ))}
                    {c.appointments.length === 0 && <li className="p-3 text-ink-soft">No appointments recorded.</li>}
                </ul>
                {canManage && (
                    <form onSubmit={appoint} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <SelectField label="Appointment" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label="Appointed person" name="appointee_name" value={form.data.appointee_name} onChange={(e) => form.setData('appointee_name', e.target.value)} error={form.errors.appointee_name} />
                            <Field label="Appointed on" name="appointed_on" type="date" value={form.data.appointed_on} onChange={(e) => form.setData('appointed_on', e.target.value)} />
                            <Field label="Competency valid to" name="competency_expires_on" type="date" value={form.data.competency_expires_on} onChange={(e) => form.setData('competency_expires_on', e.target.value)} error={form.errors.competency_expires_on} />
                        </div>
                        <input type="file" accept=".pdf,.jpg,.jpeg,.png" aria-label="Signed appointment letter" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm" />
                        <div><Button type="submit" disabled={form.processing}>Record appointment</Button></div>
                    </form>
                )}
            </section>

            <section className="grid content-start gap-3">
                <h2 className="text-lg font-bold">Safety file <span className="font-normal text-ink-soft">({c.fileComplete} of {c.fileTotal} in place)</span></h2>
                <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                    {c.file.map((f) => (
                        <li key={f.item} className="flex items-center justify-between gap-3 px-3 py-2">
                            <span className={cn(f.status === 'not_applicable' && 'text-ink-soft line-through')}>{f.label}</span>
                            {canManage ? (
                                <select aria-label={`Status of ${f.label}`} value={f.status} onChange={(e) => setItem(f.item, e.target.value)}
                                    className={cn('h-8 rounded-[var(--radius-control)] border px-2 text-xs', f.status === 'missing' ? 'border-brick text-brick' : 'border-concrete')}>
                                    <option value="missing">Missing</option><option value="in_place">In place</option><option value="not_applicable">Not applicable</option>
                                </select>
                            ) : <span className="text-xs">{f.status === 'in_place' ? 'In place' : f.status === 'missing' ? 'Missing' : 'N/A'}</span>}
                        </li>
                    ))}
                </ul>
                <p className="text-xs text-ink-soft">Keep the documents themselves in <Link href={`/documents?project=${project.id}&folder=Health and Safety`} className="underline">Documents / Health and Safety</Link>.</p>
            </section>
        </div>
    );
}

Safety.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
