import { Head, Link, router } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { STATUS_LABEL } from '@/components/approval-trail';
import { formatDate, formatRand, PageHeader, Pager, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

interface Row { id: string; reference: string; project: string; supplier: string; status: string; total: number; expected: string | null }

export default function Orders({ orders, filter }: { orders: Paginated<Row>; filter: string }) {
    return (
        <>
            <Head title="Purchase orders" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader title="Purchase orders" description="Orders are raised from awarded requisitions, approved by delegation of authority, then issued to the supplier." />
                <div className="flex flex-wrap gap-1.5">
                    {['', 'draft', 'pending_approval', 'approved', 'issued', 'partially_received', 'received'].map((s) => (
                        <button key={s} onClick={() => router.get('/purchase-orders', s ? { status: s } : {})} className={cn('rounded-full border px-3 py-1 text-sm', filter === s ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>{s ? STATUS_LABEL[s] : 'All'}</button>
                    ))}
                </div>
                {orders.data.length === 0 ? <p className="text-ink-soft">No purchase orders.</p> : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Order</th><th>Project</th><th>Status</th><th>Expected</th><th className="text-right">Total incl. VAT</th></tr></thead>
                            <tbody>
                                {orders.data.map((o) => (
                                    <tr key={o.id} className="hover:bg-plaster">
                                        <td><Link href={`/purchase-orders/${o.id}`} className="font-semibold hover:underline">{o.reference}</Link><p className="text-ink-soft">{o.supplier}</p></td>
                                        <td>{o.project}</td><td>{STATUS_LABEL[o.status] ?? o.status}</td><td>{formatDate(o.expected)}</td>
                                        <td className="text-right tabular-nums">{formatRand(o.total)}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager prev={orders.prev_page_url} next={orders.next_page_url} page={orders.current_page} last={orders.last_page} />
            </div>
        </>
    );
}

Orders.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
