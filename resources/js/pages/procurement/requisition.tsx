import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { ApprovalTrail, type ApprovalTrailData, STATUS_LABEL } from '@/components/approval-trail';
import { formatDate, formatRand, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Quote { id: number; supplier: string; amount: number; reference: string | null; leadTime: number | null; validUntil: string | null; notes: string | null; lowest: boolean; blockers: string[] }
interface Props {
    requisition: { id: string; reference: string; title: string; project: { id: string; name: string }; status: string; total: number; notes: string | null; neededBy: string | null; by: string; awardedQuoteId: number | null; awardReason: string | null; singleSourceReason: string | null };
    lines: { description: string; quantity: number; unit: string; price: number }[];
    quotes: Quote[];
    approval: ApprovalTrailData | null;
    purchaseOrder: string | null;
    suppliers: { key: string; label: string }[];
    threshold: number;
    can: { submit: boolean; procure: boolean };
}

export default function RequisitionShow({ requisition: r, lines, quotes, approval, purchaseOrder, suppliers, threshold, can }: Props) {
    const [choice, setChoice] = useState<number | null>(null);
    const [reason, setReason] = useState('');
    const [singleSource, setSingleSource] = useState('');
    const chosen = quotes.find((q) => q.id === choice);
    const needsThree = chosen && chosen.amount > threshold && quotes.length < 3;
    const quote = useForm<{ supplier: string; amount: string; reference: string; lead_time_days: string; valid_until: string; file: File | null }>({ supplier: '', amount: '', reference: '', lead_time_days: '', valid_until: '', file: null });

    function addQuote(e: FormEvent) {
        e.preventDefault();
        quote.transform((d) => ({ ...d, lead_time_days: d.lead_time_days || null, valid_until: d.valid_until || null }));
        quote.post(`/requisitions/${r.id}/quotes`, { preserveScroll: true, forceFormData: true, onSuccess: () => quote.reset() });
    }

    return (
        <>
            <Head title={r.reference} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/requisitions" className="hover:underline">Requisitions</Link> / <Link href={`/projects/${r.project.id}`} className="hover:underline">{r.project.name}</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{r.reference} {r.title}</h1>
                        <p className="text-ink-soft">{STATUS_LABEL[r.status]}, raised by {r.by}{r.neededBy && `, needed by ${formatDate(r.neededBy)}`}</p>
                    </div>
                    <div className="flex gap-2">
                        {can.submit && <Button onClick={() => router.post(`/requisitions/${r.id}/submit`, {}, { preserveScroll: true })}>Submit for approval</Button>}
                        {purchaseOrder && <Button asChild><Link href={`/purchase-orders/${purchaseOrder}`}>Open purchase order</Link></Button>}
                    </div>
                </header>

                <div className="grid gap-6 lg:grid-cols-[1fr_300px]">
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Item</th><th className="text-right">Qty</th><th className="text-right">Estimate</th></tr></thead>
                            <tbody>
                                {lines.map((l, i) => (<tr key={i}><td>{l.description}</td><td className="text-right tabular-nums">{l.quantity} {l.unit}</td><td className="text-right tabular-nums">{formatRand(l.quantity * l.price)}</td></tr>))}
                                <tr><td className="font-semibold">Estimated total (excl. VAT)</td><td /><td className="text-right font-semibold tabular-nums">{formatRand(r.total)}</td></tr>
                            </tbody>
                        </table>
                    </div>
                    {approval && <ApprovalTrail approval={approval} />}
                </div>

                {['approved', 'awarded'].includes(r.status) && (
                    <section className="grid gap-3">
                        <h2 className="text-lg font-bold">Quotes</h2>
                        <p className="text-sm text-ink-soft">Above {formatRand(threshold)} three quotes are needed, unless only one supplier can do the work. Choosing a quote that is not the cheapest needs a reason.</p>
                        {quotes.length === 0 ? <p className="text-ink-soft">No quotes recorded yet.</p> : (
                            <ul className="grid gap-2">
                                {quotes.map((q) => (
                                    <li key={q.id} className={cn('flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-panel)] border bg-surface p-3', r.awardedQuoteId === q.id ? 'border-line' : choice === q.id ? 'border-ink' : 'border-concrete')}>
                                        <label className="flex min-w-0 items-start gap-3">
                                            {r.status === 'approved' && can.procure && <input type="radio" name="quote" className="mt-1 accent-line" checked={choice === q.id} onChange={() => setChoice(q.id)} disabled={q.blockers.length > 0} />}
                                            <span className="min-w-0">
                                                <span className="block font-semibold">{q.supplier} {q.lowest && <span className="ml-1 rounded-full bg-line-wash px-2 py-0.5 text-xs text-line-deep">Lowest</span>} {r.awardedQuoteId === q.id && <span className="ml-1 rounded-full bg-line px-2 py-0.5 text-xs text-white">Awarded</span>}</span>
                                                <span className="block text-sm text-ink-soft">{[q.reference, q.leadTime !== null && `${q.leadTime} days lead time`, q.validUntil && `valid to ${formatDate(q.validUntil)}`].filter(Boolean).join(', ')}</span>
                                                {q.blockers.length > 0 && <span className="block text-sm text-brick first-letter:uppercase">Cannot be used: {q.blockers.join('; ')}</span>}
                                            </span>
                                        </label>
                                        <span className="text-lg font-bold tabular-nums">{formatRand(q.amount)}</span>
                                    </li>
                                ))}
                            </ul>
                        )}

                        {r.status === 'approved' && can.procure && chosen && (
                            <div className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                                {!chosen.lowest && <Field label="Why this quote and not the cheapest?" name="reason" value={reason} onChange={(e) => setReason(e.target.value)} />}
                                {needsThree && <Field label="Why fewer than three quotes? (only one supplier can do this work)" name="single_source_reason" value={singleSource} onChange={(e) => setSingleSource(e.target.value)} />}
                                <div><Button onClick={() => router.post(`/requisitions/${r.id}/award`, { quote: chosen.id, reason: reason || null, single_source_reason: singleSource || null })}>Award to {chosen.supplier} and draft the purchase order</Button></div>
                            </div>
                        )}

                        {r.status === 'approved' && can.procure && (
                            <form onSubmit={addQuote} className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-dashed border-concrete p-4 sm:grid-cols-[1.4fr_1fr_1fr_110px_150px]">
                                <SelectField label="Supplier" name="supplier" value={quote.data.supplier} onChange={(v) => quote.setData('supplier', v)} options={suppliers} placeholder="Choose" error={quote.errors.supplier} />
                                <Field label="Amount excl. VAT (R)" name="amount" type="number" value={quote.data.amount} onChange={(e) => quote.setData('amount', e.target.value)} error={quote.errors.amount} />
                                <Field label="Quote reference" name="reference" value={quote.data.reference} onChange={(e) => quote.setData('reference', e.target.value)} />
                                <Field label="Lead time (days)" name="lead_time_days" type="number" value={quote.data.lead_time_days} onChange={(e) => quote.setData('lead_time_days', e.target.value)} />
                                <Field label="Valid until" name="valid_until" type="date" value={quote.data.valid_until} onChange={(e) => quote.setData('valid_until', e.target.value)} />
                                <input type="file" aria-label="Quote document" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" onChange={(e) => quote.setData('file', e.target.files?.[0] ?? null)} className="text-sm sm:col-span-4" />
                                <Button type="submit" disabled={quote.processing}>Add quote</Button>
                            </form>
                        )}
                        {r.awardReason && <p className="text-sm"><span className="text-ink-soft">Reason for choice: </span>{r.awardReason}</p>}
                        {r.singleSourceReason && <p className="text-sm"><span className="text-ink-soft">Single source: </span>{r.singleSourceReason}</p>}
                    </section>
                )}
            </div>
        </>
    );
}

RequisitionShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
