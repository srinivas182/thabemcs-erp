import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatDate, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };
interface Props {
    employee: { id: string; number: string; name: string; idNumber: string | null; jobTitle: string | null; type: string; start: string; end: string | null; phone: string | null; daysPerWeek: number; status: string };
    balances: Record<string, { label: string; entitlement: number | null; taken: number; remaining: number | null }>;
    allocations: { id: number; project: string; from: string; to: string | null; role: string | null }[];
    leave: { id: number; type: string; from: string; to: string; days: number; status: string; notes: string | null }[];
    overtime: { id: number; date: string; hours: number; multiplier: number; project: string | null; reason: string | null }[];
    projects: Option[];
    leaveTypes: Option[];
    allowances: { id: number; type: string; amount: number; frequency: string; from: string; to: string | null; notes: string | null }[];
    documents: { id: number; type: string; expires: string | null; expired: boolean; download: string | null }[];
}

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function EmployeePage({ employee: e, balances, allocations, leave, overtime, projects, leaveTypes, allowances, documents }: Props) {
    const alloc = useForm({ project: '', from_date: today(), role_on_site: '' });
    const lv = useForm({ type: 'annual', from_date: '', to_date: '', notes: '' });
    const ot = useForm({ worked_on: today(), hours: '', project: '', reason: '' });

    return (
        <>
            <Head title={e.name} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/workforce" className="hover:underline">Workforce</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{e.name}</h1>
                    <p className="text-ink-soft">{[e.number, e.jobTitle, e.idNumber && `ID ${e.idNumber}`, `started ${formatDate(e.start)}`, e.end && `contract ends ${formatDate(e.end)}`, `${e.daysPerWeek}-day week`].filter(Boolean).join(', ')}</p>
                </header>

                <section className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete sm:grid-cols-3">
                    {Object.entries(balances).filter(([, b]) => b.entitlement !== null).map(([k, b]) => (
                        <div key={k} className="bg-surface p-3">
                            <p className="text-xs text-ink-soft">{b.label}</p>
                            <p className={cn('mt-1 text-xl font-bold tabular-nums', (b.remaining ?? 0) <= 0 && 'text-brick')}>{b.remaining} <span className="text-sm font-normal text-ink-soft">of {b.entitlement} days left</span></p>
                        </div>
                    ))}
                </section>

                <div className="grid gap-8 lg:grid-cols-2">
                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-bold">Leave</h2>
                        <form onSubmit={(ev) => { ev.preventDefault(); lv.post(`/workforce/${e.id}/leave`, { preserveScroll: true, onSuccess: () => lv.reset('from_date', 'to_date', 'notes') }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <SelectField label="Type" name="type" value={lv.data.type} onChange={(v) => lv.setData('type', v)} options={leaveTypes} />
                                <Field label="From" name="from_date" type="date" value={lv.data.from_date} onChange={(ev) => lv.setData('from_date', ev.target.value)} error={lv.errors.from_date} />
                                <Field label="To" name="to_date" type="date" value={lv.data.to_date} onChange={(ev) => lv.setData('to_date', ev.target.value)} error={lv.errors.to_date} />
                            </div>
                            <p className="text-xs text-ink-soft">Weekends and public holidays are not counted.</p>
                            <div><Button type="submit" disabled={lv.processing}>Record leave</Button></div>
                        </form>
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {leave.map((l) => (
                                <li key={l.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                                    <span>{leaveTypes.find((t) => t.key === l.type)?.label}, {formatDate(l.from)} to {formatDate(l.to)} <span className="text-ink-soft">({l.days} days)</span></span>
                                    {l.status === 'pending' ? (
                                        <span className="flex gap-2">
                                            <Button size="sm" onClick={() => router.patch(`/leave/${l.id}`, { decision: 'approved' }, { preserveScroll: true })}>Approve</Button>
                                            <Button size="sm" variant="ghost" className="text-brick" onClick={() => router.patch(`/leave/${l.id}`, { decision: 'declined' }, { preserveScroll: true })}>Decline</Button>
                                        </span>
                                    ) : <span className={cn('capitalize', l.status === 'declined' && 'text-brick')}>{l.status}</span>}
                                </li>
                            ))}
                            {leave.length === 0 && <li className="p-3 text-ink-soft">No leave recorded.</li>}
                        </ul>
                    </section>

                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-bold">Overtime</h2>
                        <form onSubmit={(ev) => { ev.preventDefault(); ot.transform((d) => ({ ...d, project: d.project || null })); ot.post(`/workforce/${e.id}/overtime`, { preserveScroll: true, onSuccess: () => ot.reset('hours', 'reason') }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Field label="Date" name="worked_on" type="date" value={ot.data.worked_on} onChange={(ev) => ot.setData('worked_on', ev.target.value)} />
                                <Field label="Hours" name="hours" type="number" step="0.5" value={ot.data.hours} onChange={(ev) => ot.setData('hours', ev.target.value)} error={ot.errors.hours} />
                                <SelectField label="Project" name="project" value={ot.data.project} onChange={(v) => ot.setData('project', v)} options={projects} placeholder="None" />
                            </div>
                            <p className="text-xs text-ink-soft">BCEA limits: 3 hours a day, 10 hours a week. Sundays and public holidays are paid at double time.</p>
                            <div><Button type="submit" disabled={ot.processing}>Record overtime</Button></div>
                        </form>
                        <ul className="text-sm">{overtime.map((o) => <li key={o.id}>{formatDate(o.date)}: {o.hours} h at {o.multiplier}x{o.project && `, ${o.project}`}</li>)}</ul>
                    </section>
                </div>

                <div className="grid gap-8 lg:grid-cols-2">
                    <Allowances employeeId={e.id} allowances={allowances} />
                    <Documents employeeId={e.id} documents={documents} />
                </div>

                <section className="grid gap-3 border-t-2 border-ink pt-4">
                    <h2 className="font-bold">Site allocation</h2>
                    <ul className="text-sm">{allocations.map((a) => <li key={a.id}>{a.project}{a.role && ` as ${a.role}`}, from {formatDate(a.from)}{a.to ? ` to ${formatDate(a.to)}` : ' (current)'}</li>)}</ul>
                    <form onSubmit={(ev) => { ev.preventDefault(); alloc.post(`/workforce/${e.id}/allocations`, { preserveScroll: true }); }} className="grid items-end gap-3 sm:grid-cols-[1fr_180px_1fr_auto]">
                        <SelectField label="Move to project" name="project" value={alloc.data.project} onChange={(v) => alloc.setData('project', v)} options={projects} placeholder="Choose" error={alloc.errors.project} />
                        <Field label="From" name="from_date" type="date" value={alloc.data.from_date} onChange={(ev) => alloc.setData('from_date', ev.target.value)} />
                        <Field label="Role on site" name="role_on_site" value={alloc.data.role_on_site} onChange={(ev) => alloc.setData('role_on_site', ev.target.value)} />
                        <Button type="submit" disabled={alloc.processing}>Allocate</Button>
                    </form>
                </section>
            </div>
        </>
    );
}

const ALLOWANCES = ['travel', 'site', 'tool', 'meal', 'housing', 'cellphone', 'other'].map((k) => ({ key: k, label: k[0]!.toUpperCase() + k.slice(1) }));
const DOC_TYPES = [
    { key: 'contract', label: 'Employment contract' }, { key: 'id_copy', label: 'ID copy' }, { key: 'qualification', label: 'Qualification or certificate' },
    { key: 'medical', label: 'Medical certificate of fitness' }, { key: 'induction', label: 'Induction record' }, { key: 'warning', label: 'Disciplinary record' }, { key: 'other', label: 'Other' },
];

function Allowances({ employeeId, allowances }: { employeeId: string; allowances: Props['allowances'] }) {
    const form = useForm({ type: 'travel', amount: '', frequency: 'day', from_date: today(), notes: '' });
    return (
        <section className="grid content-start gap-3">
            <h2 className="text-lg font-bold">Allowances</h2>
            <ul className="text-sm">
                {allowances.map((a) => (
                    <li key={a.id} className="flex justify-between gap-2">
                        <span>{ALLOWANCES.find((t) => t.key === a.type)?.label}: R{a.amount.toFixed(2)} per {a.frequency === 'once' ? 'payment' : a.frequency}, from {formatDate(a.from)}{a.to && ` to ${formatDate(a.to)}`}</span>
                        {!a.to && <button className="text-xs text-brick hover:underline" onClick={() => router.post(`/allowances/${a.id}/end`, {}, { preserveScroll: true })}>End</button>}
                    </li>
                ))}
                {allowances.length === 0 && <li className="text-ink-soft">No allowances.</li>}
            </ul>
            <form onSubmit={(ev) => { ev.preventDefault(); form.post(`/workforce/${employeeId}/allowances`, { preserveScroll: true, onSuccess: () => form.reset('amount', 'notes') }); }} className="grid items-end gap-2 sm:grid-cols-4">
                <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={ALLOWANCES} />
                <Field label="Amount (R)" name="amount" type="number" value={form.data.amount} onChange={(ev) => form.setData('amount', ev.target.value)} error={form.errors.amount} />
                <SelectField label="Per" name="frequency" value={form.data.frequency} onChange={(v) => form.setData('frequency', v)} options={[{ key: 'day', label: 'Day worked' }, { key: 'month', label: 'Month' }, { key: 'once', label: 'Once-off' }]} />
                <Button type="submit" disabled={form.processing}>Add</Button>
            </form>
            <p className="text-xs text-ink-soft">Daily allowances are counted from the crew register and included in the payroll inputs export.</p>
        </section>
    );
}

function Documents({ employeeId, documents }: { employeeId: string; documents: Props['documents'] }) {
    const form = useForm<{ type: string; expires_on: string; file: File | null }>({ type: 'contract', expires_on: '', file: null });
    return (
        <section className="grid content-start gap-3">
            <h2 className="text-lg font-bold">Employment documents</h2>
            <ul className="text-sm">
                {documents.map((d) => (
                    <li key={d.id} className="flex justify-between gap-2">
                        <span>{DOC_TYPES.find((t) => t.key === d.type)?.label}{d.expires && <span className={cn(d.expired ? 'font-semibold text-brick' : 'text-ink-soft')}>, {d.expired ? 'expired' : 'valid to'} {formatDate(d.expires)}</span>}</span>
                        {d.download && <a href={d.download} className="text-line hover:underline">Open</a>}
                    </li>
                ))}
                {documents.length === 0 && <li className="text-ink-soft">No documents.</li>}
            </ul>
            <form onSubmit={(ev) => { ev.preventDefault(); form.transform((d) => ({ ...d, expires_on: d.expires_on || null })); form.post(`/workforce/${employeeId}/documents`, { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset() }); }} className="grid items-end gap-2 sm:grid-cols-[1fr_150px]">
                <SelectField label="Document" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={DOC_TYPES} />
                <Field label="Expires" name="expires_on" type="date" value={form.data.expires_on} onChange={(ev) => form.setData('expires_on', ev.target.value)} />
                <input type="file" accept=".pdf,.jpg,.jpeg,.png,.docx" onChange={(ev) => form.setData('file', ev.target.files?.[0] ?? null)} className="text-sm" aria-label="File" />
                <Button type="submit" disabled={!form.data.file || form.processing}>Upload</Button>
            </form>
            {form.errors.file && <p className="text-sm text-brick">{form.errors.file}</p>}
            <p className="text-xs text-ink-soft">Employment documents are private: only Company Admins and Directors can open them.</p>
        </section>
    );
}

EmployeePage.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
