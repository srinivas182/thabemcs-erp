import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatDate, formatRand, PageHeader, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

const LABEL: Record<string, string> = { draft: 'Draft', pending_approval: 'Waiting for approval', approved: 'Approved, ready to pay', paid: 'Paid', rejected: 'Rejected' };

export default function PaymentRuns({ runs, ready }: { runs: { id: string; reference: string; payOn: string; status: string; total: number; invoices: number }[]; ready: { count: number; total: number } }) {
    const form = useForm({ pay_on: new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date()) });
    return (
        <>
            <Head title="Payment runs" />
            <div className="mx-auto grid max-w-5xl gap-6">
                <PageHeader title="Payment runs" description="Approved invoices are paid in batches. Suppliers who are not compliant are left out automatically." />
                <form onSubmit={(e) => { e.preventDefault(); form.post('/payment-runs'); }} className="flex flex-wrap items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                    <p className="flex-1 text-sm">{ready.count} approved {ready.count === 1 ? 'invoice' : 'invoices'} waiting, {formatRand(ready.total)} in total.</p>
                    <Field label="Pay invoices due by" name="pay_on" type="date" value={form.data.pay_on} onChange={(e) => form.setData('pay_on', e.target.value)} error={form.errors.pay_on} />
                    <Button type="submit" disabled={form.processing || ready.count === 0}>Prepare payment run</Button>
                </form>
                {runs.length > 0 && (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Run</th><th>Pay on</th><th>Status</th><th className="text-right">Invoices</th><th className="text-right">Total</th></tr></thead>
                            <tbody>{runs.map((r) => (
                                <tr key={r.id} className="hover:bg-plaster"><td><Link href={`/payment-runs/${r.id}`} className="font-semibold hover:underline">{r.reference}</Link></td><td>{formatDate(r.payOn)}</td><td>{LABEL[r.status] ?? r.status}</td><td className="text-right tabular-nums">{r.invoices}</td><td className="text-right tabular-nums">{formatRand(r.total)}</td></tr>
                            ))}</tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

PaymentRuns.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
