import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { ApprovalTrail, type ApprovalTrailData, STATUS_LABEL } from '@/components/approval-trail';
import { formatDate, formatDateTime, formatRand, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Line = { id?: number; description: string; quantity: number | string; unit: string; unit_price: number | string; received?: number; outstanding?: number };
interface Props {
    order: { id: string; reference: string; status: string; project: { id: string; name: string }; supplier: { id: string; name: string; vat: string | null }; requisition: { id: string; reference: string } | null; subtotal: number; vat: number; total: number; vatApplies: boolean; expectedDelivery: string | null; instructions: string | null; approvedAt: string | null; issuedAt: string | null; budgetLineId: number | null };
    lines: Required<Line>[];
    receipts: { id: string; reference: string; on: string; by: string; notes: string | null }[];
    approval: ApprovalTrailData | null;
    blockers: string[];
    deliveries: { key: string; label: string }[];
    budgetLines: { key: string; label: string }[];
    can: { procure: boolean; receive: boolean };
}

export default function OrderShow({ order: o, lines, receipts, approval, blockers, deliveries, budgetLines, can }: Props) {
    const draft = o.status === 'draft' && can.procure;
    return (
        <>
            <Head title={o.reference} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/purchase-orders" className="hover:underline">Purchase orders</Link>{o.requisition && <> / <Link href={`/requisitions/${o.requisition.id}`} className="hover:underline">{o.requisition.reference}</Link></>}</p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{o.reference} to <Link href={`/suppliers/${o.supplier.id}`} className="hover:underline">{o.supplier.name}</Link></h1>
                        <p className="text-ink-soft">{STATUS_LABEL[o.status]}, {o.project.name}{o.issuedAt && `, issued ${formatDateTime(o.issuedAt)}`}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {draft && <Button onClick={() => router.post(`/purchase-orders/${o.id}/submit`, {}, { preserveScroll: true })} disabled={blockers.length > 0}>Submit for approval</Button>}
                        {o.status === 'approved' && can.procure && <Button onClick={() => router.post(`/purchase-orders/${o.id}/issue`, {}, { preserveScroll: true })} disabled={blockers.length > 0}>Issue to supplier</Button>}
                        {['draft', 'approved'].includes(o.status) && can.procure && <Button variant="ghost" className="text-brick" onClick={() => window.confirm(`Cancel ${o.reference}?`) && router.post(`/purchase-orders/${o.id}/cancel`)}>Cancel order</Button>}
                    </div>
                </header>

                {blockers.length > 0 && !['received', 'cancelled'].includes(o.status) && (
                    <div className="rounded-[var(--radius-panel)] bg-brick-wash px-4 py-3 text-brick">
                        <p className="font-semibold">This supplier cannot be ordered from right now</p>
                        <ul className="mt-1 list-disc pl-5 text-sm">{blockers.map((b) => <li key={b} className="first-letter:uppercase">{b}</li>)}</ul>
                    </div>
                )}

                <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                    {draft ? <LinesEditor order={o} lines={lines} budgetLines={budgetLines} /> : <LinesView order={o} lines={lines} />}
                    {approval && <ApprovalTrail approval={approval} />}
                </div>

                {['issued', 'partially_received'].includes(o.status) && can.receive && <Receive order={o} lines={lines} deliveries={deliveries} />}

                {receipts.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-bold">Goods received</h2>
                        <ul className="text-sm">{receipts.map((r) => <li key={r.id}>{r.reference}, {formatDate(r.on)}, {r.by}{r.notes && `: ${r.notes}`}</li>)}</ul>
                    </section>
                )}
            </div>
        </>
    );
}

function Totals({ order: o }: { order: Props['order'] }) {
    return (
        <tbody className="[&_td]:py-1.5">
            <tr><td colSpan={3} className="text-right text-ink-soft">Subtotal</td><td className="text-right tabular-nums">{formatRand(o.subtotal)}</td></tr>
            <tr><td colSpan={3} className="text-right text-ink-soft">{o.vatApplies ? 'VAT 15%' : 'No VAT (supplier not VAT registered)'}</td><td className="text-right tabular-nums">{formatRand(o.vat)}</td></tr>
            <tr><td colSpan={3} className="text-right font-semibold">Total</td><td className="text-right font-bold tabular-nums">{formatRand(o.total)}</td></tr>
        </tbody>
    );
}

function LinesView({ order, lines }: { order: Props['order']; lines: Props['lines'] }) {
    return (
        <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
            <table className={tableClass}>
                <thead><tr><th>Item</th><th className="text-right">Qty</th><th className="text-right">Received</th><th className="text-right">Amount</th></tr></thead>
                <tbody>{lines.map((l) => (<tr key={l.id}><td>{l.description}<p className="text-ink-soft">{formatRand(Number(l.unit_price))} per {l.unit}</p></td><td className="text-right tabular-nums">{l.quantity}</td><td className="text-right tabular-nums">{l.received}</td><td className="text-right tabular-nums">{formatRand(Number(l.quantity) * Number(l.unit_price))}</td></tr>))}</tbody>
                <Totals order={order} />
            </table>
        </div>
    );
}

