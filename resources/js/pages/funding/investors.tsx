import { Head, router, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatRand, PageHeader, SelectField, StatusBadge, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Row {
    id: string;
    name: string;
    type: string;
    registration: string | null;
    contact: string;
    ficaVerifiedAt: string | null;
    projects: number;
    committed: number;
}

const TYPE_LABELS: Record<string, string> = { individual: 'Individual', company: 'Company', trust: 'Trust', fund: 'Fund' };

export default function Investors({ investors, types }: { investors: Row[]; types: string[] }) {
    const [adding, setAdding] = useState(false);
    const form = useForm({ name: '', entity_type: 'individual', registration_number: '', contact_person: '', email: '', phone: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/investors', { preserveScroll: true, onSuccess: () => { form.reset(); setAdding(false); } });
    }

    return (
        <>
            <Head title="Investors" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title="Investors"
                    description="People and entities who invest in the company's developments. Verify identity (FICA) before accepting any money."
                    action={<Button onClick={() => setAdding(!adding)}>Add investor</Button>}
                />

                {adding && (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-[1fr_180px_1fr]">
                            <Field label="Name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} />
                            <SelectField label="Type" name="entity_type" value={form.data.entity_type} onChange={(v) => form.setData('entity_type', v)} options={types.map((t) => ({ key: t, label: TYPE_LABELS[t] ?? t }))} />
                            <Field label={form.data.entity_type === 'individual' ? 'SA ID or passport number' : 'Registration number'} name="registration_number" value={form.data.registration_number} onChange={(e) => form.setData('registration_number', e.target.value)} hint="Stored encrypted. Only the last four characters are shown." />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <Field label="Contact person" name="contact_person" value={form.data.contact_person} onChange={(e) => form.setData('contact_person', e.target.value)} />
                            <Field label="Email" name="email" type="email" value={form.data.email} onChange={(e) => form.setData('email', e.target.value)} error={form.errors.email} />
                            <Field label="Phone" name="phone" type="tel" value={form.data.phone} onChange={(e) => form.setData('phone', e.target.value)} />
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>Save investor</Button>
                            <Button type="button" variant="secondary" onClick={() => setAdding(false)}>Cancel</Button>
                        </div>
                    </form>
                )}

                {investors.length === 0 ? (
                    <p className="text-ink-soft">No investors yet.</p>
                ) : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead>
                                <tr>
                                    <th>Investor</th>
                                    <th>FICA</th>
                                    <th className="text-right">Projects</th>
                                    <th className="text-right">Committed</th>
                                    <th />
                                </tr>
                            </thead>
                            <tbody>
                                {investors.map((i) => (
                                    <tr key={i.id}>
                                        <td>
                                            <p className="font-semibold">{i.name}</p>
                                            <p className="text-ink-soft">
                                                {TYPE_LABELS[i.type]}
                                                {i.registration && `, ${i.registration}`}
                                                {i.contact && `, ${i.contact}`}
                                            </p>
                                        </td>
                                        <td>
                                            <StatusBadge active={i.ficaVerifiedAt !== null} activeLabel={`Verified ${formatDate(i.ficaVerifiedAt)}`} inactiveLabel="Not verified" />
                                        </td>
                                        <td className="text-right tabular-nums">{i.projects}</td>
                                        <td className="text-right tabular-nums">{formatRand(i.committed)}</td>
                                        <td className="text-right">
                                            {!i.ficaVerifiedAt && (
                                                <Button variant="ghost" size="sm" onClick={() => window.confirm(`Confirm FICA documents for ${i.name} have been checked?`) && router.post(`/investors/${i.id}/verify`, {}, { preserveScroll: true })}>
                                                    Mark FICA verified
                                                </Button>
                                            )}
                                        </td>
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

Investors.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
