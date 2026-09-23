import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { BadgeCheck, Download } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatDateTime, formatRand, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface ComplianceRow {
    type: string;
    label: string;
    block: boolean;
    state: 'valid' | 'expiring' | 'expired' | 'missing';
    reference: string | null;
    expiresOn: string | null;
    daysLeft: number | null;
    verified: boolean;
    documentId: string | null;
    versionId: number | null;
}

interface Props {
    supplier: {
        id: string; name: string; trading_name: string | null; type: string; typeLabel: string; registration_number: string | null; vat_number: string | null;
        cidb_crs_number: string | null; cidb_grade: number | null; cidb_class: string | null; bbbee_level: string | null; contact_name: string | null;
        email: string | null; phone: string | null; province: string | null; status: string; notes: string | null; cidbLimit: number | null;
    };
    compliance: ComplianceRow[];
    blockers: string[];
    ratings: { id: number; project: string | null; quality: number; timeliness: number; safety: number; average: number; comment: string | null; by: string | null; at: string }[];
    documentTypes: Option[];
    types: Option[];
    provinces: Option[];
    can: { manage: boolean; rate: boolean };
}

const STATE: Record<ComplianceRow['state'], { label: string; style: string }> = {
    valid: { label: 'Valid', style: 'bg-line-wash text-line-deep' },
    expiring: { label: 'Expiring', style: 'bg-hivis-wash text-ink' },
    expired: { label: 'Expired', style: 'bg-brick text-white' },
    missing: { label: 'Missing', style: 'bg-brick-wash text-brick' },
};

export default function SupplierShow({ supplier, compliance, blockers, ratings, documentTypes, types, provinces, can }: Props) {
    const [editing, setEditing] = useState(false);
    const [value, setValue] = useState('');
    const cidbOk = (() => {
        const v = Number(value);
        if (!value || !['contractor', 'subcontractor'].includes(supplier.type)) return null;
        if (supplier.cidb_grade === null) return false;
        return supplier.cidbLimit === null || v <= supplier.cidbLimit;
    })();

    return (
        <>
            <Head title={supplier.name} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/suppliers" className="hover:underline">Contractors and suppliers</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{supplier.name}</h1>
                        <p className="text-ink-soft">
                            {[supplier.typeLabel, supplier.registration_number, supplier.cidb_grade && `CIDB ${supplier.cidb_grade}${supplier.cidb_class ?? ''}`, supplier.bbbee_level && (supplier.bbbee_level === 'non_compliant' ? 'B-BBEE non-compliant' : `B-BBEE level ${supplier.bbbee_level}`)].filter(Boolean).join(', ')}
                        </p>
                        <p className="text-sm text-ink-soft">{[supplier.contact_name, supplier.email, supplier.phone].filter(Boolean).join(', ')}</p>
                    </div>
                    {can.manage && (
                        <div className="flex gap-2">
                            <Button variant="secondary" onClick={() => setEditing(!editing)}>Edit details</Button>
                            <Button variant={supplier.status === 'active' ? 'ghost' : 'secondary'} className={supplier.status === 'active' ? 'text-brick' : ''}
                                onClick={() => {
                                    const suspend = supplier.status === 'active';
                                    const reason = suspend ? window.prompt('Reason for suspending this supplier?') : '';
                                    if (suspend && reason === null) return;
                                    router.patch(`/suppliers/${supplier.id}/status`, { status: suspend ? 'suspended' : 'active', reason }, { preserveScroll: true });
                                }}>
                                {supplier.status === 'active' ? 'Suspend' : 'Reactivate'}
                            </Button>
                        </div>
                    )}
                </header>

                <div className={cn('rounded-[var(--radius-panel)] px-4 py-3', blockers.length ? 'bg-brick-wash text-brick' : 'bg-line-wash text-line-deep')}>
                    {blockers.length ? (
                        <>
                            <p className="font-semibold">Cannot be appointed or paid</p>
                            <ul className="mt-1 list-disc pl-5 text-sm">{blockers.map((b) => (<li key={b} className="first-letter:uppercase">{b}</li>))}</ul>
                        </>
                    ) : (
                        <p className="font-semibold">Compliant: can be appointed and paid</p>
                    )}
                </div>

                {editing && <DetailsForm supplier={supplier} types={types} provinces={provinces} onDone={() => setEditing(false)} />}

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Compliance documents</h2>
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {compliance.map((c) => (
                            <li key={c.type} className="flex flex-wrap items-center justify-between gap-3 p-3">
                                <div className="min-w-0">
                                    <p className="font-medium">{c.label} {!c.block && <span className="text-sm font-normal text-ink-soft">(recommended)</span>}</p>
                                    <p className="text-sm text-ink-soft">
                                        {[c.reference, c.expiresOn && `expires ${formatDate(c.expiresOn)}`, c.daysLeft !== null && c.daysLeft >= 0 && c.daysLeft <= 30 && `${c.daysLeft} days left`].filter(Boolean).join(', ')}
                                        {c.verified && <span className="ml-1 inline-flex items-center gap-0.5 text-line-deep"><BadgeCheck className="size-3.5" /> verified</span>}
                                    </p>
                                </div>
                                <div className="flex items-center gap-2">
                                    {c.documentId && c.versionId && (
                                        <a href={`/documents/${c.documentId}/versions/${c.versionId}/download`} className="inline-flex items-center gap-1 text-sm font-medium text-line hover:underline">
                                            <Download className="size-3.5" /> Copy
                                        </a>
                                    )}
                                    <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold', STATE[c.state].style)}>{STATE[c.state].label}</span>
                                </div>
                            </li>
                        ))}
                    </ul>
                    {can.manage && <AddDocument supplierId={supplier.id} documentTypes={documentTypes} />}
                </section>

                {['contractor', 'subcontractor'].includes(supplier.type) && (
                    <section className="grid gap-2 border-t-2 border-ink pt-4">
                        <h2 className="font-bold">CIDB grade check</h2>
                        <p className="text-sm text-ink-soft">
                            {supplier.cidb_grade ? `Grade ${supplier.cidb_grade} allows contracts ${supplier.cidbLimit === null ? 'of any value' : `up to ${formatRand(supplier.cidbLimit)} (incl. VAT)`}.` : 'No CIDB grading recorded.'}
                        </p>
                        <div className="flex flex-wrap items-center gap-3">
                            <input type="number" value={value} onChange={(e) => setValue(e.target.value)} placeholder="Contract value (R, incl. VAT)" className="h-10 w-64 rounded-[var(--radius-control)] border border-concrete px-3 text-sm" aria-label="Contract value" />
                            {cidbOk === true && <span className="font-semibold text-line-deep">Within their grading</span>}
                            {cidbOk === false && <span className="font-semibold text-brick">Above their grading. Appointing them would not comply with CIDB rules.</span>}
                        </div>
                    </section>
                )}

                <Ratings supplierId={supplier.id} ratings={ratings} canRate={can.rate} />
            </div>
        </>
    );
}

