import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { ApprovalTrail, type ApprovalTrailData } from '@/components/approval-trail';
import { formatDate, formatDateTime, formatRand, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Props {
    run: { id: string; reference: string; payOn: string; status: string; total: number; by: string; paidAt: string | null };
    invoices: { id: string; number: string; supplier: string; project: string; due: string; total: number; status: string }[];
    approval: ApprovalTrailData | null;
}

export default function PaymentRun({ run, invoices, approval }: Props) {
    return (
        <>
            <Head title={run.reference} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/payment-runs" className="hover:underline">Payment runs</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{run.reference}: {formatRand(run.total)}</h1>
                        <p className="text-ink-soft">Pay on {formatDate(run.payOn)}, prepared by {run.by}{run.paidAt && `, paid ${formatDateTime(run.paidAt)}`}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        {run.status === 'draft' && <Button onClick={() => router.post(`/payment-runs/${run.id}/submit`)}>Send for approval</Button>}
                        {['approved', 'paid'].includes(run.status) && <Button variant="secondary" asChild><a href={`/payment-runs/${run.id}/export`}>Download payment schedule (CSV)</a></Button>}
                        {run.status === 'approved' && <Button onClick={() => window.confirm('Confirm the bank payments have been made?') && router.post(`/payment-runs/${run.id}/paid`)}>Mark as paid</Button>}
                    </div>
                </header>
                <div className="grid gap-6 lg:grid-cols-[1fr_280px]">
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Supplier</th><th>Invoice</th><th>Due</th><th className="text-right">Amount</th></tr></thead>
                            <tbody>{invoices.map((i) => (<tr key={i.id}><td>{i.supplier}<p className="text-ink-soft">{i.project}</p></td><td>{i.number}</td><td>{formatDate(i.due)}</td><td className="text-right tabular-nums">{formatRand(i.total)}</td></tr>))}</tbody>
                        </table>
                    </div>
                    {approval && <ApprovalTrail approval={approval} />}
                </div>
            </div>
        </>
    );
}

PaymentRun.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
