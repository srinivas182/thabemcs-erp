import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Check } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Agreement {
    id: string; reference: string; status: string; project: { id: string; code: string; name: string }; unit: string; unitType: string; nhbrc: string | null;
    buyer: { id: string; name: string; fica: boolean }; signed: string; price: number; netPrice: number; vat: boolean;
    deposit: number; depositDue: string | null; depositReceived: string | null; depositHeldBy: string | null; trustRef: string | null;
    bondRequired: boolean; bondAmount: number | null; bondOriginator: string | null; occupation: string | null;
    agency: string | null; conveyancer: string | null; commissionPercent: number | null; commissionAmount: number | null;
    commissionStatus: string; registered: string | null; notes: string | null;
}
interface Condition { id: number; type: string; description: string; due: string; status: string; resolved: string | null; notes: string | null; overdue: boolean }
interface Step { id: number; step: string; label: string; completed: string | null; notes: string | null }

const STATUS_LABEL: Record<string, string> = {
    conditional: 'Conditions outstanding', unconditional: 'Unconditional', registered: 'Registered', lapsed: 'Lapsed', cancelled: 'Cancelled',
};

export default function AgreementPage({ agreement: a, conditions, steps, canManage }: { agreement: Agreement; conditions: Condition[]; steps: Step[]; canManage: boolean }) {
    const today = new Date().toLocaleDateString('en-CA');
    const deposit = useForm({ deposit_received_on: a.depositReceived ?? '', deposit_held_by: a.depositHeldBy ?? '', trust_account_ref: a.trustRef ?? '' });

    return (
        <>
            <Head title={`${a.reference}: ${a.unit}`} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft">
                        <Link href="/sales/agreements" className="hover:underline">Sale agreements</Link> / <Link href={`/projects/${a.project.id}/sales`} className="hover:underline">{a.project.code} sales</Link>
                    </p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{a.reference}: {a.unit}</h1>
                    <p className="text-ink-soft">
                        {a.buyer.name} · signed {formatDate(a.signed)} · {formatRand(a.price)}{a.vat && ` (${formatRand(a.netPrice)} excl. VAT)`} ·{' '}
                        <span className={cn('font-medium', a.status === 'registered' && 'text-line-deep', ['lapsed', 'cancelled'].includes(a.status) && 'text-brick')}>{STATUS_LABEL[a.status]}</span>
                    </p>
                    {!a.buyer.fica && <p className="mt-2 rounded-[var(--radius-control)] bg-brick-wash px-3 py-2 text-sm text-brick">FICA is not verified for {a.buyer.name}. The transfer cannot proceed until it is.</p>}
                    {a.unitType === 'house' && !a.nhbrc && <p className="mt-2 rounded-[var(--radius-control)] bg-hivis/20 px-3 py-2 text-sm">No NHBRC enrolment number is recorded for this home.</p>}
                </header>

                <section className="grid gap-4 sm:grid-cols-2">
                    <dl className="grid gap-1 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 text-sm">
                        <Row label="Deposit" value={`${formatRand(a.deposit)}${a.depositDue ? `, due ${formatDate(a.depositDue)}` : ''}`} />
                        <Row label="Deposit received" value={a.depositReceived ? formatDate(a.depositReceived) : 'Not yet'} />
                        <Row label="Held in trust by" value={a.depositHeldBy ?? '—'} />
                        <Row label="Trust reference" value={a.trustRef ?? '—'} />
                        <Row label="Bond" value={a.bondRequired ? `${a.bondAmount ? formatRand(a.bondAmount) : 'Required'}${a.bondOriginator ? ` via ${a.bondOriginator}` : ''}` : 'Cash purchase'} />
                        <Row label="Occupation" value={a.occupation ? formatDate(a.occupation) : '—'} />
                        <Row label="Conveyancer" value={a.conveyancer ?? '—'} />
                    </dl>
                    <div className="grid content-start gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <p className="font-semibold">Commission</p>
                        <p className="text-sm">{a.agency ?? 'No agency'}{a.commissionPercent ? ` · ${a.commissionPercent}% of the price excl. VAT` : ''}</p>
                        <p className="text-2xl font-bold tabular-nums">{a.commissionAmount ? formatRand(a.commissionAmount) : '—'}</p>
                        <p className={cn('text-sm capitalize', a.commissionStatus === 'pending' ? 'text-ink-soft' : 'text-line-deep')}>{a.commissionStatus}</p>
                        {canManage && a.commissionStatus === 'pending' && (
                            <Button size="sm" className="justify-self-start" onClick={() => router.post(`/sales/agreements/${a.id}/commission`, {}, { preserveScroll: true })}>Approve commission</Button>
                        )}
                        <p className="text-xs text-ink-soft">Payable once the transfer is registered, and only to an agency with a valid Fidelity Fund Certificate.</p>
                    </div>
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Suspensive conditions</h2>
                    {conditions.length === 0 ? <p className="text-ink-soft">No conditions: the sale was unconditional from signature.</p> : (
                        <ul className="grid gap-2">
                            {conditions.map((c) => <ConditionRow key={c.id} condition={c} canManage={canManage} today={today} />)}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Transfer</h2>
                    <ol className="grid gap-2">
                        {steps.map((s) => <StepRow key={s.id} step={s} canManage={canManage} today={today} />)}
                    </ol>
                </section>

                {canManage && (
                    <form onSubmit={(e) => { e.preventDefault(); deposit.transform((d) => ({ ...d, deposit_received_on: d.deposit_received_on || null })); deposit.patch(`/sales/agreements/${a.id}/deposit`, { preserveScroll: true }); }}
                        className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-4">
                        <Field label="Deposit received on" name="deposit_received_on" type="date" value={deposit.data.deposit_received_on} onChange={(e) => deposit.setData('deposit_received_on', e.target.value)} />
                        <Field label="Held in trust by" name="deposit_held_by" value={deposit.data.deposit_held_by} onChange={(e) => deposit.setData('deposit_held_by', e.target.value)} />
                        <Field label="Trust account reference" name="trust_account_ref" value={deposit.data.trust_account_ref} onChange={(e) => deposit.setData('trust_account_ref', e.target.value)} />
                        <Button type="submit" disabled={deposit.processing}>Save deposit</Button>
                    </form>
                )}
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex justify-between gap-3"><dt className="text-ink-soft">{label}</dt><dd className="text-right">{value}</dd></div>;
}

function ConditionRow({ condition: c, canManage, today }: { condition: Condition; canManage: boolean; today: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ status: 'met', resolved_on: today, notes: '' });
    const done = c.status !== 'open';
    return (
        <li className={cn('rounded-[var(--radius-panel)] border bg-surface p-3 text-sm', c.overdue ? 'border-brick' : 'border-concrete')}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <span><span className="font-semibold">{c.description}</span><span className="block text-ink-soft">Due {formatDate(c.due)}{c.overdue && ' — overdue'}{c.resolved && `, ${c.status} on ${formatDate(c.resolved)}`}{c.notes && `: ${c.notes}`}</span></span>
                {done ? <span className={cn('font-medium capitalize', c.status === 'failed' ? 'text-brick' : 'text-line-deep')}>{c.status}</span>
                    : canManage && <Button size="sm" variant="secondary" onClick={() => setOpen(!open)}>Record outcome</Button>}
            </div>
            {open && !done && (
                <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, notes: d.notes || null })); form.patch(`/sales/conditions/${c.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="mt-3 grid items-end gap-2 sm:grid-cols-4">
                    <SelectField label="Outcome" name={`s${c.id}`} value={form.data.status} onChange={(v) => form.setData('status', v)} options={[{ key: 'met', label: 'Met' }, { key: 'waived', label: 'Waived' }, { key: 'failed', label: 'Failed (sale lapses)' }]} />
                    <Field label="On" name={`d${c.id}`} type="date" value={form.data.resolved_on} onChange={(e) => form.setData('resolved_on', e.target.value)} />
                    <Field label="Notes" name={`n${c.id}`} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} error={form.errors.notes} />
                    <Button type="submit" disabled={form.processing}>Save</Button>
                </form>
            )}
        </li>
    );
}

function StepRow({ step: s, canManage, today }: { step: Step; canManage: boolean; today: string }) {
    const [open, setOpen] = useState(false);
    const form = useForm({ completed_on: today, notes: '' });
    return (
        <li className="flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 text-sm">
            <span className="flex items-center gap-2">
                <span className={cn('flex size-5 items-center justify-center rounded-full', s.completed ? 'bg-line text-white' : 'border border-concrete')}>{s.completed && <Check className="size-3.5" />}</span>
                <span>{s.label}{s.completed && <span className="text-ink-soft"> — {formatDate(s.completed)}</span>}{s.notes && <span className="block text-xs text-ink-soft">{s.notes}</span>}</span>
            </span>
            {!s.completed && canManage && (open ? (
                <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, notes: d.notes || null })); form.patch(`/sales/transfer-steps/${s.id}`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex items-end gap-2">
                    <Field label="" aria-label="Completed on" name={`t${s.id}`} type="date" value={form.data.completed_on} onChange={(e) => form.setData('completed_on', e.target.value)} />
                    <Button size="sm" type="submit" disabled={form.processing}>Save</Button>
                </form>
            ) : <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>Mark done</Button>)}
        </li>
    );
}

AgreementPage.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
