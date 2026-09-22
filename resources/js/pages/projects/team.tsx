import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { BadgeCheck } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatRand, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Claim {
    id: number;
    number: string;
    description: string | null;
    amount: number;
    submittedOn: string;
    status: 'submitted' | 'approved' | 'paid' | 'rejected';
    paidOn: string | null;
}

interface Appointment {
    id: string;
    discipline: string;
    firm: string;
    contact: string;
    registrationBody: string | null;
    registrationNumber: string | null;
    verifiedAt: string | null;
    feeBasis: string;
    feePercentage: string | null;
    agreedFee: number | null;
    approvedClaims: number;
    overClaimed: boolean;
    appointedOn: string | null;
    status: string;
    claims: Claim[];
}

interface Props {
    project: { id: string; name: string; code: string };
    appointments: Appointment[];
    disciplines: (Option & { body: string | null })[];
    feeBases: Option[];
    can: { manage: boolean; approveClaims: boolean; markPaid: boolean };
}

const CLAIM_STYLE: Record<Claim['status'], string> = {
    submitted: 'bg-hivis-wash text-ink',
    approved: 'bg-line-wash text-line-deep',
    paid: 'bg-line text-white',
    rejected: 'bg-brick-wash text-brick',
};

export default function Team({ project, appointments, disciplines, feeBases, can }: Props) {
    const [adding, setAdding] = useState(false);

    return (
        <>
            <Head title={`Professional team: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft">
                            <Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link>
                        </p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Professional team</h1>
                        <p className="text-ink-soft">{project.name}. Fees exclude VAT.</p>
                    </div>
                    {can.manage && <Button onClick={() => setAdding(!adding)}>Appoint professional</Button>}
                </header>

                {adding && <NewAppointment projectId={project.id} disciplines={disciplines} feeBases={feeBases} onDone={() => setAdding(false)} />}

                {appointments.length === 0 ? (
                    <p className="text-ink-soft">No professionals appointed yet. A typical team includes an architect, quantity surveyor, engineers, town planner, attorney and a health and safety agent.</p>
                ) : (
                    <ul className="grid gap-4">
                        {appointments.map((a) => (
                            <AppointmentCard key={a.id} appointment={a} can={can} />
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

function AppointmentCard({ appointment: a, can }: { appointment: Appointment; can: Props['can'] }) {
    const [claiming, setClaiming] = useState(false);
    const form = useForm({ claim_number: '', description: '', amount: '', submitted_on: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/appointments/${a.id}/claims`, { preserveScroll: true, onSuccess: () => { form.reset(); setClaiming(false); } });
    }

    const used = a.agreedFee ? Math.min(100, (a.approvedClaims / a.agreedFee) * 100) : null;

    return (
        <li className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-sm text-ink-soft">{a.discipline}</p>
                    <p className="text-lg font-semibold">{a.firm}</p>
                    {a.contact && <p className="text-sm text-ink-soft">{a.contact}</p>}
                    <p className="mt-1 flex flex-wrap items-center gap-2 text-sm">
                        {a.registrationBody ? (
                            a.verifiedAt ? (
                                <span className="inline-flex items-center gap-1 text-line-deep"><BadgeCheck className="size-4" /> {a.registrationBody} {a.registrationNumber}, verified</span>
                            ) : (
                                <>
                                    <span className="text-brick">{a.registrationBody} registration {a.registrationNumber ? `${a.registrationNumber} not yet verified` : 'number missing'}</span>
                                    {can.manage && a.registrationNumber && (
                                        <button className="font-medium text-line hover:underline" onClick={() => window.confirm(`Confirm you have checked ${a.registrationNumber} on the ${a.registrationBody} register?`) && router.post(`/appointments/${a.id}/verify`, {}, { preserveScroll: true })}>Mark verified</button>
                                    )}
                                </>
                            )
                        ) : null}
                    </p>
                </div>
                <div className="text-right text-sm">
                    <p>{a.feeBasis}{a.feePercentage && `: ${Number(a.feePercentage)}%`}</p>
                    <p className="font-semibold tabular-nums">{a.agreedFee !== null ? formatRand(a.agreedFee) : 'Fee not agreed'}</p>
                    <p className="text-ink-soft">{a.appointedOn ? `Appointed ${formatDate(a.appointedOn)}` : 'Proposed'}</p>
                </div>
            </div>

            {used !== null && (
                <div className="mt-3">
                    <div className="h-2 overflow-hidden rounded-full bg-concrete-soft"><div className={cn('h-full', a.overClaimed ? 'bg-brick' : 'bg-line')} style={{ width: `${used}%` }} /></div>
                    <p className={cn('mt-1 text-xs', a.overClaimed ? 'font-semibold text-brick' : 'text-ink-soft')}>
                        {formatRand(a.approvedClaims)} approved of {formatRand(a.agreedFee)}{a.overClaimed && '. Approved claims exceed the agreed fee.'}
                    </p>
                </div>
            )}

            {a.claims.length > 0 && (
                <ul className="mt-3 divide-y divide-concrete border-t border-concrete text-sm">
                    {a.claims.map((c) => (
                        <li key={c.id} className="flex flex-wrap items-center justify-between gap-2 py-2">
                            <span>
                                <span className="font-medium">Claim {c.number}</span>
                                {c.description && `, ${c.description}`}
                                <span className="text-ink-soft">, {formatDate(c.submittedOn)}</span>
                            </span>
                            <span className="flex items-center gap-2">
                                <span className="tabular-nums">{formatRand(c.amount)}</span>
                                <span className={cn('rounded-full px-2 py-0.5 text-xs font-semibold capitalize', CLAIM_STYLE[c.status])}>{c.status}</span>
                                {c.status === 'submitted' && can.approveClaims && (
                                    <>
                                        <button className="font-medium text-line hover:underline" onClick={() => router.patch(`/fee-claims/${c.id}`, { status: 'approved' }, { preserveScroll: true })}>Approve</button>
                                        <button className="text-brick hover:underline" onClick={() => router.patch(`/fee-claims/${c.id}`, { status: 'rejected' }, { preserveScroll: true })}>Reject</button>
                                    </>
                                )}
                                {c.status === 'approved' && can.markPaid && (
                                    <button className="font-medium text-line hover:underline" onClick={() => router.patch(`/fee-claims/${c.id}`, { status: 'paid' }, { preserveScroll: true })}>Mark paid</button>
                                )}
                            </span>
                        </li>
                    ))}
                </ul>
            )}

            {can.manage && !claiming && <button className="mt-2 text-sm font-medium text-line hover:underline" onClick={() => setClaiming(true)}>Record a fee claim</button>}
            {claiming && (
                <form onSubmit={submit} className="mt-3 grid items-end gap-3 border-t border-concrete pt-3 sm:grid-cols-[120px_1fr_160px_160px_auto]">
                    <Field label="Claim no." name="claim_number" value={form.data.claim_number} onChange={(e) => form.setData('claim_number', e.target.value)} error={form.errors.claim_number} />
                    <Field label="Work stage" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} placeholder="e.g. Stage 3 design development" />
                    <Field label="Amount (R)" name="amount" type="number" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
                    <Field label="Submitted on" name="submitted_on" type="date" value={form.data.submitted_on} onChange={(e) => form.setData('submitted_on', e.target.value)} error={form.errors.submitted_on} />
                    <Button type="submit" disabled={form.processing}>Save</Button>
                </form>
            )}
        </li>
    );
}

