import { Head, Link, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatDate, formatRand, PageHeader, Pager, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Row { id: string; reference: string; unit: string; project: string; tenant: string; type: string; starts: string; ends: string | null; monthToMonth: boolean; rent: number; status: string; arrears: number }
interface Props {
    leases: { data: Row[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    filters: { status: string };
    summary: { active: number; vacant: number; monthlyRent: number };
    canManage: boolean;
}

const STATUSES = [{ key: 'active', label: 'Active' }, { key: 'draft', label: 'Draft' }, { key: 'ended', label: 'Ended' }, { key: 'all', label: 'All' }];

export default function Rentals({ leases, filters, summary, canManage }: Props) {
    return (
        <>
            <Head title="Rentals" />
            <div className="mx-auto grid max-w-6xl gap-5">
                <PageHeader title="Rentals" description="Leases, rent and arrears. Rent for the coming month is invoiced automatically, with escalations on each anniversary."
                    action={<div className="flex gap-2"><Button variant="ghost" asChild><Link href="/rentals/maintenance">Maintenance</Link></Button><Button variant="ghost" asChild><Link href="/rentals/tenants">Tenants</Link></Button></div>} />

                <div className="grid grid-cols-3 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete">
                    <Figure label="Active leases" value={String(summary.active)} />
                    <Figure label="Vacant units" value={String(summary.vacant)} />
                    <Figure label="Rent per month" value={formatRand(summary.monthlyRent)} />
                </div>

                <div className="w-52"><SelectField label="Status" name="status" value={filters.status} onChange={(v) => router.get('/rentals', { status: v })} options={STATUSES} /></div>

                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className={tableClass}>
                        <thead><tr><th>Lease</th><th>Unit</th><th>Tenant</th><th>Term</th><th className="text-right">Rent</th><th className="text-right">Arrears</th><th>Status</th></tr></thead>
                        <tbody>
                            {leases.data.map((l) => (
                                <tr key={l.id} className="border-t border-concrete">
                                    <td><Link href={`/rentals/leases/${l.id}`} className="font-semibold hover:underline">{l.reference}</Link></td>
                                    <td>{l.project} {l.unit}<span className="block text-xs capitalize text-ink-soft">{l.type}</span></td>
                                    <td>{l.tenant}</td>
                                    <td>{formatDate(l.starts)}{l.monthToMonth ? ' — month to month' : l.ends ? ` to ${formatDate(l.ends)}` : ''}</td>
                                    <td className="text-right tabular-nums">{formatRand(l.rent)}</td>
                                    <td className={cn('text-right tabular-nums', l.arrears > 0 && 'font-semibold text-brick')}>{l.arrears > 0 ? formatRand(l.arrears) : '—'}</td>
                                    <td className="capitalize">{l.status}</td>
                                </tr>
                            ))}
                            {leases.data.length === 0 && <tr><td colSpan={7} className="px-3 py-2 text-ink-soft">No leases.</td></tr>}
                        </tbody>
                    </table>
                </div>
                <Pager prev={leases.prev_page_url} next={leases.next_page_url} page={leases.current_page} last={leases.last_page} />
                {canManage && <p className="text-sm text-ink-soft">New leases start from a unit on the project's Sales tab: mark the unit as to let, then create the lease.</p>}
            </div>
        </>
    );
}

function Figure({ label, value }: { label: string; value: string }) {
    return <div className="bg-surface p-4"><p className="text-xs text-ink-soft">{label}</p><p className="mt-1 text-2xl font-bold tabular-nums">{value}</p></div>;
}

Rentals.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
