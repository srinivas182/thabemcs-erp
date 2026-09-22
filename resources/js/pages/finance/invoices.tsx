import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { AlertTriangle, Check } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatRand, PageHeader, Pager, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

type Option = { key: string; label: string };
interface Row { id: string; number: string; supplier: string; project: string; po: string | null; date: string; due: string; subtotal: number; vat: number; total: number; status: string; issues: string[]; override: string | null; approver: string | null; mine: boolean }
interface Props {
    invoices: Paginated<Row>;
    filter: string;
    suppliers: Option[];
    orders: (Option & { supplier: string })[];
    projects: Option[];
    budgetLines: (Option & { project: string })[];
    canOverride: boolean;
}

const LABEL: Record<string, string> = { captured: 'Captured', matched: 'Matched', exception: 'Match failed', approved: 'Approved', scheduled: 'In a payment run', paid: 'Paid', rejected: 'Rejected' };

export default function Invoices({ invoices, filter, suppliers, orders, projects, budgetLines, canOverride }: Props) {
    const [capturing, setCapturing] = useState(false);
    return (
        <>
            <Head title="Supplier invoices" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader title="Supplier invoices" description="Every invoice is matched to its purchase order and the goods received before it can be paid."
                    action={<div className="flex gap-2"><Button variant="secondary" asChild><Link href="/payment-runs">Payment runs</Link></Button><Button onClick={() => setCapturing(!capturing)}>Capture invoice</Button></div>} />
                {capturing && <Capture suppliers={suppliers} orders={orders} projects={projects} budgetLines={budgetLines} onDone={() => setCapturing(false)} />}
                <div className="flex flex-wrap gap-1.5">
                    {['', 'exception', 'matched', 'approved', 'scheduled', 'paid', 'rejected'].map((s) => (
                        <button key={s} onClick={() => router.get('/invoices', s ? { status: s } : {})} className={cn('rounded-full border px-3 py-1 text-sm', filter === s ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>{s ? LABEL[s] : 'All'}</button>
                    ))}
                </div>
                {invoices.data.length === 0 ? <p className="text-ink-soft">No invoices.</p> : (
                    <ul className="grid gap-3">{invoices.data.map((i) => <InvoiceCard key={i.id} invoice={i} canOverride={canOverride} />)}</ul>
                )}
                <Pager prev={invoices.prev_page_url} next={invoices.next_page_url} page={invoices.current_page} last={invoices.last_page} />
            </div>
        </>
    );
}

function InvoiceCard({ invoice: i, canOverride }: { invoice: Row; canOverride: boolean }) {
    const [reason, setReason] = useState('');
    const act = (path: string, data: Record<string, string | null> = {}) => router.post(`/invoices/${i.id}/${path}`, data, { preserveScroll: true });
    return (
        <li className={cn('rounded-[var(--radius-panel)] border bg-surface p-4', i.status === 'exception' ? 'border-brick' : 'border-concrete')}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold">{i.supplier}, invoice {i.number}</p>
                    <p className="text-sm text-ink-soft">{[i.project, i.po ?? 'no purchase order', `dated ${formatDate(i.date)}`, `due ${formatDate(i.due)}`].join(', ')}</p>
                </div>
                <div className="text-right">
                    <p className="text-lg font-bold tabular-nums">{formatRand(i.total)}</p>
                    <p className="text-xs text-ink-soft tabular-nums">{formatRand(i.subtotal)} + VAT {formatRand(i.vat)}</p>
                </div>
            </div>
            <p className={cn('mt-2 flex items-center gap-1.5 text-sm font-semibold', i.status === 'exception' ? 'text-brick' : i.status === 'rejected' ? 'text-brick' : 'text-line-deep')}>
                {i.status === 'exception' ? <AlertTriangle className="size-4" /> : <Check className="size-4" />} {LABEL[i.status]}{i.approver && ` by ${i.approver}`}
            </p>
            {i.issues.length > 0 && <ul className="mt-1 list-disc pl-5 text-sm">{i.issues.map((x) => <li key={x}>{x}</li>)}</ul>}
            {i.override && <p className="mt-1 text-sm"><span className="text-ink-soft">{i.status === 'rejected' ? 'Reason: ' : 'Override: '}</span>{i.override}</p>}
            {['matched', 'exception'].includes(i.status) && (
                <div className="mt-3 flex flex-wrap items-center gap-2 border-t border-concrete pt-3">
                    <input value={reason} onChange={(e) => setReason(e.target.value)} placeholder={i.status === 'exception' ? 'Reason for approving despite the match failure' : 'Reason (needed to reject)'} className="h-9 min-w-64 flex-1 rounded-[var(--radius-control)] border border-concrete px-3 text-sm" aria-label="Reason" />
                    {i.mine ? <span className="text-sm text-ink-soft">You captured this, so someone else must approve it.</span> : (
                        (i.status === 'matched' || canOverride) && <Button size="sm" onClick={() => act('approve', { override_reason: reason || null })}>{i.status === 'exception' ? 'Approve with override' : 'Approve for payment'}</Button>
                    )}
                    {i.status === 'exception' && <Button size="sm" variant="secondary" onClick={() => act('rematch')}>Check again</Button>}
                    <Button size="sm" variant="ghost" className="text-brick" onClick={() => (reason ? act('reject', { reason }) : alert('Give a reason for rejecting.'))}>Reject</Button>
                </div>
            )}
        </li>
    );
}

function Capture({ suppliers, orders, projects, budgetLines, onDone }: Omit<Props, 'invoices' | 'filter' | 'canOverride'> & { onDone: () => void }) {
    const form = useForm<{ supplier: string; purchase_order: string; project: string; budget_line_id: string; invoice_number: string; invoice_date: string; due_date: string; subtotal: string; vat: string; total: string; file: File | null }>({
        supplier: '', purchase_order: '', project: '', budget_line_id: '', invoice_number: '', invoice_date: '', due_date: '', subtotal: '', vat: '', total: '', file: null,
    });
    const bind = (k: 'invoice_number' | 'invoice_date' | 'due_date' | 'subtotal' | 'vat' | 'total') => ({ name: k, value: form.data[k], onChange: (e: { target: { value: string } }) => form.setData(k, e.target.value), error: form.errors[k] });
    const supplierOrders = orders.filter((o) => o.supplier === form.data.supplier);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, purchase_order: d.purchase_order || null, project: d.project || null, budget_line_id: d.budget_line_id || null }));
        form.post('/invoices', { preserveScroll: true, forceFormData: true, onSuccess: onDone });
    }

    function fillVat() {
        const sub = Number(form.data.subtotal || 0);
        const vat = Math.round(sub * 15) / 100;
        form.setData((d) => ({ ...d, vat: vat.toFixed(2), total: (sub + vat).toFixed(2) }));
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField label="Supplier" name="supplier" value={form.data.supplier} onChange={(v) => form.setData((d) => ({ ...d, supplier: v, purchase_order: '' }))} options={suppliers} placeholder="Choose" error={form.errors.supplier} />
                <SelectField label="Purchase order" name="purchase_order" value={form.data.purchase_order} onChange={(v) => form.setData('purchase_order', v)} options={supplierOrders} placeholder={supplierOrders.length ? 'Choose' : 'No open orders for this supplier'} />
                <Field label="Supplier's invoice number" {...bind('invoice_number')} />
            </div>
            {!form.data.purchase_order && (
                <div className="grid gap-4 sm:grid-cols-2">
                    <SelectField label="Project" name="project" value={form.data.project} onChange={(v) => form.setData((d) => ({ ...d, project: v, budget_line_id: '' }))} options={projects} placeholder="Choose" error={form.errors.project} />
                    <SelectField label="Cost code" name="budget_line_id" value={form.data.budget_line_id} onChange={(v) => form.setData('budget_line_id', v)} options={budgetLines.filter((l) => l.project === form.data.project)} placeholder="Choose" error={form.errors.budget_line_id} />
                </div>
            )}
            <div className="grid gap-4 sm:grid-cols-5">
                <Field label="Invoice date" type="date" {...bind('invoice_date')} />
                <Field label="Due date" type="date" {...bind('due_date')} />
                <Field label="Amount excl. VAT" type="number" step="0.01" {...bind('subtotal')} onBlur={fillVat} />
                <Field label="VAT" type="number" step="0.01" {...bind('vat')} />
                <Field label="Total" type="number" step="0.01" {...bind('total')} />
            </div>
            <input type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} aria-label="Invoice copy" className="text-sm" />
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>Capture and match</Button>
                <Button type="button" variant="secondary" onClick={onDone}>Cancel</Button>
            </div>
        </form>
    );
}

Invoices.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