function NewAppointment({ projectId, disciplines, feeBases, onDone }: { projectId: string; disciplines: Props['disciplines']; feeBases: Option[]; onDone: () => void }) {
    const form = useForm({ discipline: 'architect', firm_name: '', contact_name: '', email: '', phone: '', registration_number: '', fee_basis: 'percentage', fee_percentage: '', agreed_fee: '', appointed_on: '' });
    const body = disciplines.find((d) => d.key === form.data.discipline)?.body;
    type Key = keyof typeof form.data;
    const bind = (k: Key) => ({ name: k, value: form.data[k], onChange: (e: { target: { value: string } }) => form.setData(k, e.target.value), error: form.errors[k] });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.post(`/projects/${projectId}/team`, { preserveScroll: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField label="Discipline" name="discipline" value={form.data.discipline} onChange={(v) => form.setData('discipline', v)} options={disciplines} />
                <Field label="Firm" {...bind('firm_name')} />
                <Field label={body ? `${body} registration number` : 'Registration number'} {...bind('registration_number')} hint={body ? `Check it on the ${body} public register.` : undefined} />
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Contact person" {...bind('contact_name')} />
                <Field label="Email" type="email" {...bind('email')} />
                <Field label="Phone" type="tel" {...bind('phone')} />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                <SelectField label="Fee basis" name="fee_basis" value={form.data.fee_basis} onChange={(v) => form.setData('fee_basis', v)} options={feeBases} />
                {form.data.fee_basis === 'percentage' && <Field label="Fee (%)" type="number" step="0.01" {...bind('fee_percentage')} />}
                <Field label="Agreed fee (R)" type="number" {...bind('agreed_fee')} />
                <Field label="Appointed on" type="date" {...bind('appointed_on')} />
            </div>
            <div className="flex gap-3">
                <Button type="submit" disabled={form.processing}>Add to team</Button>
                <Button type="button" variant="secondary" onClick={onDone}>Cancel</Button>
            </div>
        </form>
    );
}

Team.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
