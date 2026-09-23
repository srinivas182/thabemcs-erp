import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatDateTime, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Parcel {
    id: string;
    name: string;
    property_description: string | null;
    title_deed_number: string | null;
    province: string | null;
    town: string | null;
    size_m2: string | null;
    current_zoning: string | null;
    seller_name: string | null;
    asking_price: string | null;
    offer_price: string | null;
    status: string;
    offer_date: string | null;
    acceptance_date: string | null;
    transfer_date: string | null;
    notes: string | null;
    project: { id: string; name: string } | null;
}

interface Check {
    id: number;
    title: string;
    required: boolean;
    result: 'pending' | 'clear' | 'issue' | 'not_applicable';
    notes: string | null;
    checkedBy: string | null;
    checkedAt: string | null;
}

interface Props {
    parcel: Parcel;
    checks: Check[];
    outstanding: string[];
    statuses: Option[];
    provinces: Option[];
    canManage: boolean;
}

const RESULTS: { key: Check['result']; label: string; style: string }[] = [
    { key: 'clear', label: 'Clear', style: 'bg-line text-white border-line' },
    { key: 'issue', label: 'Issue', style: 'bg-brick text-white border-brick' },
    { key: 'not_applicable', label: 'N/A', style: 'bg-ink-soft text-white border-ink-soft' },
    { key: 'pending', label: 'To do', style: 'bg-surface text-ink border-ink' },
];

export default function LandShow({ parcel, checks, outstanding, statuses, provinces, canManage }: Props) {
    const [editing, setEditing] = useState(false);
    const done = checks.filter((c) => c.result !== 'pending').length;

    return (
        <>
            <Head title={parcel.name} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/land" className="hover:underline">Land</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{parcel.name}</h1>
                        <p className="text-ink-soft">{[parcel.property_description, parcel.town, parcel.size_m2 && `${Number(parcel.size_m2).toLocaleString('en-ZA')} m²`].filter(Boolean).join(', ')}</p>
                        {parcel.project && (
                            <p className="mt-1 text-sm">For project <Link href={`/projects/${parcel.project.id}`} className="font-medium text-line hover:underline">{parcel.project.name}</Link></p>
                        )}
                    </div>
                    {canManage && (
                        <div className="flex items-end gap-2">
                            <SelectField label="Status" name="status" value={parcel.status} onChange={(v) => router.patch(`/land/${parcel.id}/status`, { status: v }, { preserveScroll: true })} options={statuses} />
                            <Button variant="secondary" onClick={() => setEditing(!editing)}>Edit details</Button>
                        </div>
                    )}
                </header>

                <p className="text-sm text-ink-soft">
                    {[parcel.offer_date && `Offer made ${formatDate(parcel.offer_date)}`, parcel.acceptance_date && `accepted ${formatDate(parcel.acceptance_date)}`, parcel.transfer_date && `transferred ${formatDate(parcel.transfer_date)}`].filter(Boolean).join(', ')}
                </p>

                {editing && <DetailsForm parcel={parcel} provinces={provinces} onDone={() => setEditing(false)} />}

                <section>
                    <div className="flex flex-wrap items-baseline justify-between gap-2">
                        <h2 className="text-lg font-bold">Due diligence</h2>
                        <p className="text-sm text-ink-soft tabular-nums">{done} of {checks.length} checked</p>
                    </div>
                    {outstanding.length > 0 && (
                        <p className="mt-1 text-sm text-ink-soft">The offer can only be accepted once every required check is clear or not applicable.</p>
                    )}
                    <ul className="mt-3 divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {checks.map((c) => (
                            <CheckRow key={c.id} parcelId={parcel.id} check={c} canManage={canManage} />
                        ))}
                    </ul>
                </section>
            </div>
        </>
    );
}

