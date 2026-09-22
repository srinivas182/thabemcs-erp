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
}

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function EmployeePage({ employee: e, balances, allocations, leave, overtime, projects, leaveTypes }: Props) {
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

EmployeePage.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
