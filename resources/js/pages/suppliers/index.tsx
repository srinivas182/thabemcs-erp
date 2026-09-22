import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { PageHeader, Pager, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

type Option = { key: string; label: string };

interface Row {
    id: string;
    name: string;
    type: string;
    cidb: string | null;
    bbbee: string | null;
    status: string;
    compliant: boolean;
    expiring: number;
    problems: number;
}

export default function SuppliersIndex({ suppliers, types, filters, canManage }: { suppliers: Paginated<Row>; types: Option[]; filters: { type: string | null; q: string }; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const [q, setQ] = useState(filters.q);
    const form = useForm({ name: '', type: 'contractor', registration_number: '', contact_name: '', email: '', phone: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.post('/suppliers');
    }

    return (
        <>
            <Head title="Contractors and suppliers" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title="Contractors and suppliers"
                    description="Everyone the company buys from. Suppliers with missing or expired compliance documents cannot be appointed or paid."
                    action={canManage && <Button onClick={() => setAdding(!adding)}>Add supplier</Button>}
                />

                {adding && (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Registered name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                            <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                            <Field label="CIPC registration number" name="registration_number" value={form.data.registration_number} onChange={(e) => form.setData('registration_number', e.target.value)} error={form.errors.registration_number} placeholder="2015/123456/07" />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Contact person" name="contact_name" value={form.data.contact_name} onChange={(e) => form.setData('contact_name', e.target.value)} />
                            <Field label="Email" name="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                            <Field label="Phone" name="phone" type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>Add supplier</Button>
                            <Button type="button" variant="secondary" onClick={() => setAdding(false)}>Cancel</Button>
                        </div>
                    </form>
                )}

                <div className="flex flex-wrap items-center gap-3">
                    <form onSubmit={(e) => { e.preventDefault(); router.get('/suppliers', { ...filters, q }, { preserveState: true }); }} className="flex-1">
                        <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search by name or registration number" className="h-10 w-full max-w-md rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm focus:border-line focus:outline-none" />
                    </form>
                    <select aria-label="Type" className="h-10 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm" value={filters.type ?? ''} onChange={(e) => router.get('/suppliers', { ...filters, type: e.target.value || undefined })}>
                        <option value="">All types</option>
                        {types.map((t) => (<option key={t.key} value={t.key}>{t.label}</option>))}
                    </select>
                </div>

                {suppliers.data.length === 0 ? (
                    <p className="text-ink-soft">No suppliers found.</p>
                ) : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Supplier</th><th>CIDB</th><th>B-BBEE</th><th>Compliance</th></tr></thead>
                            <tbody>
                                {suppliers.data.map((s) => (
                                    <tr key={s.id} className={cn('hover:bg-plaster', s.status !== 'active' && 'opacity-60')}>
                                        <td>
                                            <Link href={`/suppliers/${s.id}`} className="font-semibold hover:underline">{s.name}</Link>
                                            <p className="text-ink-soft">{s.type}{s.status !== 'active' && ', suspended'}</p>
                                        </td>
                                        <td>{s.cidb ?? <span className="text-ink-soft">None</span>}</td>
                                        <td>{s.bbbee ? (s.bbbee === 'non_compliant' ? 'Non-compliant' : `Level ${s.bbbee}`) : <span className="text-ink-soft">Unknown</span>}</td>
                                        <td>
                                            {s.compliant ? (
                                                <span className="font-semibold text-line-deep">Compliant{s.expiring > 0 && <span className="font-normal text-ink">, {s.expiring} expiring soon</span>}</span>
                                            ) : (
                                                <span className="font-semibold text-brick">Blocked: {s.problems} {s.problems === 1 ? 'document' : 'documents'} missing or expired</span>
                                            )}
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager prev={suppliers.prev_page_url} next={suppliers.next_page_url} page={suppliers.current_page} last={suppliers.last_page} />
            </div>
        </>
    );
}

SuppliersIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
