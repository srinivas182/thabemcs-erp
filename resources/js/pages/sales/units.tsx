import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatRand, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };
interface Unit {
    id: string; reference: string; type: string; description: string | null; size: number | null; bedrooms: number | null;
    price: number; netPrice: number; vat: boolean; nhbrc: string | null; status: string;
    buyer: string | null; agreement: string | null; agreementRef: string | null; reservationExpires: string | null;
}
interface Props {
    project: { id: string; name: string; code: string };
    units: Unit[];
    revenue: { hasStock: boolean; units: number; sold: number; transferred: number; available: number; forecast: number; contracted: number; earned: number; deposits: number; listValue: number };
    unitTypes: Option[]; conditionTypes: Option[]; reservationDays: number; commissionPercent: number; canManage: boolean;
}

const STATUS: Record<string, string> = { available: 'Available', reserved: 'Reserved', sold: 'Sold', transferred: 'Transferred', withdrawn: 'Withdrawn' };
const TONE: Record<string, string> = { available: 'bg-line-wash text-line-deep', reserved: 'bg-hivis/20 text-ink', sold: 'bg-ink text-white', transferred: 'bg-line text-white', withdrawn: 'bg-concrete text-ink-soft' };

export default function SalesUnits({ project, units, revenue, unitTypes, conditionTypes, reservationDays, commissionPercent, canManage }: Props) {
    const [acting, setActing] = useState<{ unit: Unit; mode: 'reserve' | 'sign' | 'price' | 'let' } | null>(null);
    const [adding, setAdding] = useState(false);

    return (
        <>
            <Head title={`Sales: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Sales</h1>
                        <p className="text-ink-soft">{project.name}. Prices include VAT where the seller is a VAT vendor; revenue figures exclude VAT.</p>
                    </div>
                    <div className="flex gap-2">
                        <Button variant="ghost" asChild><Link href="/sales/buyers">Buyers</Link></Button>
                        {canManage && <Button onClick={() => setAdding(!adding)}>Add a unit</Button>}
                    </div>
                </header>

                {revenue.hasStock && (
                    <div className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete md:grid-cols-4">
                        <Figure label="Units" value={`${revenue.sold} of ${revenue.units} sold`} note={`${revenue.available} available`} />
                        <Figure label="Forecast revenue" value={formatRand(revenue.forecast)} note="Sold at agreed prices, the rest at list" />
                        <Figure label="Contracted" value={formatRand(revenue.contracted)} note={`${formatRand(revenue.deposits)} deposits received`} />
                        <Figure label="Registered" value={formatRand(revenue.earned)} note={`${revenue.transferred} transferred`} />
                    </div>
                )}

                {adding && canManage && <AddUnit projectId={project.id} unitTypes={unitTypes} onDone={() => setAdding(false)} />}

                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className="w-full min-w-[820px] text-sm [&_td]:px-3 [&_td]:py-2.5 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                        <thead><tr><th>Unit</th><th>Type</th><th className="text-right">Size</th><th className="text-right">Price</th><th>Status</th><th>Buyer</th><th /></tr></thead>
                        <tbody>
                            {units.map((u) => (
                                <tr key={u.id}>
                                    <td className="font-semibold">{u.reference}{u.description && <span className="block text-xs font-normal text-ink-soft">{u.description}</span>}</td>
                                    <td>{unitTypes.find((t) => t.key === u.type)?.label}{u.bedrooms ? `, ${u.bedrooms} bed` : ''}</td>
                                    <td className="text-right tabular-nums">{u.size ? `${u.size} m²` : '—'}</td>
                                    <td className="text-right tabular-nums">{formatRand(u.price)}{u.vat && <span className="block text-xs text-ink-soft">{formatRand(u.netPrice)} excl. VAT</span>}</td>
                                    <td><span className={cn('rounded-full px-2 py-0.5 text-xs font-medium', TONE[u.status])}>{STATUS[u.status]}</span>
                                        {u.reservationExpires && <span className="block text-xs text-ink-soft">holds to {formatDate(u.reservationExpires)}</span>}</td>
                                    <td>{u.agreement ? <Link href={`/sales/agreements/${u.agreement}`} className="hover:underline">{u.buyer} <span className="text-ink-soft">({u.agreementRef})</span></Link> : (u.buyer ?? '—')}</td>
                                    <td className="text-right whitespace-nowrap">
                                        {canManage && (
                                            <>
                                                {u.status === 'available' && <button className="text-line hover:underline" onClick={() => setActing({ unit: u, mode: 'reserve' })}>Reserve</button>}
                                                {(u.status === 'available' || u.status === 'reserved') && <button className="ml-3 text-line hover:underline" onClick={() => setActing({ unit: u, mode: 'sign' })}>Record sale</button>}
                                                {['available', 'withdrawn'].includes(u.status) && <button className="ml-3 text-ink-soft hover:underline" onClick={() => router.post(`/sales/units/${u.id}/withdraw`, {}, { preserveScroll: true })}>{u.status === 'withdrawn' ? 'Restore' : 'Withdraw'}</button>}
                                                <button className="ml-3 text-ink-soft hover:underline" onClick={() => setActing({ unit: u, mode: 'price' })}>Price</button>
                                                {['available', 'withdrawn'].includes(u.status) && <button className="ml-3 text-line hover:underline" onClick={() => setActing({ unit: u, mode: 'let' })}>Let</button>}
                                            </>
                                        )}
                                    </td>
                                </tr>
                            ))}
                            {units.length === 0 && <tr><td colSpan={7} className="text-ink-soft">No units yet. Add the erven or units that are for sale.</td></tr>}
                        </tbody>
                    </table>
                </div>

                {acting?.mode === 'reserve' && <Reserve unit={acting.unit} days={reservationDays} onDone={() => setActing(null)} />}
                {acting?.mode === 'sign' && <Sign unit={acting.unit} conditionTypes={conditionTypes} commissionPercent={commissionPercent} onDone={() => setActing(null)} />}
                {acting?.mode === 'price' && <PriceChange unit={acting.unit} onDone={() => setActing(null)} />}
                {acting?.mode === 'let' && <LetUnit unit={acting.unit} onDone={() => setActing(null)} />}
            </div>
        </>
    );
}

function Figure({ label, value, note }: { label: string; value: string; note: string }) {
    return <div className="bg-surface p-4"><p className="text-xs text-ink-soft">{label}</p><p className="mt-1 text-xl font-bold tabular-nums">{value}</p><p className="mt-1 text-xs text-ink-soft">{note}</p></div>;
}

function Panel({ title, children, onDone }: { title: string; children: ReactNode; onDone: () => void }) {
    return (
        <section className="grid gap-4 rounded-[var(--radius-panel)] border-2 border-ink bg-surface p-5">
            <div className="flex items-center justify-between"><h2 className="text-lg font-bold">{title}</h2><button className="text-sm text-ink-soft underline" onClick={onDone}>Close</button></div>
            {children}
        </section>
    );
}

function AddUnit({ projectId, unitTypes, onDone }: { projectId: string; unitTypes: Option[]; onDone: () => void }) {
    const form = useForm({ reference: '', type: 'erf', description: '', size_m2: '', bedrooms: '', list_price: '', vat_applies: true, nhbrc_enrolment: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, size_m2: d.size_m2 || null, bedrooms: d.bedrooms || null, nhbrc_enrolment: d.nhbrc_enrolment || null }));
        form.post(`/projects/${projectId}/sales/units`, { preserveScroll: true, onSuccess: () => form.reset() });
    }
    return (
        <Panel title="Add a unit" onDone={onDone}>
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-4">
                    <Field label="Erf or unit number" name="reference" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} error={form.errors.reference} />
                    <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={unitTypes} />
                    <Field label="Size (m²)" name="size_m2" type="number" value={form.data.size_m2} onChange={(e) => form.setData('size_m2', e.target.value)} />
                    <Field label="Bedrooms" name="bedrooms" type="number" value={form.data.bedrooms} onChange={(e) => form.setData('bedrooms', e.target.value)} />
                </div>
                <div className="grid gap-4 sm:grid-cols-4">
                    <Field label="Asking price (R)" name="list_price" type="number" value={form.data.list_price} onChange={(e) => form.setData('list_price', e.target.value)} error={form.errors.list_price} />
                    <Field label="NHBRC enrolment" name="nhbrc_enrolment" value={form.data.nhbrc_enrolment} onChange={(e) => form.setData('nhbrc_enrolment', e.target.value)} />
                    <Field label="Description" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} />
                    <label className="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.vat_applies} onChange={(e) => form.setData('vat_applies', e.target.checked)} /> Price includes VAT</label>
                </div>
                <div><Button type="submit" disabled={form.processing}>Add unit</Button></div>
            </form>
        </Panel>
    );
}

function Reserve({ unit, days, onDone }: { unit: Unit; days: number; onDone: () => void }) {
    const today = new Date().toLocaleDateString('en-CA');
    const form = useForm({ buyer: '', reserved_on: today, expires_on: new Date(Date.now() + days * 86_400_000).toLocaleDateString('en-CA'), deposit_amount: '', notes: '' });
    return (
        <Panel title={`Reserve ${unit.reference}`} onDone={onDone}>
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/sales/units/${unit.id}/reserve`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-4">
                    <LookupField label="Buyer" name="buyer" type="buyers" value={form.data.buyer} onChange={(v) => form.setData('buyer', v)} error={form.errors.buyer} placeholder="Search buyers" />
                    <Field label="Reserved on" name="reserved_on" type="date" value={form.data.reserved_on} onChange={(e) => form.setData('reserved_on', e.target.value)} />
                    <Field label="Holds until" name="expires_on" type="date" value={form.data.expires_on} onChange={(e) => form.setData('expires_on', e.target.value)} error={form.errors.expires_on} />
                    <Field label="Reservation deposit (R)" name="deposit_amount" type="number" value={form.data.deposit_amount} onChange={(e) => form.setData('deposit_amount', e.target.value)} />
                </div>
                <p className="text-xs text-ink-soft">The unit goes back on the market automatically when the reservation runs out, and the person who reserved it is told.</p>
                <div><Button type="submit" disabled={form.processing}>Reserve</Button></div>
            </form>
        </Panel>
    );
}

function Sign({ unit, conditionTypes, commissionPercent, onDone }: { unit: Unit; conditionTypes: Option[]; commissionPercent: number; onDone: () => void }) {
    const today = new Date().toLocaleDateString('en-CA');
    const form = useForm<{ buyer: string; signed_on: string; purchase_price: string; vat_applies: boolean; deposit_amount: string; deposit_due_on: string; deposit_held_by: string; trust_account_ref: string; bond_required: boolean; bond_amount: string; bond_originator: string; occupation_date: string; commission_percent: string; agent_supplier: string; conveyancer_supplier: string; conditions: { type: string; due_on: string }[] }>({
        buyer: '', signed_on: today, purchase_price: String(unit.price), vat_applies: unit.vat, deposit_amount: '', deposit_due_on: '', deposit_held_by: '', trust_account_ref: '',
        bond_required: true, bond_amount: '', bond_originator: '', occupation_date: '', commission_percent: String(commissionPercent), agent_supplier: '', conveyancer_supplier: '',
        conditions: [{ type: 'bond_approval', due_on: '' }],
    });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({
            ...d, deposit_amount: d.deposit_amount || null, deposit_due_on: d.deposit_due_on || null, bond_amount: d.bond_amount || null,
            occupation_date: d.occupation_date || null, agent_supplier: d.agent_supplier || null, conveyancer_supplier: d.conveyancer_supplier || null,
            conditions: d.conditions.map((c) => ({ ...c, due_on: c.due_on || null })),
        }));
        form.post(`/sales/units/${unit.id}/sign`);
    }
    return (
        <Panel title={`Record the sale of ${unit.reference}`} onDone={onDone}>
            <form onSubmit={submit} className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-4">
                    <LookupField label="Buyer" name="buyer" type="buyers" value={form.data.buyer} onChange={(v) => form.setData('buyer', v)} error={form.errors.buyer} placeholder="Search buyers" />
                    <Field label="Signed on" name="signed_on" type="date" value={form.data.signed_on} onChange={(e) => form.setData('signed_on', e.target.value)} error={form.errors.signed_on} />
                    <Field label="Purchase price (R)" name="purchase_price" type="number" value={form.data.purchase_price} onChange={(e) => form.setData('purchase_price', e.target.value)} error={form.errors.purchase_price} />
                    <Field label="Occupation date" name="occupation_date" type="date" value={form.data.occupation_date} onChange={(e) => form.setData('occupation_date', e.target.value)} />
                </div>
                <div className="grid gap-4 sm:grid-cols-4">
                    <Field label="Deposit (R)" name="deposit_amount" type="number" value={form.data.deposit_amount} onChange={(e) => form.setData('deposit_amount', e.target.value)} />
                    <Field label="Deposit due" name="deposit_due_on" type="date" value={form.data.deposit_due_on} onChange={(e) => form.setData('deposit_due_on', e.target.value)} />
                    <Field label="Deposit held by (trust account)" name="deposit_held_by" value={form.data.deposit_held_by} onChange={(e) => form.setData('deposit_held_by', e.target.value)} />
                    <Field label="Trust account reference" name="trust_account_ref" value={form.data.trust_account_ref} onChange={(e) => form.setData('trust_account_ref', e.target.value)} />
                </div>
                <div className="grid gap-4 sm:grid-cols-4">
                    <Field label="Bond amount (R)" name="bond_amount" type="number" value={form.data.bond_amount} onChange={(e) => form.setData('bond_amount', e.target.value)} />
                    <Field label="Bond originator" name="bond_originator" value={form.data.bond_originator} onChange={(e) => form.setData('bond_originator', e.target.value)} />
                    <LookupField label="Estate agency" name="agent_supplier" type="suppliers" params={{ types: 'estate_agency' }} value={form.data.agent_supplier} onChange={(v) => form.setData('agent_supplier', v)} placeholder="None" />
                    <Field label="Commission %" name="commission_percent" type="number" step="0.1" value={form.data.commission_percent} onChange={(e) => form.setData('commission_percent', e.target.value)} />
                </div>
                <LookupField label="Conveyancer" name="conveyancer_supplier" type="suppliers" value={form.data.conveyancer_supplier} onChange={(v) => form.setData('conveyancer_supplier', v)} placeholder="Choose the attorney handling the transfer" />

                <fieldset className="grid gap-2">
                    <legend className="text-sm font-medium">Suspensive conditions</legend>
                    {form.data.conditions.map((c, i) => (
                        <div key={i} className="grid items-end gap-2 sm:grid-cols-[1fr_200px_auto]">
                            <SelectField label="" name={`c${i}`} value={c.type} onChange={(v) => form.setData('conditions', form.data.conditions.map((x, j) => (j === i ? { ...x, type: v } : x)))} options={conditionTypes} />
                            <Field label="" aria-label="Due" name={`cd${i}`} type="date" value={c.due_on} onChange={(e) => form.setData('conditions', form.data.conditions.map((x, j) => (j === i ? { ...x, due_on: e.target.value } : x)))} />
                            <button type="button" className="mb-2 text-sm text-brick hover:underline" onClick={() => form.setData('conditions', form.data.conditions.filter((_, j) => j !== i))}>Remove</button>
                        </div>
                    ))}
                    <Button type="button" variant="ghost" size="sm" className="justify-self-start" onClick={() => form.setData('conditions', [...form.data.conditions, { type: 'other', due_on: '' }])}>Add a condition</Button>
                    <p className="text-xs text-ink-soft">Leave the due date blank to use the standard period. The sale becomes unconditional once every condition is met or waived; if one fails, the sale lapses and the unit goes back on the market.</p>
                </fieldset>
                <div><Button type="submit" disabled={form.processing}>Record the sale</Button></div>
            </form>
        </Panel>
    );
}