function CheckRow({ parcelId, check, canManage }: { parcelId: string; check: Check; canManage: boolean }) {
    const [notes, setNotes] = useState(check.notes ?? '');
    const [askNotes, setAskNotes] = useState(false);

    function set(result: Check['result']) {
        if (result === 'issue' && !notes) {
            setAskNotes(true);
            return;
        }
        router.patch(`/land/${parcelId}/checks/${check.id}`, { result, notes: notes || null }, { preserveScroll: true, onSuccess: () => setAskNotes(false) });
    }

    return (
        <li className={cn('grid gap-2 p-3', check.result === 'issue' && 'bg-brick-wash/50')}>
            <div className="flex flex-wrap items-center justify-between gap-3">
                <div className="min-w-0 flex-1">
                    <p>{check.title} {!check.required && <span className="text-sm text-ink-soft">(where applicable)</span>}</p>
                    {check.checkedAt && <p className="text-xs text-ink-soft">{check.checkedBy}, {formatDateTime(check.checkedAt)}</p>}
                    {check.notes && !askNotes && <p className="mt-0.5 text-sm">{check.notes}</p>}
                </div>
                <div className="flex gap-1" role="group" aria-label={`Result for ${check.title}`}>
                    {RESULTS.map((r) => (
                        <button
                            key={r.key}
                            disabled={!canManage}
                            aria-pressed={check.result === r.key}
                            onClick={() => set(r.key)}
                            className={cn('rounded-[var(--radius-control)] border px-2.5 py-1 text-xs font-semibold', check.result === r.key ? r.style : 'border-concrete bg-surface text-ink-soft')}
                        >
                            {r.label}
                        </button>
                    ))}
                </div>
            </div>
            {askNotes && (
                <div className="flex gap-2">
                    <input autoFocus value={notes} onChange={(e) => setNotes(e.target.value)} placeholder="Describe the issue" className="h-9 flex-1 rounded-[var(--radius-control)] border border-concrete px-2 text-sm" />
                    <Button size="sm" variant="danger" onClick={() => set('issue')} disabled={!notes}>Save issue</Button>
                </div>
            )}
        </li>
    );
}

function DetailsForm({ parcel, provinces, onDone }: { parcel: Parcel; provinces: Option[]; onDone: () => void }) {
    const form = useForm({
        name: parcel.name, property_description: parcel.property_description ?? '', title_deed_number: parcel.title_deed_number ?? '',
        province: parcel.province ?? '', town: parcel.town ?? '', size_m2: parcel.size_m2 ?? '', current_zoning: parcel.current_zoning ?? '',
        seller_name: parcel.seller_name ?? '', asking_price: parcel.asking_price ?? '', offer_price: parcel.offer_price ?? '',
        notes: parcel.notes ?? '', project: parcel.project?.id ?? '',
    });
    type Key = keyof typeof form.data;
    const bind = (k: Key) => ({ name: k, value: form.data[k], onChange: (e: { target: { value: string } }) => form.setData(k, e.target.value), error: form.errors[k] });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.put(`/land/${parcel.id}`, { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Name" {...bind('name')} />
                <Field label="Property description" {...bind('property_description')} />
                <Field label="Title deed number" {...bind('title_deed_number')} placeholder="e.g. T12345/2019" />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <SelectField label="Province" name="province" value={form.data.province} onChange={(v) => form.setData('province', v)} options={provinces} placeholder="Choose" />
                <Field label="Town" {...bind('town')} />
                <Field label="Size (m²)" type="number" {...bind('size_m2')} />
                <Field label="Current zoning" {...bind('current_zoning')} />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Seller" {...bind('seller_name')} />
                <Field label="Asking price (R)" type="number" {...bind('asking_price')} />
                <Field label="Offer price (R)" type="number" {...bind('offer_price')} />
                <LookupField label="Project" name="project" value={form.data.project} onChange={(v) => form.setData('project', v)} type="projects" placeholder="Not linked" error={form.errors.project} />
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="secondary" onClick={onDone}>Cancel</Button>
            </div>
        </form>
    );
}

LandShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
