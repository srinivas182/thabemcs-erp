import { Head, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, PageHeader, Pager, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Tenant { id: string; name: string; entityType: string; email: string | null; phone: string | null; employer: string | null; status: string; fica: boolean; credit: boolean; screened: string | null; notes: string | null }
interface Props {
    tenants: { data: Tenant[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    filters: { status: string | null; q: string | null };
    canManage: boolean;
}

const STATUSES = [
    { key: 'applicant', label: 'Applicant' }, { key: 'approved', label: 'Approved' }, { key: 'current', label: 'Current tenant' },
    { key: 'former', label: 'Former tenant' }, { key: 'declined', label: 'Declined' },
];
const ENTITY = [{ key: 'individual', label: 'Individual' }, { key: 'company', label: 'Company' }, { key: 'trust', label: 'Trust' }];

export default function Tenants({ tenants, filters, canManage }: Props) {
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState(filters.q ?? '');

    return (
        <>
            <Head title="Tenants" />
            <div className="mx-auto grid max-w-5xl gap-5">
                <PageHeader title="Tenants" description="Applicants and tenants. FICA and a credit check before approving anyone."
                    action={canManage ? <Button onClick={() => setAdding(!adding)}>Add a tenant</Button> : undefined} />

                <div className="flex flex-wrap items-end gap-3">
                    <form onSubmit={(e) => { e.preventDefault(); router.get('/rentals/tenants', { ...filters, q: search || undefined }, { preserveState: true }); }} className="w-64">
                        <Field label="Search" name="q" value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Name or email" />
                    </form>
                    <div className="w-52"><SelectField label="Status" name="status" value={filters.status ?? ''} onChange={(v) => router.get('/rentals/tenants', { ...filters, status: v || undefined })} options={STATUSES} placeholder="All" /></div>
                </div>

                {adding && canManage && <TenantForm onDone={() => setAdding(false)} />}

                <ul className="grid gap-2">
                    {tenants.data.map((t) => (
                        <li key={t.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-3">
                            <div className="flex flex-wrap items-start justify-between gap-3">
                                <div>
                                    <p className="font-semibold">{t.name} <span className="text-sm font-normal text-ink-soft">({ENTITY.find((e) => e.key === t.entityType)?.label})</span></p>
                                    <p className="text-sm text-ink-soft">{[t.email, t.phone, t.employer].filter(Boolean).join(' · ')}</p>
                                    {t.notes && <p className="mt-1 text-sm">{t.notes}</p>}
                                </div>
                                <div className="text-right text-sm">
                                    <span className="rounded-full bg-concrete px-2 py-0.5 text-xs font-medium">{STATUSES.find((s) => s.key === t.status)?.label}</span>
                                    <p className={cn('mt-1', t.fica && t.credit ? 'text-line-deep' : 'text-brick')}>
                                        {t.fica ? 'FICA ✓' : 'FICA outstanding'} · {t.credit ? 'credit checked' : 'no credit check'}
                                        {t.screened && <span className="block text-xs text-ink-soft">screened {formatDate(t.screened)}</span>}
                                    </p>
                                </div>
                            </div>
                            {canManage && <Screening tenant={t} />}
                        </li>
                    ))}
                    {tenants.data.length === 0 && <li className="text-ink-soft">No tenants yet.</li>}
                </ul>
                <Pager prev={tenants.prev_page_url} next={tenants.next_page_url} page={tenants.current_page} last={tenants.last_page} />
            </div>
        </>
    );
}

function Screening({ tenant }: { tenant: Tenant }) {
    const form = useForm({ fica_verified: tenant.fica, credit_checked: tenant.credit, status: tenant.status });
    const changed = form.data.fica_verified !== tenant.fica || form.data.credit_checked !== tenant.credit || form.data.status !== tenant.status;
    return (
        <div className="mt-3 flex flex-wrap items-end gap-3 border-t border-concrete pt-3 text-sm">
            <label className="flex items-center gap-1.5"><input type="checkbox" className="accent-line" checked={form.data.fica_verified} onChange={(e) => form.setData('fica_verified', e.target.checked)} /> FICA verified</label>
            <label className="flex items-center gap-1.5"><input type="checkbox" className="accent-line" checked={form.data.credit_checked} onChange={(e) => form.setData('credit_checked', e.target.checked)} /> Credit checked</label>
            <div className="w-48"><SelectField label="" name={`s${tenant.id}`} value={form.data.status} onChange={(v) => form.setData('status', v)} options={STATUSES} /></div>
            {changed && <Button size="sm" onClick={() => form.post(`/rentals/tenants/${tenant.id}/screen`, { preserveScroll: true })}>Save screening</Button>}
        </div>
    );
}

function TenantForm({ onDone }: { onDone: () => void }) {
    const form = useForm({ name: '', entity_type: 'individual', id_number: '', email: '', phone: '', employer: '', status: 'applicant', accounting_ref: '', notes: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/rentals/tenants', { preserveScroll: true, onSuccess: () => { form.reset(); onDone(); } });
    }
    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                <SelectField label="Renting as" name="entity_type" value={form.data.entity_type} onChange={(v) => form.setData('entity_type', v)} options={ENTITY} />
                <Field label="ID or registration number" name="id_number" value={form.data.id_number} onChange={(e) => form.setData('id_number', e.target.value)} />
                <SelectField label="Status" name="status" value={form.data.status} onChange={(v) => form.setData('status', v)} options={STATUSES} />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Email" name="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} />
                <Field label="Phone" name="phone" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                <Field label="Employer" name="employer" value={form.data.employer} onChange={(e) => form.setData('employer', e.target.value)} />
                <Field label="Customer ID in accounting" name="accounting_ref" value={form.data.accounting_ref} onChange={(e) => form.setData('accounting_ref', e.target.value)} />
            </div>
            <Field label="Notes" name="notes" value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} />
            <p className="text-xs text-ink-soft">ID and registration numbers are stored encrypted, in line with POPIA.</p>
            <div><Button type="submit" disabled={form.processing}>Add tenant</Button></div>
        </form>
    );
}

Tenants.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
