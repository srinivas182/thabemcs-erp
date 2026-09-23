import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatRand, selectClass, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Source {
    id: string;
    type: string;
    typeLabel: string;
    name: string;
    investor: string | null;
    committed: number;
    received: number;
    repaid: number;
    interestRate: string | null;
    agreementSignedOn: string | null;
    status: string;
    movements: { direction: 'in' | 'out'; amount: number; date: string; reference: string | null }[];
}

interface Props {
    project: { id: string; name: string; code: string };
    summary: { requirement: number | null; requirementSource: string | null; committed: number; received: number; repaid: number; gap: number | null };
    sources: Source[];
    account: { bank: string; accountName: string; last4: string; openedOn: string | null } | null;
    types: Option[];
    can: { manage: boolean };
}

const STATUSES: Option[] = [
    { key: 'proposed', label: 'Proposed' },
    { key: 'committed', label: 'Committed' },
    { key: 'active', label: 'Active' },
    { key: 'closed', label: 'Closed' },
];

export default function Funding({ project, summary, sources, account, types, can }: Props) {
    const covered = summary.requirement ? Math.min(100, (summary.committed / summary.requirement) * 100) : null;

    return (
        <>
            <Head title={`Funding: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft">
                        <Link href="/projects" className="hover:underline">Projects</Link> /{' '}
                        <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link>
                    </p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Funding</h1>
                    <p className="text-ink-soft">{project.name}</p>
                </header>

                <section className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                    {summary.requirement === null ? (
                        <p className="text-sm">
                            No approved feasibility yet, so the funding requirement is unknown.{' '}
                            <Link href={`/projects/${project.id}/feasibility`} className="font-medium text-line hover:underline">Open the feasibility</Link>.
                        </p>
                    ) : (
                        <>
                            <div className="flex flex-wrap items-baseline justify-between gap-2">
                                <p>
                                    <span className="text-2xl font-bold tabular-nums">{formatRand(summary.committed)}</span>
                                    <span className="text-ink-soft"> committed of {formatRand(summary.requirement)} needed</span>
                                </p>
                                <p className={cn('font-semibold', summary.gap ? 'text-brick' : 'text-line-deep')}>
                                    {summary.gap ? `${formatRand(summary.gap)} still to raise` : 'Fully funded'}
                                </p>
                            </div>
                            <div className="mt-3 h-3 overflow-hidden rounded-full bg-concrete-soft" role="progressbar" aria-valuenow={Math.round(covered ?? 0)} aria-valuemin={0} aria-valuemax={100}>
                                <div className="h-full bg-line" style={{ width: `${covered}%` }} />
                            </div>
                            <p className="mt-2 text-sm text-ink-soft">
                                Requirement is the peak funding from the approved baseline "{summary.requirementSource}". Received so far {formatRand(summary.received)}; paid out {formatRand(summary.repaid)}.
                            </p>
                        </>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Funding sources</h2>
                    {sources.length === 0 && <p className="text-ink-soft">No funding sources yet. Add developer equity, investor capital, loans or grants.</p>}
                    <ul className="grid gap-3">
                        {sources.map((s) => (
                            <SourceCard key={s.id} source={s} canManage={can.manage} />
                        ))}
                    </ul>
                    {can.manage && <NewSource projectId={project.id} types={types} />}
                </section>

                <BankAccount projectId={project.id} account={account} canManage={can.manage} />
            </div>
        </>
    );
}

function SourceCard({ source, canManage }: { source: Source; canManage: boolean }) {
    const [recording, setRecording] = useState(false);
    const form = useForm({ direction: 'in', amount: '', occurred_on: '', reference: '' });

    function record(e: FormEvent) {
        e.preventDefault();
        form.post(`/funding-sources/${source.id}/movements`, { preserveScroll: true, onSuccess: () => { form.reset(); setRecording(false); } });
    }

    return (
        <li className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold">{source.name}</p>
                    <p className="text-sm text-ink-soft">
                        {source.typeLabel}
                        {source.investor && `, ${source.investor}`}
                        {source.interestRate && `, ${Number(source.interestRate)}% a year`}
                        {source.agreementSignedOn ? `, agreement signed ${formatDate(source.agreementSignedOn)}` : ', agreement not signed yet'}
                    </p>
                </div>
                <div className="flex items-center gap-3">
                    <div className="text-right text-sm tabular-nums">
                        <p className="font-semibold">{formatRand(source.committed)}</p>
                        <p className="text-ink-soft">received {formatRand(source.received)}</p>
                    </div>
                    {canManage ? (
                        <select aria-label="Status" className={selectClass + ' h-9 w-32 text-sm'} value={source.status} onChange={(e) => router.patch(`/funding-sources/${source.id}`, { status: e.target.value }, { preserveScroll: true })}>
                            {STATUSES.map((s) => (<option key={s.key} value={s.key}>{s.label}</option>))}
                        </select>
                    ) : (
                        <span className="text-sm capitalize">{source.status}</span>
                    )}
                </div>
            </div>

            {source.movements.length > 0 && (
                <ul className="mt-3 grid gap-1 border-t border-concrete pt-2 text-sm">
                    {source.movements.map((m, i) => (
                        <li key={i} className="flex justify-between gap-3">
                            <span className="text-ink-soft">{formatDate(m.date)} {m.reference && `, ${m.reference}`}</span>
                            <span className={cn('tabular-nums', m.direction === 'out' && 'text-brick')}>{m.direction === 'out' ? '−' : '+'}{formatRand(m.amount)}</span>
                        </li>
                    ))}
                </ul>
            )}

            {canManage && !recording && (
                <button className="mt-2 text-sm font-medium text-line hover:underline" onClick={() => setRecording(true)}>Record money in or out</button>
            )}
            {recording && (
                <form onSubmit={record} className="mt-3 grid items-end gap-3 border-t border-concrete pt-3 sm:grid-cols-[150px_1fr_160px_1fr_auto]">
                    <SelectField label="Type" name="direction" value={form.data.direction} onChange={(v) => form.setData('direction', v)} options={[{ key: 'in', label: 'Received' }, { key: 'out', label: 'Paid out' }]} />
                    <Field label="Amount (R)" name="amount" type="number" min={0} value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
                    <Field label="Date" name="occurred_on" type="date" value={form.data.occurred_on} onChange={(e) => form.setData('occurred_on', e.target.value)} error={form.errors.occurred_on} />
                    <Field label="Reference" name="reference" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} />
                    <Button type="submit" disabled={form.processing}>Save</Button>
                </form>
            )}
        </li>
    );
}

function NewSource({ projectId, types }: { projectId: string; types: Option[] }) {
    const form = useForm({ type: 'equity', name: '', investor: '', committed_amount: '', interest_rate: '', agreement_signed_on: '', status: 'proposed' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, investor: d.investor || null, interest_rate: d.interest_rate || null, agreement_signed_on: d.agreement_signed_on || null }));
        form.post(`/projects/${projectId}/funding-sources`, { preserveScroll: true, onSuccess: () => form.reset() });
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-dashed border-concrete p-4">
            <p className="font-semibold">Add a funding source</p>
            <div className="grid gap-4 sm:grid-cols-3">
                <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                <Field label="Name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="e.g. Development loan, Bank X" />
                <Field label="Amount committed (R)" name="committed_amount" type="number" min={1} value={form.data.committed_amount} onChange={(e) => form.setData('committed_amount', e.target.value)} error={form.errors.committed_amount} />
            </div>
            <div className="grid gap-4 sm:grid-cols-4">
                {form.data.type === 'investor' && (
                    <LookupField label="Investor" name="investor" value={form.data.investor} onChange={(v) => form.setData('investor', v)} type="investors" placeholder="Search investors" error={form.errors.investor} />
                )}
                {form.data.type === 'debt' && (
                    <Field label="Interest rate (% a year)" name="interest_rate" type="number" step="0.01" value={form.data.interest_rate} onChange={(e) => form.setData('interest_rate', e.target.value)} error={form.errors.interest_rate} />
                )}
                <Field label="Agreement signed on" name="agreement_signed_on" type="date" value={form.data.agreement_signed_on} onChange={(e) => form.setData('agreement_signed_on', e.target.value)} error={form.errors.agreement_signed_on} />
                <SelectField label="Status" name="status" value={form.data.status} onChange={(v) => form.setData('status', v)} options={STATUSES} />
            </div>
            <div>
                <Button type="submit" disabled={form.processing}>Add funding source</Button>
            </div>
        </form>
    );
}

function BankAccount({ projectId, account, canManage }: { projectId: string; account: Props['account']; canManage: boolean }) {
    const [editing, setEditing] = useState(false);
    const form = useForm({ bank: account?.bank ?? '', account_name: account?.accountName ?? '', account_last4: account?.last4 ?? '', opened_on: account?.openedOn ?? '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, opened_on: d.opened_on || null }));
        form.put(`/projects/${projectId}/bank-account`, { preserveScroll: true, onSuccess: () => setEditing(false) });
    }

    return (
        <section className="grid gap-3 border-t-2 border-ink pt-4">
            <h2 className="font-bold">Project bank account</h2>
            <p className="text-sm text-ink-soft">Project money is kept separate from company money. Only the last four digits of the account number are stored.</p>
            {account && !editing && (
                <p>
                    {account.bank}, {account.accountName}, account ending {account.last4}
                    {account.openedOn && `, opened ${formatDate(account.openedOn)}`}
                    {canManage && <button className="ml-3 text-sm font-medium text-line hover:underline" onClick={() => setEditing(true)}>Change</button>}
                </p>
            )}
            {!account && !editing && canManage && (
                <div><Button variant="secondary" onClick={() => setEditing(true)}>Add project bank account</Button></div>
            )}
            {editing && (
                <form onSubmit={submit} className="grid items-end gap-3 sm:grid-cols-[1fr_1fr_140px_160px_auto]">
                    <Field label="Bank" name="bank" value={form.data.bank} onChange={(e) => form.setData('bank', e.target.value)} error={form.errors.bank} />
                    <Field label="Account name" name="account_name" value={form.data.account_name} onChange={(e) => form.setData('account_name', e.target.value)} error={form.errors.account_name} />
                    <Field label="Last 4 digits" name="account_last4" inputMode="numeric" maxLength={4} value={form.data.account_last4} onChange={(e) => form.setData('account_last4', e.target.value)} error={form.errors.account_last4} />
                    <Field label="Opened on" name="opened_on" type="date" value={form.data.opened_on} onChange={(e) => form.setData('opened_on', e.target.value)} />
                    <Button type="submit" disabled={form.processing}>Save</Button>
                </form>
            )}
        </section>
    );
}

Funding.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