function PriceChange({ unit, onDone }: { unit: Unit; onDone: () => void }) {
    const form = useForm({ price: String(unit.price), effective_from: new Date().toLocaleDateString('en-CA'), reason: '' });
    return (
        <Panel title={`Price for ${unit.reference}`} onDone={onDone}>
            <form onSubmit={(e) => { e.preventDefault(); form.post(`/sales/units/${unit.id}/price`, { preserveScroll: true, onSuccess: onDone }); }} className="grid items-end gap-4 sm:grid-cols-4">
                <Field label="New price (R)" name="price" type="number" value={form.data.price} onChange={(e) => form.setData('price', e.target.value)} error={form.errors.price} />
                <Field label="Effective from" name="effective_from" type="date" value={form.data.effective_from} onChange={(e) => form.setData('effective_from', e.target.value)} />
                <Field label="Reason" name="reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="Annual escalation" />
                <Button type="submit" disabled={form.processing}>Save price</Button>
            </form>
        </Panel>
    );
}

function LetUnit({ unit, onDone }: { unit: Unit; onDone: () => void }) {
    const today = new Date().toLocaleDateString('en-CA');
    const form = useForm({
        tenant: '', type: 'residential', signed_on: today, starts_on: today, ends_on: '', month_to_month: false,
        rent_amount: '', vat_applies: false, escalation_percent: '8', payment_day: '1',
        deposit_amount: '', deposit_account: '', deposit_received_on: '', notes: '',
    });
    return (
        <Panel title={`Let ${unit.reference}`} onDone={onDone}>
            <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, ends_on: d.ends_on || null, deposit_received_on: d.deposit_received_on || null })); form.post(`/rentals/units/${unit.id}/lease`); }} className="grid gap-4">
                <div className="grid gap-4 sm:grid-cols-4">
                    <LookupField label="Tenant" name="tenant" type="tenants" value={form.data.tenant} onChange={(v) => form.setData('tenant', v)} error={form.errors.tenant} placeholder="Search tenants" />
                    <SelectField label="Lease type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={[{ key: 'residential', label: 'Residential' }, { key: 'commercial', label: 'Commercial (VAT)' }]} />
                    <Field label="Starts" name="starts_on" type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} />
                    <Field label="Ends" name="ends_on" type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} error={form.errors.ends_on} />
                </div>
                <div className="grid gap-4 sm:grid-cols-4">
                    <Field label="Rent a month (R)" name="rent_amount" type="number" value={form.data.rent_amount} onChange={(e) => form.setData('rent_amount', e.target.value)} error={form.errors.rent_amount} />
                    <Field label="Escalation % a year" name="escalation_percent" type="number" step="0.1" value={form.data.escalation_percent} onChange={(e) => form.setData('escalation_percent', e.target.value)} />
                    <Field label="Rent due on day" name="payment_day" type="number" value={form.data.payment_day} onChange={(e) => form.setData('payment_day', e.target.value)} error={form.errors.payment_day} />
                    <label className="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.month_to_month} onChange={(e) => form.setData('month_to_month', e.target.checked)} /> Month to month</label>
                </div>
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Deposit (R)" name="deposit_amount" type="number" value={form.data.deposit_amount} onChange={(e) => form.setData('deposit_amount', e.target.value)} />
                    <Field label="Interest-bearing account holding it" name="deposit_account" value={form.data.deposit_account} onChange={(e) => form.setData('deposit_account', e.target.value)} />
                    <Field label="Deposit received on" name="deposit_received_on" type="date" value={form.data.deposit_received_on} onChange={(e) => form.setData('deposit_received_on', e.target.value)} />
                </div>
                <p className="text-xs text-ink-soft">The lease is created as a draft with a private link for the tenant. Activate it once signed; rent is then invoiced monthly with escalations on each anniversary.</p>
                <div><Button type="submit" disabled={form.processing}>Create the lease</Button></div>
            </form>
        </Panel>
    );
}

SalesUnits.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
