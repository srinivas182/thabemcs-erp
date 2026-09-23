import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, PageHeader, Pager, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Buyer {
    id: string; name: string; entityType: string; email: string | null; phone: string | null; status: string;
    source: string | null; owner: string | null; agent: string | null; fica: boolean; ficaOn: string | null; notes: string | null;
}
interface Props {
    buyers: { data: Buyer[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    filters: { status: string | null; q: string | null };
    canManage: boolean;
}

const STATUSES = [
    { key: 'enquiry', label: 'Enquiry' }, { key: 'qualified', label: 'Qualified' }, { key: 'reserved', label: 'Reserved' },
    { key: 'purchaser', label: 'Purchaser' }, { key: 'lost', label: 'Lost' },
];
const ENTITY = [{ key: 'individual', label: 'Individual' }, { key: 'company', label: 'Company' }, { key: 'trust', label: 'Trust' }];

export default function Buyers({ buyers, filters, canManage }: Props) {
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState(filters.q ?? '');

    return (
        <>
            <Head title="Buyers" />
            <div className="mx-auto grid max-w-6xl gap-5">
                <PageHeader title="Buyers" description="Everyone from first enquiry to registration. FICA must be verified before a sale can go unconditional."
                    action={canManage ? <Button onClick={() => setAdding(!adding)}>Add a buyer</Button> : undefined} />

                <div className="flex flex-wrap items-end gap-3">
                    <form onSubmit={(e) => { e.preventDefault(); router.get('/sales/buyers', { ...filters, q: search || undefined }, { preserveState: true }); }} className="w-64">
                        <Field label="Search" name="q" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Name or email" />
                    </form>
                    <div className="w-52"><SelectField label="Status" name="status" value={filters.status ?? ''} onChange={(v) => router.get('/sales/buyers', { ...filters, status: v || undefined })} options={STATUSES} placeholder="All" /></div>
                </div>

                {adding && canManage && <BuyerForm onDone={() => setAdding(false)} />}

                <ul className="grid gap-2">
                    {buyers.data.map((b) => (
                        <li key={b.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-3">
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{b.name} <span className="text-sm font-normal text-ink-soft">({ENTITY.find((e) => e.key === b.entityType)?.label})</span></p>
                                    <p className="text-sm text-ink-soft">{[b.email, b.phone, b.source, b.agent && `via ${b.agent}`, b.owner && `looked after by ${b.owner}`].filter(Boolean).join(' · ')}</p>
                                    {b.notes && <p className="mt-1 text-sm">{b.notes}</p>}
                                </div>
                                <div className="text-right text-sm">
                                    <span className="rounded-full bg-concrete px-2 py-0.5 text-xs font-medium capitalize">{b.status}</span>
                                    <p className={cn('mt-1', b.fica ? 'text-line-deep' : 'text-brick')}>{b.fica ? `FICA verified ${formatDate(b.ficaOn)}` : 'FICA outstanding'}</p>
                                    {canManage && <button className="text-xs text-ink-soft underline" onClick={() => router.post(`/sales/buyers/${b.id}/fica`, { verified: !b.fica }, { preserveScroll: true })}>{b.fica ? 'Withdraw verification' : 'Mark FICA verified'}</button>}
                                </div>
                            </div>
                        </li>
                    ))}
                    {buyers.data.length === 0 && <li className="text-ink-soft">No buyers yet.</li>}
                </ul>
                <Pager prev={buyers.prev_page_url} next={buyers.next_page_url} page={buyers.current_page} last={buyers.last_page} />
                <p className="text-sm text-ink-soft">Stock and sales live on each project's <Link href="/projects" className="underline">Sales</Link> tab.</p>
            </div>
        </>
    );
}

function BuyerForm({ onDone }: { onDone: () => void }) {
    const form = useForm({ name: '', entity_type: 'individual', id_number: '', email: '', phone: '', address: '', status: 'enquiry', source: '', owner: '', agent_supplier: '', notes: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, owner: d.owner || null, agent_supplier: d.agent_supplier || null }));
        form.post('/sales/buyers', { preserveScroll: true, onSuccess: () => { form.reset(); onDone(); } });
    }
    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                <SelectField label="Buying as" name="entity_type" value={form.data.entity_type} onChange={(v) => form.setData('entity_type', v)} options={ENTITY} />
                <Field label="ID or registration number" name="id_number" value={form.data.id_number} onChange={(e) => form.setData('id_number', e.target.value)} />
                <SelectField label="Status" name="status" value={form.data.status} onChange={(v) => form.setData('status', v)} options={STATUSES} />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Email" name="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <Field label="Phone" name="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                <Field label="How they found us" name="source" value={form.data.source} onChange={(e) => form.setData('source', e.target.value)} placeholder="Show house, website, referral" />
                <LookupField label="Looked after by" name="owner" type="people" value={form.data.owner} onChange={(v) => form.setData('owner', v)} placeholder="Nobody yet" />
            </div>
            <div className="grid gap-4 sm:grid-cols-2">
                <LookupField label="Estate agency" name="agent_supplier" type="suppliers" params={{ types: 'estate_agency' }} value={form.data.agent_supplier} onChange={(v) => form.setData('agent_supplier', v)} placeholder="None" />
                <Field label="Notes" name="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            </div>
            <p className="text-xs text-ink-soft">ID and registration numbers are stored encrypted, in line with POPIA.</p>
            <div><Button type="submit" disabled={form.processing}>Add buyer</Button></div>
        </form>
    );
}

Buyers.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