function LinesEditor({ order, lines, budgetLines }: { order: Props['order']; lines: Props['lines']; budgetLines: Props['budgetLines'] }) {
    const form = useForm<{ expected_delivery: string; delivery_instructions: string; budget_line_id: string; lines: Line[] }>({
        expected_delivery: order.expectedDelivery ?? '', delivery_instructions: order.instructions ?? '', budget_line_id: order.budgetLineId ? String(order.budgetLineId) : '',
        lines: lines.map(({ description, quantity, unit, unit_price }) => ({ description, quantity, unit, unit_price })),
    });
    const setLine = (i: number, patch: Partial<Line>) => form.setData('lines', form.data.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));
    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, expected_delivery: d.expected_delivery || null, budget_line_id: d.budget_line_id || null }));
        form.put(`/purchase-orders/${order.id}`, { preserveScroll: true });
    }
    return (
        <form onSubmit={save} className="grid content-start gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
            <p className="text-sm text-ink-soft">Line prices were spread from the awarded quote. Adjust them to match the supplier's quotation, then save.</p>
            {form.data.lines.map((l, i) => (
                <div key={i} className="grid items-end gap-2 sm:grid-cols-[1fr_90px_90px_130px_auto]">
                    <Field label={i === 0 ? 'Item' : ''} aria-label="Item" name={`d${i}`} value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} />
                    <Field label={i === 0 ? 'Qty' : ''} aria-label="Quantity" name={`q${i}`} type="number" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} />
                    <Field label={i === 0 ? 'Unit' : ''} aria-label="Unit" name={`u${i}`} value={l.unit} onChange={(e) => setLine(i, { unit: e.target.value })} />
                    <Field label={i === 0 ? 'Unit price (R)' : ''} aria-label="Unit price" name={`p${i}`} type="number" step="0.01" value={l.unit_price} onChange={(e) => setLine(i, { unit_price: e.target.value })} />
                    <button type="button" className="mb-2 p-1 text-ink-soft hover:text-brick disabled:opacity-30" disabled={form.data.lines.length === 1} onClick={() => form.setData('lines', form.data.lines.filter((_, j) => j !== i))} aria-label="Remove line"><Trash2 className="size-4" /></button>
                </div>
            ))}
            <Button type="button" variant="ghost" size="sm" className="justify-self-start" onClick={() => form.setData('lines', [...form.data.lines, { description: '', quantity: 1, unit: 'each', unit_price: 0 }])}><Plus className="size-4" /> Add line</Button>
            <SelectField label="Cost code" name="budget_line_id" value={form.data.budget_line_id} onChange={(v) => form.setData('budget_line_id', v)} options={budgetLines} placeholder={budgetLines.length ? 'Choose the budget line this order is charged to' : 'This project has no budget yet'} />
            <div className="grid gap-3 sm:grid-cols-2">
                <Field label="Expected delivery" name="expected_delivery" type="date" value={form.data.expected_delivery} onChange={(e) => form.setData('expected_delivery', e.target.value)} />
                <Field label="Delivery instructions" name="delivery_instructions" value={form.data.delivery_instructions} onChange={(e) => form.setData('delivery_instructions', e.target.value)} />
            </div>
            <table className="w-full text-sm"><Totals order={order} /></table>
            <div><Button type="submit" disabled={form.processing}>Save order</Button></div>
        </form>
    );
}

function Receive({ order, lines, deliveries }: { order: Props['order']; lines: Props['lines']; deliveries: Props['deliveries'] }) {
    const [qty, setQty] = useState<Record<number, string>>(Object.fromEntries(lines.map((l) => [l.id, String(l.outstanding)])));
    const form = useForm({ received_on: new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date()), delivery: '', notes: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, delivery: d.delivery || null, quantities: qty }));
        form.post(`/purchase-orders/${order.id}/receive`, { preserveScroll: true });
    }
    return (
        <form onSubmit={submit} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
            <h2 className="font-bold">Receive goods</h2>
            <div className="grid gap-2">
                {lines.filter((l) => l.outstanding > 0).map((l) => (
                    <label key={l.id} className="flex flex-wrap items-center justify-between gap-2 text-sm">
                        <span>{l.description} <span className="text-ink-soft">({l.outstanding} {l.unit} outstanding)</span></span>
                        <input type="number" min={0} max={l.outstanding} step="0.001" value={qty[l.id]} onChange={(e) => setQty({ ...qty, [l.id]: e.target.value })} className="h-9 w-28 rounded-[var(--radius-control)] border border-concrete px-2 text-right" aria-label={`Quantity received of ${l.description}`} />
                    </label>
                ))}
            </div>
            <div className="grid gap-3 sm:grid-cols-3">
                <Field label="Received on" name="received_on" type="date" value={form.data.received_on} onChange={(e) => form.setData('received_on', e.target.value)} />
                <SelectField label="Site delivery record" name="delivery" value={form.data.delivery} onChange={(v) => form.setData('delivery', v)} options={deliveries} placeholder="Not linked" />
                <Field label="Notes" name="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </div>
            <div><Button type="submit" disabled={form.processing}>Record goods received</Button></div>
        </form>
    );
}

OrderShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
