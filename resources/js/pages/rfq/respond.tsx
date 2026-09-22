import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent } from 'react';
import { BrandMark } from '@/components/brand-mark';
import { formatDate, formatRand } from '@/components/data';
import type { SharedProps } from '@/types';

interface Props {
    token: string; company: string; supplier: string; reference: string; title: string; project: string;
    neededBy: string | null; closesOn: string; open: boolean; declined: boolean; message: string | null;
    lines: { description: string; quantity: number; unit: string }[];
    quote: { amount: number; reference: string | null; leadTime: number | null; validUntil: string | null; notes: string | null } | null;
}

/** Page a supplier reaches from the request-for-quotation email. No sign-in; the link is the key. */
export default function Respond(p: Props) {
    const { flash } = usePage<SharedProps>().props;
    const form = useForm<{ amount: string; reference: string; lead_time_days: string; valid_until: string; notes: string; file: File | null }>({
        amount: p.quote ? String(p.quote.amount) : '', reference: p.quote?.reference ?? '', lead_time_days: p.quote?.leadTime ? String(p.quote.leadTime) : '',
        valid_until: p.quote?.validUntil ?? '', notes: p.quote?.notes ?? '', file: null,
    });
    const vat = Number(form.data.amount || 0) * 0.15;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, lead_time_days: d.lead_time_days || null, valid_until: d.valid_until || null }));
        form.post(`/quote/${p.token}`, { forceFormData: true, preserveScroll: true });
    }

    return (
        <div className="min-h-dvh bg-plaster">
            <Head><title>{`Quote ${p.reference}`}</title><meta name="robots" content="noindex, nofollow" /></Head>
            <header className="border-b border-concrete bg-surface px-5 py-4"><div className="mx-auto max-w-3xl"><BrandMark /></div></header>
            <main className="mx-auto grid max-w-3xl gap-6 px-5 py-8">
                <div>
                    <p className="text-sm text-ink-soft">Request for quotation from {p.company} to {p.supplier}</p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{p.reference}: {p.title}</h1>
                    <p className="text-ink-soft">Project: {p.project}.{p.neededBy && ` Needed by ${formatDate(p.neededBy)}.`} Quotes close on <strong>{formatDate(p.closesOn)}</strong>.</p>
                </div>
                {flash.success && <p role="status" className="rounded-[var(--radius-control)] bg-line-wash px-4 py-3 text-line-deep">{flash.success}</p>}
                {flash.error && <p role="alert" className="rounded-[var(--radius-control)] bg-brick-wash px-4 py-3 text-brick">{flash.error}</p>}
                {p.message && <p className="whitespace-pre-line rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">{p.message}</p>}

                <section className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className="w-full text-sm [&_td]:px-4 [&_td]:py-2.5 [&_th]:px-4 [&_th]:py-2.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                        <thead><tr><th>Item</th><th className="text-right">Quantity</th></tr></thead>
                        <tbody>{p.lines.map((l, i) => <tr key={i}><td>{l.description}</td><td className="text-right tabular-nums">{l.quantity} {l.unit}</td></tr>)}</tbody>
                    </table>
                </section>

                {p.declined ? <p className="text-ink-soft">You told us you will not quote. Thank you.</p> : !p.open ? (
                    <p className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">Quotes for this request have closed.{p.quote && ` Your quote of ${formatRand(p.quote.amount)} excl. VAT was received.`}</p>
                ) : (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <h2 className="text-lg font-bold">{p.quote ? 'Revise your quote' : 'Your quote'}</h2>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Total for all items, excl. VAT (R)" name="amount" type="number" step="0.01" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} hint={form.data.amount ? `${formatRand(vat)} VAT if you are VAT registered` : undefined} />
                            <Field label="Your quote number" name="reference" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                            <Field label="Delivery lead time (days)" name="lead_time_days" type="number" value={form.data.lead_time_days} onChange={(e) => form.setData('lead_time_days', e.target.value)} error={form.errors.lead_time_days} />
                            <Field label="Quote valid until" name="valid_until" type="date" value={form.data.valid_until} onChange={(e) => form.setData('valid_until', e.target.value)} error={form.errors.valid_until} />
                        </div>
                        <label className="grid gap-1.5 text-sm font-medium">Notes (optional)<textarea rows={3} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} className="rounded-[var(--radius-control)] border border-concrete p-3" /></label>
                        <label className="grid gap-1.5 text-sm font-medium">Your quotation document (PDF preferred)<input type="file" accept=".pdf,.jpg,.jpeg,.png,.xlsx,.docx" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm font-normal" /></label>
                        {form.errors.file && <p className="text-sm text-brick">{form.errors.file}</p>}
                        <div className="flex flex-wrap items-center gap-3">
                            <Button type="submit" size="lg" disabled={form.processing}>{p.quote ? 'Update quote' : 'Submit quote'}</Button>
                            {!p.quote && <button type="button" className="text-sm text-ink-soft underline" onClick={() => window.confirm('Tell us you will not be quoting?') && router.post(`/quote/${p.token}/decline`)}>We won't be quoting</button>}
                        </div>
                    </form>
                )}
                <p className="text-xs text-ink-soft">This link is personal to {p.supplier}. The information you submit is used only to evaluate this quotation, in line with POPIA.</p>
            </main>
        </div>
    );
}
