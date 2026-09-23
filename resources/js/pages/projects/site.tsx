import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { MapPinOff } from 'lucide-react';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { formatDate, formatDateTime, selectClass, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';


interface Props {
    project: { id: string; name: string; code: string; hasLocation: boolean };
    diaries: { id: number; date: string; weather: string; workers: number; work: string; delays: string | null; by: string | null }[];
    attendance: { id: number; name: string; direction: string; at: string; within: boolean | null; distance: number | null; hasSelfie: boolean }[];
    photos: { id: number; caption: string | null; at: string; geotagged: boolean }[];
    deliveries: { id: string; supplier: string | null; note: string | null; items: string; condition: string; notes: string | null; at: string; by: string | null }[];
    instructions: { id: string; number: number; subject: string; instruction: string; cost: boolean; time: boolean; status: string; at: string; by: string | null; supplier: string | null }[];
    inspections: { id: string; title: string; location: string | null; result: string; findings: string | null; on: string }[];
    snags: { id: string; location: string | null; description: string; status: string; dueOn: string | null; supplier: string | null }[];
    crew: { date: string; present: number; absent: number }[];
    canManage: boolean;
}

const TABS = [
    { key: 'diary', label: 'Diary' },
    { key: 'attendance', label: 'Attendance' },
    { key: 'photos', label: 'Photos' },
    { key: 'deliveries', label: 'Deliveries' },
    { key: 'instructions', label: 'Instructions' },
    { key: 'quality', label: 'Quality and snags' },
] as const;
type Tab = (typeof TABS)[number]['key'];

export default function Site(props: Props) {
    const { project } = props;
    const [tab, setTab] = useState<Tab>('diary');
    useEffect(() => {
        const hash = window.location.hash.slice(1) as Tab;
        if (TABS.some((t) => t.key === hash)) setTab(hash);
    }, []);

    return (
        <>
            <Head title={`Site: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Site</h1>
                    <p className="text-ink-soft">{project.name}. Site teams capture this on their phones with the site app at <a href="/site/" className="text-line hover:underline">/site</a>.</p>
                    {!project.hasLocation && (
                        <p className="mt-2 flex items-center gap-1.5 text-sm text-brick"><MapPinOff className="size-4" /> This project has no site location, so attendance cannot be checked against a geofence. Add it on the project's edit page.</p>
                    )}
                </header>
                <nav className="flex gap-1 overflow-x-auto border-b border-concrete" aria-label="Site sections">
                    {TABS.map((t) => (
                        <button key={t.key} onClick={() => { setTab(t.key); window.history.replaceState(null, '', `#${t.key}`); }}
                            className={cn('-mb-px shrink-0 border-b-2 px-3 py-2 text-sm font-medium', tab === t.key ? 'border-line text-ink' : 'border-transparent text-ink-soft hover:text-ink')}>{t.label}</button>
                    ))}
                </nav>
                {tab === 'diary' && <Diary {...props} />}
                {tab === 'attendance' && <Attendance {...props} />}
                {tab === 'photos' && <Photos {...props} />}
                {tab === 'deliveries' && <Deliveries {...props} />}
                {tab === 'instructions' && <Instructions {...props} />}
                {tab === 'quality' && <Quality {...props} />}
            </div>
        </>
    );
}

function Empty({ children }: { children: ReactNode }) {
    return <p className="text-ink-soft">{children}</p>;
}

function Diary({ diaries }: Props) {
    if (!diaries.length) return <Empty>No diary entries yet.</Empty>;
    return (
        <ol className="grid gap-3">
            {diaries.map((d) => (
                <li key={d.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                    <div className="flex flex-wrap justify-between gap-2">
                        <p className="font-semibold">{formatDate(d.date)}</p>
                        <p className="text-sm text-ink-soft">{d.weather}, {d.workers} workers, {d.by}</p>
                    </div>
                    <p className="mt-2 whitespace-pre-line">{d.work}</p>
                    {d.delays && <p className="mt-2 text-sm"><span className="font-semibold text-brick">Delays: </span>{d.delays}</p>}
                </li>
            ))}
        </ol>
    );
}

function Attendance({ attendance, crew }: Props) {
    const register = crew.length > 0 && (
        <p className="mb-3 text-sm">Crew register: {crew.map((c) => `${formatDate(c.date)} ${c.present} present${c.absent ? `, ${c.absent} absent` : ''}`).join('; ')}.</p>
    );
    if (!attendance.length) return <>{register}<Empty>No staff sign-ins in the last 7 days.</Empty></>;
    return (<>{register}
        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
            {attendance.map((a) => (
                <li key={a.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                    <span><span className="font-medium">{a.name}</span> signed {a.direction}, {formatDateTime(a.at)}</span>
                    <span className={cn('font-medium', a.within === false ? 'text-brick' : a.within ? 'text-line-deep' : 'text-ink-soft')}>
                        {a.within === null ? 'No location' : a.within ? 'On site' : `${a.distance?.toLocaleString('en-ZA')} m from site`}
                        {!a.hasSelfie && ', no selfie'}
                    </span>
                </li>
            ))}
        </ul>
    </>);
}

function Photos({ photos }: Props) {
    if (!photos.length) return <Empty>No photos yet.</Empty>;
    return (
        <ul className="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            {photos.map((p) => (
                <li key={p.id} className="overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <a href={`/site-photos/${p.id}`} target="_blank" rel="noreferrer"><img src={`/site-photos/${p.id}`} alt={p.caption ?? 'Site photo'} loading="lazy" className="aspect-[4/3] w-full object-cover" /></a>
                    <p className="p-2 text-xs"><span className="block truncate font-medium">{p.caption ?? 'No caption'}</span><span className="text-ink-soft">{formatDateTime(p.at)}{!p.geotagged && ', no location'}</span></p>
                </li>
            ))}
        </ul>
    );
}

