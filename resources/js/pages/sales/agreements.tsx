import { Head, Link, router } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatDate, formatRand, PageHeader, Pager, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Row { id: string; reference: string; unit: string; project: string; buyer: string; signed: string; price: number; status: string; registered: string | null; commissionStatus: string }
interface Props {
    agreements: { data: Row[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    filters: { status: string | null };
}

const STATUSES = [
    { key: 'conditional', label: 'Conditions outstanding' }, { key: 'unconditional', label: 'Unconditional' },
    { key: 'registered', label: 'Registered' }, { key: 'lapsed', label: 'Lapsed' }, { key: 'cancelled', label: 'Cancelled' },
];

export default function Agreements({ agreements, filters }: Props) {
    return (
        <>
            <Head title="Sale agreements" />
            <div className="mx-auto grid max-w-6xl gap-5">
                <PageHeader title="Sale agreements" description="Every signed deed of sale, its conditions and where the transfer has reached." />
                <div className="w-64"><SelectField label="Status" name="status" value={filters.status ?? ''} onChange={(v) => router.get('/sales/agreements', { status: v || undefined })} options={STATUSES} placeholder="All" /></div>
                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className={tableClass}>
                        <thead><tr><th>Sale</th><th>Unit</th><th>Buyer</th><th>Signed</th><th className="text-right">Price</th><th>Status</th><th>Commission</th></tr></thead>
                        <tbody>
                            {agreements.data.map((a) => (
                                <tr key={a.id} className="border-t border-concrete">
                                    <td><Link href={`/sales/agreements/${a.id}`} className="font-semibold hover:underline">{a.reference}</Link></td>
                                    <td>{a.project} {a.unit}</td>
                                    <td>{a.buyer}</td>
                                    <td>{formatDate(a.signed)}</td>
                                    <td className="text-right tabular-nums">{formatRand(a.price)}</td>
                                    <td><span className={cn('capitalize', a.status === 'registered' && 'text-line-deep', ['lapsed', 'cancelled'].includes(a.status) && 'text-brick')}>
                                        {STATUSES.find((s) => s.key === a.status)?.label}</span>{a.registered && <span className="block text-xs text-ink-soft">{formatDate(a.registered)}</span>}</td>
                                    <td className="capitalize">{a.commissionStatus}</td>
                                </tr>
                            ))}
                            {agreements.data.length === 0 && <tr><td colSpan={7} className="px-3 py-2 text-ink-soft">No sales yet.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <Pager prev={agreements.prev_page_url} next={agreements.next_page_url} page={agreements.current_page} last={agreements.last_page} />
            </div>
        </>
    );
}

Agreements.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
