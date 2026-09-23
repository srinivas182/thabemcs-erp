import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Lease {
    id: string; reference: string; status: string; type: string; unit: string; project: { id: string; code: string };
    tenant: { id: string; name: string; email: string | null; phone: string | null; fica: boolean; credit: boolean };
    signed: string | null; starts: string; ends: string | null; monthToMonth: boolean; rent: number; rentNow: number;
    escalation: number; vat: boolean; paymentDay: number; noticeDays: number | null;
    deposit: number; depositAccount: string | null; depositReceived: string | null; depositInterest: number;
    depositDeductions: number; depositRefundDue: number; depositRefunded: string | null;
    ended: string | null; endReason: string | null; notes: string | null;
}
interface Invoice { id: string; reference: string; period: string; due: string; total: number; paid: number; outstanding: number; status: string; lines: { description: string; amount: number; vat: number }[] }
interface Props {
    lease: Lease;
    charges: { id: number; type: string; description: string; amount: number; escalates: boolean; vat: boolean }[];
    invoices: Invoice[];
    receipts: { id: number; amount: number; on: string; method: string; reference: string | null }[];
    arrears: { total: number; current: number; days30: number; days60: number; days90: number; oldestDue: string | null };
    inspections: { id: string; type: string; on: string; tenantPresent: boolean; items: { area: string; condition: string; notes?: string | null }[]; notes: string | null }[];
    maintenance: { id: string; reference: string; category: string; description: string; priority: string; status: string; supplier: string | null; cost: number | null; reported: string; byTenant: boolean }[];
    chargeTypes: { key: string; label: string }[];
    inspectionAreas: string[];
    conditions: string[];
    canManage: boolean;
}