function Deliveries({ deliveries }: Props) {
    if (!deliveries.length) return <Empty>No deliveries recorded.</Empty>;
    return (
        <ul className="grid gap-3">
            {deliveries.map((d) => (
                <li key={d.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-4', d.condition === 'good' ? 'border-concrete' : 'border-brick')}>
                    <div className="flex flex-wrap justify-between gap-2">
                        <p className="font-semibold">{d.supplier}{d.note && `, DN ${d.note}`}</p>
                        <p className="text-sm text-ink-soft">{formatDateTime(d.at)}, {d.by}</p>
                    </div>
                    <p className="mt-1 whitespace-pre-line text-sm">{d.items}</p>
                    {d.condition !== 'good' && <p className="mt-1 text-sm font-semibold text-brick">{d.condition === 'damaged' ? 'Damaged' : 'Short'}: {d.notes}</p>}
                </li>
            ))}
        </ul>
    );
}

function Instructions({ project, instructions, canManage }: Props) {
    const form = useForm({ supplier: '', subject: '', instruction: '', cost_implication: false, time_implication: false });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, supplier: d.supplier || null }));
        form.post(`/projects/${project.id}/site-instructions`, { preserveScroll: true, onSuccess: () => form.reset() });
    }
    return (
        <div className="grid gap-4">
            {canManage && (
                <form onSubmit={submit} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                    <p className="font-semibold">Issue a site instruction</p>
                    <div className="grid gap-3 sm:grid-cols-2">
                        <LookupField label="To" name="supplier" value={form.data.supplier} onChange={(v) => form.setData('supplier', v)} type="suppliers" placeholder="Choose contractor" />
                        <Field label="Subject" name="subject" value={form.data.subject} onChange={(e) => form.setData('subject', e.target.value)} error={form.errors.subject} />
                    </div>
                    <textarea aria-label="Instruction" rows={3} value={form.data.instruction} onChange={(e) => form.setData('instruction', e.target.value)} placeholder="Instruction" className="rounded-[var(--radius-control)] border border-concrete p-3 text-sm" />
                    <div className="flex flex-wrap gap-4 text-sm">
                        <label className="flex items-center gap-2"><input type="checkbox" className="accent-line" checked={form.data.cost_implication} onChange={(e) => form.setData('cost_implication', e.target.checked)} /> May change the cost</label>
                        <label className="flex items-center gap-2"><input type="checkbox" className="accent-line" checked={form.data.time_implication} onChange={(e) => form.setData('time_implication', e.target.checked)} /> May change the programme</label>
                    </div>
                    <div><Button type="submit" disabled={form.processing}>Issue instruction</Button></div>
                </form>
            )}
            {instructions.length === 0 ? <Empty>No site instructions issued.</Empty> : (
                <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    {instructions.map((i) => (
                        <li key={i.id} className="flex flex-wrap items-start justify-between gap-3 p-3">
                            <div className="min-w-0">
                                <p className="font-semibold">SI-{i.number}: {i.subject}</p>
                                <p className="text-sm text-ink-soft">{[i.supplier, i.by, formatDateTime(i.at), i.cost && 'cost impact', i.time && 'time impact'].filter(Boolean).join(', ')}</p>
                                <p className="mt-1 whitespace-pre-line text-sm">{i.instruction}</p>
                            </div>
                            {canManage && (
                                <select aria-label="Status" className={selectClass + ' h-9 w-40 text-sm'} value={i.status} onChange={(e) => router.patch(`/site-instructions/${i.id}`, { status: e.target.value }, { preserveScroll: true })}>
                                    <option value="issued">Issued</option><option value="acknowledged">Acknowledged</option><option value="closed">Closed</option>
                                </select>
                            )}
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function Quality({ project, inspections, snags, canManage }: Props) {
    const insp = useForm({ kind: 'quality', title: '', location: '', result: 'pass', findings: '', inspected_on: new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date()) });
    const snag = useForm({ location: '', description: '', supplier: '', due_on: '' });
    return (
        <div className="grid gap-8 lg:grid-cols-2">
            <section className="grid content-start gap-3">
                <h2 className="text-lg font-bold">Snag list</h2>
                {canManage && (
                    <form onSubmit={(e) => { e.preventDefault(); snag.transform((d) => ({ ...d, supplier: d.supplier || null, due_on: d.due_on || null })); snag.post(`/projects/${project.id}/snags`, { preserveScroll: true, onSuccess: () => snag.reset() }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Location" name="location" value={snag.data.location} onChange={(e) => snag.setData('location', e.target.value)} placeholder="e.g. Unit 12, kitchen" />
                            <Field label="Due" name="due_on" type="date" value={snag.data.due_on} onChange={(e) => snag.setData('due_on', e.target.value)} />
                        </div>
                        <Field label="Defect" name="description" value={snag.data.description} onChange={(e) => snag.setData('description', e.target.value)} error={snag.errors.description} />
                        <LookupField label="Contractor responsible" name="supplier" value={snag.data.supplier} onChange={(v) => snag.setData('supplier', v)} type="suppliers" placeholder="Not assigned" />
                        <div><Button type="submit" disabled={snag.processing}>Add snag</Button></div>
                    </form>
                )}
                {snags.length === 0 ? <Empty>No snags.</Empty> : (
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {snags.map((s) => (
                            <li key={s.id} className={cn('flex items-start justify-between gap-3 p-3', s.status === 'verified' && 'opacity-60')}>
                                <span className="min-w-0 text-sm"><span className="block font-medium">{s.description}</span><span className="text-ink-soft">{[s.location, s.supplier, s.dueOn && `due ${formatDate(s.dueOn)}`].filter(Boolean).join(', ')}</span></span>
                                {canManage ? (
                                    <select aria-label="Status" className={selectClass + ' h-9 w-32 text-sm'} value={s.status} onChange={(e) => router.patch(`/snags/${s.id}`, { status: e.target.value }, { preserveScroll: true })}>
                                        <option value="open">Open</option><option value="fixed">Fixed</option><option value="verified">Verified</option>
                                    </select>
                                ) : <span className="text-sm capitalize">{s.status}</span>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
            <section className="grid content-start gap-3">
                <h2 className="text-lg font-bold">Quality inspections</h2>
                {canManage && (
                    <form onSubmit={(e) => { e.preventDefault(); insp.post(`/projects/${project.id}/inspections`, { preserveScroll: true, onSuccess: () => insp.reset('title', 'location', 'findings') }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <Field label="Inspection" name="title" value={insp.data.title} onChange={(e) => insp.setData('title', e.target.value)} error={insp.errors.title} placeholder="e.g. Foundation reinforcement before pour" />
                        <div className="grid gap-3 sm:grid-cols-3">
                            <Field label="Location" name="location" value={insp.data.location} onChange={(e) => insp.setData('location', e.target.value)} />
                            <SelectField label="Result" name="result" value={insp.data.result} onChange={(v) => insp.setData('result', v)} options={[{ key: 'pass', label: 'Pass' }, { key: 'partial', label: 'Partial' }, { key: 'fail', label: 'Fail' }]} />
                            <Field label="Date" name="inspected_on" type="date" value={insp.data.inspected_on} onChange={(e) => insp.setData('inspected_on', e.target.value)} />
                        </div>
                        {insp.data.result !== 'pass' && <Field label="Findings" name="findings" value={insp.data.findings} onChange={(e) => insp.setData('findings', e.target.value)} error={insp.errors.findings} />}
                        <div><Button type="submit" disabled={insp.processing}>Record inspection</Button></div>
                    </form>
                )}
                {inspections.length === 0 ? <Empty>No inspections recorded.</Empty> : (
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                        {inspections.map((i) => (
                            <li key={i.id} className="p-3">
                                <p className="flex justify-between gap-2"><span className="font-medium">{i.title}</span><span className={cn('font-semibold capitalize', i.result === 'fail' ? 'text-brick' : i.result === 'partial' ? 'text-ink' : 'text-line-deep')}>{i.result}</span></p>
                                <p className="text-ink-soft">{[i.location, formatDate(i.on)].filter(Boolean).join(', ')}</p>
                                {i.findings && <p>{i.findings}</p>}
                            </li>
                        ))}
                    </ul>
                )}
            </section>
        </div>
    );
}

Site.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
