import { Head, useForm, usePage } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent } from 'react';
import { BrandMark } from '@/components/brand-mark';
import { formatDate, formatRand, SelectField } from '@/components/data';
import type { SharedProps } from '@/types';

interface Props {
    token: string;
    lease: { reference: string; unit: string; project: string; tenant: string; rent: number; paymentDay: number; starts: string; ends: string | null; status: string };
    invoices: { reference: string; period: string; due: string; total: number; outstanding: number; status: string }[];
    requests: { reference: string; description: string; status: string; reported: string }[];
    categories: { key: string; label: string }[];
}

/** The tenant's own page: their lease, what is owed, and a way to report something that needs fixing. */
export default function TenantPortal({ token, lease, invoices, requests, categories }: Props) {
    const { flash } = usePage<SharedProps>().props;
    const form = useForm({ category: 'plumbing', description: '', priority: 'normal' });
    const owing = invoices.reduce((sum, i) => sum + i.outstanding, 0);

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/tenant/${token}/requests`, { preserveScroll: true, onSuccess: () => form.reset('description') });
    }

    return (
        <div className="min-h-dvh bg-plaster">
            <Head><title>{`${lease.unit} — your lease`}</title><meta name="robots" content="noindex, nofollow" /></Head>
            <header className="border-b border-concrete bg-surface px-5 py-4"><div className="mx-auto max-w-3xl"><BrandMark /></div></header>
            <main className="mx-auto grid max-w-3xl gap-6 px-5 py-8">
                <div>
                    <p className="text-sm text-ink-soft">{lease.project}</p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{lease.unit}</h1>
                    <p className="text-ink-soft">{lease.tenant} · rent {formatRand(lease.rent)} a month, due on day {lease.paymentDay} · from {formatDate(lease.starts)}{lease.ends ? ` to ${formatDate(lease.ends)}` : ''}</p>
                </div>
                {flash.success && <p role="status" className="rounded-[var(--radius-control)] bg-line-wash px-4 py-3 text-line-deep">{flash.success}</p>}

                <section className="grid gap-2">
                    <h2 className="text-lg font-bold">Your account</h2>
                    <p className={owing > 0 ? 'font-semibold text-brick' : 'text-line-deep'}>{owing > 0 ? `${formatRand(owing)} outstanding` : 'Your account is up to date. Thank you.'}</p>
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr><th>Invoice</th><th>Month</th><th>Due</th><th className="text-right">Total</th><th className="text-right">Outstanding</th></tr></thead>
                            <tbody>
                                {invoices.map((i) => (
                                    <tr key={i.reference}><td>{i.reference}</td><td>{i.period}</td><td>{formatDate(i.due)}</td>
                                        <td className="text-right tabular-nums">{formatRand(i.total)}</td>
                                        <td className="text-right tabular-nums">{i.outstanding > 0 ? formatRand(i.outstanding) : '—'}</td></tr>
                                ))}
                                {invoices.length === 0 && <tr><td colSpan={5} className="text-ink-soft">No invoices yet.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Report something that needs fixing</h2>
                    <form onSubmit={submit} className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-4">
                        <SelectField label="What kind of problem" name="category" value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} />
                        <Field label="What is wrong" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} error={form.errors.description} />
                        <SelectField label="How urgent" name="priority" value={form.data.priority} onChange={(v) => form.setData('priority', v)} options={[{ key: 'urgent', label: 'Urgent (unsafe or no water/power)' }, { key: 'normal', label: 'Normal' }, { key: 'low', label: 'Can wait' }]} />
                        <Button type="submit" disabled={form.processing}>Send</Button>
                    </form>
                    {requests.length > 0 && (
                        <ul className="text-sm">
                            {requests.map((r) => <li key={r.reference}><span className="font-medium">{r.reference}</span> {r.description} — <span className="capitalize">{r.status.replace('_', ' ')}</span>, reported {formatDate(r.reported)}</li>)}
                        </ul>
                    )}
                </section>
                <p className="text-xs text-ink-soft">This link is personal to you; please do not share it. Your information is used only to manage your lease, in line with POPIA.</p>
            </main>
        </div>
    );
}