export default function LeasePage({ lease: l, charges, invoices, receipts, arrears, inspections, maintenance, chargeTypes, inspectionAreas, conditions, canManage }: Props) {
    const today = new Date().toLocaleDateString('en-CA');
    const [panel, setPanel] = useState<'receipt' | 'charge' | 'inspection' | 'end' | null>(null);

    return (
        <>
            <Head title={`${l.reference}: ${l.unit}`} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/rentals" className="hover:underline">Rentals</Link> / <Link href={`/projects/${l.project.id}/sales`} className="hover:underline">{l.project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{l.reference}: {l.unit}</h1>
                    <p className="text-ink-soft">
                        {l.tenant.name} · {l.type} lease · {formatDate(l.starts)}{l.monthToMonth ? ', month to month' : l.ends ? ` to ${formatDate(l.ends)}` : ''} ·{' '}
                        <span className="capitalize">{l.status}</span>
                    </p>
                    {!l.tenant.fica && <p className="mt-2 rounded-[var(--radius-control)] bg-brick-wash px-3 py-2 text-sm text-brick">FICA is not verified for {l.tenant.name}.</p>}
                    {canManage && l.status === 'draft' && (
                        <Button className="mt-3" onClick={() => router.post(`/rentals/leases/${l.id}/activate`, {}, { preserveScroll: true })}>Activate the lease</Button>
                    )}
                </header>

                <section className="grid gap-4 sm:grid-cols-3">
                    <dl className="grid gap-1 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 text-sm">
                        <Row label="Rent now" value={formatRand(l.rentNow)} />
                        <Row label="Rent at signature" value={formatRand(l.rent)} />
                        <Row label="Escalation" value={`${l.escalation}% a year`} />
                        <Row label="VAT" value={l.vat ? 'Charged (commercial)' : 'Exempt (residential)'} />
                        <Row label="Due" value={`Day ${l.paymentDay} of the month`} />
                        <Row label="Notice" value={l.noticeDays ? `${l.noticeDays} days` : '—'} />
                    </dl>
                    <dl className="grid gap-1 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 text-sm">
                        <Row label="Deposit" value={formatRand(l.deposit)} />
                        <Row label="Received" value={l.depositReceived ? formatDate(l.depositReceived) : 'Not yet'} />
                        <Row label="Held in" value={l.depositAccount ?? '—'} />
                        <Row label="Interest earned" value={formatRand(l.depositInterest)} />
                        <Row label="Deductions" value={formatRand(l.depositDeductions)} />
                        <Row label="Refund due" value={formatRand(l.depositRefundDue)} />
                    </dl>
                    <div className={cn('grid content-start gap-1 rounded-[var(--radius-panel)] border bg-surface p-4 text-sm', arrears.total > 0 ? 'border-brick' : 'border-concrete')}>
                        <p className="font-semibold">Arrears</p>
                        <p className={cn('text-2xl font-bold tabular-nums', arrears.total > 0 && 'text-brick')}>{formatRand(arrears.total)}</p>
                        <Row label="Not yet due" value={formatRand(arrears.current)} />
                        <Row label="30 days" value={formatRand(arrears.days30)} />
                        <Row label="60 days" value={formatRand(arrears.days60)} />
                        <Row label="90+ days" value={formatRand(arrears.days90)} />
                        {arrears.oldestDue && <p className="text-xs text-ink-soft">Oldest due {formatDate(arrears.oldestDue)}</p>}
                    </div>
                </section>

                {canManage && (
                    <div className="flex flex-wrap gap-2">
                        <Button size="sm" variant="secondary" onClick={() => setPanel(panel === 'receipt' ? null : 'receipt')}>Record a receipt</Button>
                        <Button size="sm" variant="secondary" onClick={() => setPanel(panel === 'charge' ? null : 'charge')}>Add a charge</Button>
                        <Button size="sm" variant="secondary" onClick={() => setPanel(panel === 'inspection' ? null : 'inspection')}>Record an inspection</Button>
                        <Button size="sm" variant="secondary" onClick={() => router.post(`/rentals/leases/${l.id}/bill`, { month: today }, { preserveScroll: true })}>Invoice this month</Button>
                        {l.status === 'active' && <Button size="sm" variant="ghost" className="text-brick" onClick={() => setPanel(panel === 'end' ? null : 'end')}>End the lease</Button>}
                    </div>
                )}

                {panel === 'receipt' && <Receipt leaseId={l.id} today={today} onDone={() => setPanel(null)} />}
                {panel === 'charge' && <Charge leaseId={l.id} chargeTypes={chargeTypes} onDone={() => setPanel(null)} />}
                {panel === 'inspection' && <Inspection leaseId={l.id} areas={inspectionAreas} conditions={conditions} today={today} onDone={() => setPanel(null)} />}
                {panel === 'end' && <EndLease leaseId={l.id} today={today} refundDue={l.depositRefundDue} onDone={() => setPanel(null)} />}

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Monthly charges</h2>
                    <ul className="text-sm">
                        {charges.map((c) => <li key={c.id}>{c.description}: {formatRand(c.amount)}{c.escalates ? ' (escalates)' : ''}{c.vat ? ' + VAT' : ''}</li>)}
                    </ul>
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Invoices</h2>
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr><th>Invoice</th><th>Period</th><th>Due</th><th className="text-right">Total</th><th className="text-right">Outstanding</th><th>Status</th></tr></thead>
                            <tbody>
                                {invoices.map((i) => (
                                    <tr key={i.id}>
                                        <td>{i.reference}</td><td>{i.period}</td><td>{formatDate(i.due)}</td>
                                        <td className="text-right tabular-nums">{formatRand(i.total)}</td>
                                        <td className={cn('text-right tabular-nums', i.outstanding > 0 && 'text-brick')}>{i.outstanding > 0 ? formatRand(i.outstanding) : '—'}</td>
                                        <td className="capitalize">{i.status.replace('_', ' ')}</td>
                                    </tr>
                                ))}
                                {invoices.length === 0 && <tr><td colSpan={6} className="text-ink-soft">Nothing invoiced yet.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                    {receipts.length > 0 && <p className="text-sm text-ink-soft">Latest receipts: {receipts.slice(0, 4).map((r) => `${formatRand(r.amount)} on ${formatDate(r.on)}`).join(', ')}</p>}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Inspections</h2>
                    {inspections.length === 0 ? <p className="text-ink-soft">No inspections recorded. An incoming inspection protects both sides at the end of the lease.</p> : (
                        <ul className="grid gap-2">
                            {inspections.map((i) => (
                                <li key={i.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 text-sm">
                                    <p className="font-semibold capitalize">{i.type} inspection, {formatDate(i.on)}{i.tenantPresent ? ', tenant present' : ''}</p>
                                    <ul className="mt-1 grid gap-0.5 sm:grid-cols-2">
                                        {i.items.map((item, k) => <li key={k}>{item.area}: <span className={cn(['poor', 'damaged'].includes(item.condition) && 'font-medium text-brick')}>{item.condition}</span>{item.notes ? ` — ${item.notes}` : ''}</li>)}
                                    </ul>
                                    {i.notes && <p className="mt-1 text-ink-soft">{i.notes}</p>}
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Maintenance</h2>
                    {maintenance.length === 0 ? <p className="text-ink-soft">No requests.</p> : (
                        <ul className="grid gap-1 text-sm">
                            {maintenance.map((m) => (
                                <li key={m.id}>
                                    <span className="font-medium">{m.reference}</span> {m.description}
                                    <span className="text-ink-soft"> — {m.status.replace('_', ' ')}{m.supplier ? `, ${m.supplier}` : ''}{m.cost ? `, ${formatRand(m.cost)}` : ''}{m.byTenant ? ', reported by the tenant' : ''}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                    <p className="text-sm text-ink-soft"><Link href="/rentals/maintenance" className="underline">All maintenance requests</Link></p>
                </section>
            </div>
        </>
    );
}

function Row({ label, value }: { label: string; value: string }) {
    return <div className="flex justify-between gap-3"><dt className="text-ink-soft">{label}</dt><dd className="text-right">{value}</dd></div>;
}

function Panel({ title, children }: { title: string; children: ReactNode }) {
    return <section className="grid gap-4 rounded-[var(--radius-panel)] border-2 border-ink bg-surface p-5"><h2 className="text-lg font-bold">{title}</h2>{children}</section>;
}

function Receipt({ leaseId, today, onDone }: { leaseId: string; today: string; onDone: () => void }) {
    const form = useForm({ amount: '', received_on: today, method: 'eft', reference: '' });
    return (
        <Panel title="Record a receipt">
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/rentals/leases/${leaseId}/receipts`, { preserveScroll: true, onSuccess: onDone }); }} className="grid items-end gap-4 sm:grid-cols-5">
                <Field label="Amount (R)" name="amount" type="number" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
                <Field label="Received on" name="received_on" type="date" value={form.data.received_on} onChange={(e) => form.setData('received_on', e.target.value)} />
                <SelectField label="Method" name="method" value={form.data.method} onChange={(v) => form.setData('method', v)} options={[{ key: 'eft', label: 'EFT' }, { key: 'debit_order', label: 'Debit order' }, { key: 'cash', label: 'Cash' }, { key: 'card', label: 'Card' }]} />
                <Field label="Reference" name="reference" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                <Button type="submit" disabled={form.processing}>Save receipt</Button>
            </form>
            <p className="text-xs text-ink-soft">Money is allocated to the oldest unpaid invoices first.</p>
        </Panel>
    );
}

function Charge({ leaseId, chargeTypes, onDone }: { leaseId: string; chargeTypes: { key: string; label: string }[]; onDone: () => void }) {
    const form = useForm({ type: 'utilities', description: '', amount: '', escalates: false });
    return (
        <Panel title="Add a monthly charge">
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/rentals/leases/${leaseId}/charges`, { preserveScroll: true, onSuccess: onDone }); }} className="grid items-end gap-4 sm:grid-cols-5">
                <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={chargeTypes} />
                <Field label="Description" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} error={form.errors.description} />
                <Field label="Amount (R)" name="amount" type="number" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
                <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.escalates} onChange={(e) => form.setData('escalates', e.target.checked)} /> Escalates yearly</label>
                <Button type="submit" disabled={form.processing}>Add charge</Button>
            </form>
        </Panel>
    );
}

function Inspection({ leaseId, areas, conditions, today, onDone }: { leaseId: string; areas: string[]; conditions: string[]; today: string; onDone: () => void }) {
    const form = useForm<{ type: string; inspected_on: string; tenant_present: boolean; notes: string; items: { area: string; condition: string; notes: string }[] }>({
        type: 'incoming', inspected_on: today, tenant_present: true, notes: '',
        items: areas.map((area) => ({ area, condition: 'good', notes: '' })),
    });
    return (
        <Panel title="Record an inspection">
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/rentals/leases/${leaseId}/inspections`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-4">
                <div className="grid items-end gap-4 sm:grid-cols-4">
                    <SelectField label="Inspection" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={[{ key: 'incoming', label: 'Incoming (move in)' }, { key: 'outgoing', label: 'Outgoing (move out)' }]} />
                    <Field label="Date" name="inspected_on" type="date" value={form.data.inspected_on} onChange={(e) => form.setData('inspected_on', e.target.value)} />
                    <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.tenant_present} onChange={(e) => form.setData('tenant_present', e.target.checked)} /> Tenant present</label>
                </div>
                <ul className="grid gap-2">
                    {form.data.items.map((item, i) => (
                        <li key={item.area} className="grid items-end gap-2 sm:grid-cols-[1fr_160px_1fr]">
                            <span className="pb-2 text-sm">{item.area}</span>
                            <SelectField label="" name={`c${i}`} value={item.condition} onChange={(v) => form.setData('items', form.data.items.map((x, j) => (j === i ? { ...x, condition: v } : x)))} options={conditions.map((c) => ({ key: c, label: c[0]!.toUpperCase() + c.slice(1) }))} />
                            <Field label="" aria-label={`Notes for ${item.area}`} name={`n${i}`} value={item.notes} onChange={(e) => form.setData('items', form.data.items.map((x, j) => (j === i ? { ...x, notes: e.target.value } : x)))} placeholder="Notes" />
                        </li>
                    ))}
                </ul>
                <Field label="General notes" name="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
                <div><Button type="submit" disabled={form.processing}>Save inspection</Button></div>
            </form>
        </Panel>
    );
}

function EndLease({ leaseId, today, refundDue, onDone }: { leaseId: string; today: string; refundDue: number; onDone: () => void }) {
    const form = useForm({ ended_on: today, end_reason: '', deposit_deductions: '', deposit_refunded_on: '' });
    return (
        <Panel title="End the lease">
            <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, deposit_refunded_on: d.deposit_refunded_on || null })); form.post(`/rentals/leases/${leaseId}/end`, { preserveScroll: true, onSuccess: onDone }); }} className="grid items-end gap-4 sm:grid-cols-5">
                <Field label="Ended on" name="ended_on" type="date" value={form.data.ended_on} onChange={(e) => form.setData('ended_on', e.target.value)} />
                <Field label="Reason" name="end_reason" value={form.data.end_reason} onChange={(e) => form.setData('end_reason', e.target.value)} error={form.errors.end_reason} placeholder="Notice given, lease expired" />
                <Field label="Deposit deductions (R)" name="deposit_deductions" type="number" value={form.data.deposit_deductions} onChange={(e) => form.setData('deposit_deductions', e.target.value)} />
                <Field label="Deposit refunded on" name="deposit_refunded_on" type="date" value={form.data.deposit_refunded_on} onChange={(e) => form.setData('deposit_refunded_on', e.target.value)} />
                <Button type="submit" disabled={form.processing}>End lease</Button>
            </form>
            <p className="text-xs text-ink-soft">Refund due before deductions: {formatRand(refundDue)}. Deductions must be supported by the outgoing inspection.</p>
        </Panel>
    );
}

LeasePage.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