function AddDocument({ supplierId, documentTypes }: { supplierId: string; documentTypes: Option[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ type: string; reference: string; issued_on: string; expires_on: string; file: File | null }>({ type: documentTypes[0]?.key ?? '', reference: '', issued_on: '', expires_on: '', file: null });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, issued_on: d.issued_on || null, expires_on: d.expires_on || null, reference: d.reference || null }));
        form.post(`/suppliers/${supplierId}/documents`, { preserveScroll: true, forceFormData: true, onSuccess: () => { form.reset(); setOpen(false); } });
    }

    if (!open) return <div><Button variant="secondary" onClick={() => setOpen(true)}>Record a compliance document</Button></div>;

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-dashed border-concrete p-4">
            <div className="grid gap-4 sm:grid-cols-4">
                <SelectField label="Document" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={documentTypes} />
                <Field label="Reference / PIN" name="reference" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                <Field label="Issued on" name="issued_on" type="date" value={form.data.issued_on} onChange={(e) => form.setData('issued_on', e.target.value)} error={form.errors.issued_on} />
                <Field label="Expires on" name="expires_on" type="date" value={form.data.expires_on} onChange={(e) => form.setData('expires_on', e.target.value)} error={form.errors.expires_on} />
            </div>
            <div className="grid gap-1.5">
                <label htmlFor="file" className="text-sm font-medium">Copy of the document (PDF or photo)</label>
                <input id="file" type="file" accept=".pdf,.jpg,.jpeg,.png" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm" />
                {form.errors.file && <p className="text-sm text-brick">{form.errors.file}</p>}
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>{form.progress ? `Uploading ${form.progress.percentage}%` : 'Save'}</Button>
                <Button type="button" variant="secondary" onClick={() => setOpen(false)}>Cancel</Button>
            </div>
        </form>
    );
}

