import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatRand, PageHeader, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Row {
    id: string;
    name: string;
    description: string | null;
    location: string;
    status: string;
    statusLabel: string;
    price: string | null;
    openChecks: number;
    issues: number;
    project: string | null;
}

export default function LandIndex({ parcels, statuses, provinces, filter, canManage }: { parcels: Row[]; statuses: Option[]; provinces: Option[]; filter: string | null; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const form = useForm({ name: '', property_description: '', province: '', town: '', size_m2: '', asking_price: '', seller_name: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, province: d.province || null, size_m2: d.size_m2 || null, asking_price: d.asking_price || null }));
        form.post('/land');
    }

    return (
        <>
            <Head title="Land" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title="Land"
                    description="Sites being considered, negotiated and acquired. Only proceed when the land works financially and legally."
                    action={canManage && <Button onClick={() => setAdding(!adding)}>Add land</Button>}
                />

                {adding && (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="e.g. Ballito ridge site" />
                            <Field label="Property description" name="property_description" value={form.data.property_description} onChange={(e) => form.setData('property_description', e.target.value)} placeholder="e.g. Erf 1234 Ballito" />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-5">
                            <SelectField label="Province" name="province" value={form.data.province} onChange={(v) => form.setData('province', v)} options={provinces} placeholder="Choose" />
                            <Field label="Town" name="town" value={form.data.town} onChange={(e) => form.setData('town', e.target.value)} />
                            <Field label="Size (m²)" name="size_m2" type="number" value={form.data.size_m2} onChange={(e) => form.setData('size_m2', e.target.value)} />
                            <Field label="Asking price (R)" name="asking_price" type="number" value={form.data.asking_price} onChange={(e) => form.setData('asking_price', e.target.value)} />
                            <Field label="Seller" name="seller_name" value={form.data.seller_name} onChange={(e) => form.setData('seller_name', e.target.value)} />
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>Add and start due diligence</Button>
                            <Button type="button" variant="secondary" onClick={() => setAdding(false)}>Cancel</Button>
                        </div>
                    </form>
                )}

                <div className="flex flex-wrap gap-1.5">
                    <button onClick={() => router.get('/land')} className={cn('rounded-full border px-3 py-1 text-sm', !filter ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>All</button>
                    {statuses.map((s) => (
                        <button key={s.key} onClick={() => router.get('/land', { status: s.key })} className={cn('rounded-full border px-3 py-1 text-sm', filter === s.key ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>
                            {s.label}
                        </button>
                    ))}
                </div>

                {parcels.length === 0 ? (
                    <p className="text-ink-soft">No land here yet.</p>
                ) : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead>
                                <tr><th>Site</th><th>Status</th><th>Due diligence</th><th className="text-right">Price</th><th>Project</th></tr>
                            </thead>
                            <tbody>
                                {parcels.map((p) => (
                                    <tr key={p.id} className="hover:bg-plaster">
                                        <td>
                                            <Link href={`/land/${p.id}`} className="font-semibold hover:underline">{p.name}</Link>
                                            <p className="text-ink-soft">{[p.description, p.location].filter(Boolean).join(', ')}</p>
                                        </td>
                                        <td>{p.statusLabel}</td>
                                        <td>
                                            {p.issues > 0 ? (
                                                <span className="font-semibold text-brick">{p.issues === 1 ? '1 issue' : `${p.issues} issues`}</span>
                                            ) : p.openChecks > 0 ? (
                                                <span className="text-ink-soft">{p.openChecks} checks to do</span>
                                            ) : (
                                                <span className="text-line-deep">Complete</span>
                                            )}
                                        </td>
                                        <td className="text-right tabular-nums">{formatRand(p.price)}</td>
                                        <td>{p.project ?? <span className="text-ink-soft">Not linked</span>}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

LandIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