function Ratings({ supplierId, ratings, canRate }: { supplierId: string; ratings: Props['ratings']; canRate: boolean }) {
    const form = useForm({ project: '', quality: '4', timeliness: '4', safety: '4', comment: '' });
    const scale = [1, 2, 3, 4, 5].map((n) => ({ key: String(n), label: String(n) }));
    const avg = ratings.length ? (ratings.reduce((s, r) => s + r.average, 0) / ratings.length).toFixed(1) : null;

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, project: d.project || null, quality: Number(d.quality), timeliness: Number(d.timeliness), safety: Number(d.safety) }));
        form.post(`/suppliers/${supplierId}/ratings`, { preserveScroll: true, onSuccess: () => form.reset('comment') });
    }

    return (
        <section className="grid gap-3 border-t-2 border-ink pt-4">
            <h2 className="font-bold">Performance {avg && <span className="font-normal text-ink-soft">(average {avg} out of 5)</span>}</h2>
            {ratings.length === 0 && <p className="text-sm text-ink-soft">No ratings yet.</p>}
            <ul className="grid gap-2">
                {ratings.map((r) => (
                    <li key={r.id} className="text-sm">
                        <span className="font-semibold tabular-nums">{r.average}</span> <span className="text-ink-soft">(quality {r.quality}, time {r.timeliness}, safety {r.safety})</span>
                        {r.project && `, ${r.project}`}{r.comment && `: ${r.comment}`}
                        <span className="text-ink-soft">, {r.by}, {formatDateTime(r.at)}</span>
                    </li>
                ))}
            </ul>
            {canRate && (
                <form onSubmit={submit} className="grid items-end gap-3 sm:grid-cols-[1fr_90px_90px_90px_1.5fr_auto]">
                    <LookupField label="Project" name="project" value={form.data.project} onChange={(v) => form.setData('project', v)} type="projects" placeholder="General" />
                    <SelectField label="Quality" name="quality" value={form.data.quality} onChange={(v) => form.setData('quality', v)} options={scale} />
                    <SelectField label="On time" name="timeliness" value={form.data.timeliness} onChange={(v) => form.setData('timeliness', v)} options={scale} />
                    <SelectField label="Safety" name="safety" value={form.data.safety} onChange={(v) => form.setData('safety', v)} options={scale} />
                    <Field label="Comment" name="comment" value={form.data.comment} onChange={(e) => form.setData('comment', e.target.value)} />
                    <Button type="submit" disabled={form.processing}>Rate</Button>
                </form>
            )}
        </section>
    );
}

function DetailsForm({ supplier, types, provinces, onDone }: { supplier: Props['supplier']; types: Option[]; provinces: Option[]; onDone: () => void }) {
    const form = useForm({
        name: supplier.name, trading_name: supplier.trading_name ?? '', type: supplier.type, registration_number: supplier.registration_number ?? '',
        vat_number: supplier.vat_number ?? '', cidb_crs_number: supplier.cidb_crs_number ?? '', cidb_grade: supplier.cidb_grade ? String(supplier.cidb_grade) : '',
        cidb_class: supplier.cidb_class ?? '', bbbee_level: supplier.bbbee_level ?? '', contact_name: supplier.contact_name ?? '', email: supplier.email ?? '',
        phone: supplier.phone ?? '', province: supplier.province ?? '', notes: supplier.notes ?? '',
    });
    type Key = keyof typeof form.data;
    const bind = (k: Key) => ({ name: k, value: form.data[k], onChange: (e: { target: { value: string } }) => form.setData(k, e.target.value), error: form.errors[k] });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.put(`/suppliers/${supplier.id}`, { preserveScroll: true, onSuccess: onDone });
    }

    const levels = [...['1', '2', '3', '4', '5', '6', '7', '8'].map((l) => ({ key: l, label: `Level ${l}` })), { key: 'non_compliant', label: 'Non-compliant' }];
    const grades = [1, 2, 3, 4, 5, 6, 7, 8, 9].map((g) => ({ key: String(g), label: String(g) }));

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Registered name" {...bind('name')} />
                <Field label="Trading name" {...bind('trading_name')} />
                <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="CIPC registration number" {...bind('registration_number')} />
                <Field label="VAT number" {...bind('vat_number')} />
                <SelectField label="B-BBEE level" name="bbbee_level" value={form.data.bbbee_level} onChange={(v) => form.setData('bbbee_level', v)} options={levels} placeholder="Unknown" />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="CIDB CRS number" {...bind('cidb_crs_number')} />
                <SelectField label="CIDB grade" name="cidb_grade" value={form.data.cidb_grade} onChange={(v) => form.setData('cidb_grade', v)} options={grades} placeholder="Not graded" />
                <Field label="CIDB class of work" {...bind('cidb_class')} placeholder="e.g. GB, CE, EB" />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <Field label="Contact person" {...bind('contact_name')} />
                <Field label="Email" type="email" {...bind('email')} />
                <Field label="Phone" type="tel" {...bind('phone')} />
                <SelectField label="Province" name="province" value={form.data.province} onChange={(v) => form.setData('province', v)} options={provinces} placeholder="Choose" />
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>Save</Button>
                <Button type="button" variant="secondary" onClick={onDone}>Cancel</Button>
            </div>
        </form>
    );
}

SupplierShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
