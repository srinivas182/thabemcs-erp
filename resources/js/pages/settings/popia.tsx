import { Head, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatDateTime, PageHeader, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };
interface Props {
    officer: { name: string | null; email: string | null };
    register: { category: string; data: string; purpose: string; basis: string; access: string }[];
    rules: { record: string; label: string; months: number; minimum: number; lastRun: string | null; lastCount: number }[];
    requests: { id: string; number: number; name: string; email: string | null; type: string; subjectType: string; hasSubject: boolean; details: string | null; received: string; due: string; overdue: boolean; status: string; outcome: string | null; handler: string | null }[];
    employees: Option[];
    users: Option[];
}

const TYPES = [{ key: 'access', label: 'See their information' }, { key: 'correction', label: 'Correct it' }, { key: 'deletion', label: 'Delete it' }, { key: 'objection', label: 'Object to its use' }];
const SUBJECTS = [{ key: 'employee', label: 'Employee' }, { key: 'user', label: 'System user' }, { key: 'investor', label: 'Investor' }, { key: 'other', label: 'Someone else' }];

export default function Popia({ officer, register, rules, requests, employees, users }: Props) {
    const today = new Date().toLocaleDateString('en-CA');
    const form = useForm({ requester_name: '', requester_email: '', type: 'access', subject_type: 'employee', subject: '', details: '', received_on: today });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, subject: d.subject || null, requester_email: d.requester_email || null }));
        form.post('/settings/popia/requests', { preserveScroll: true, onSuccess: () => form.reset() });
    }
    return (
        <>
            <Head title="POPIA" />
            <div className="mx-auto grid max-w-6xl gap-8">
                <PageHeader title="Personal information (POPIA)" description={`What personal information the system holds, how long it is kept, and requests from people about their information.${officer.name ? ` Information Officer: ${officer.name}${officer.email ? ` (${officer.email})` : ''}.` : ' Set the Information Officer in the settings (POPIA_INFORMATION_OFFICER).'}`} />

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Requests from people</h2>
                    <form onSubmit={submit} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <div className="grid gap-3 sm:grid-cols-4">
                            <Field label="Who asked" name="requester_name" value={form.data.requester_name} onChange={(e) => form.setData('requester_name', e.target.value)} error={form.errors.requester_name} />
                            <Field label="Their email" name="requester_email" type="email" value={form.data.requester_email} onChange={(e) => form.setData('requester_email', e.target.value)} />
                            <SelectField label="They want to" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={TYPES} />
                            <Field label="Received" name="received_on" type="date" value={form.data.received_on} onChange={(e) => form.setData('received_on', e.target.value)} />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-[180px_1fr_1.4fr]">
                            <SelectField label="They are" name="subject_type" value={form.data.subject_type} onChange={(v) => form.setData((d) => ({ ...d, subject_type: v, subject: '' }))} options={SUBJECTS} />
                            {form.data.subject_type === 'employee' && <SelectField label="Employee" name="subject" value={form.data.subject} onChange={(v) => form.setData('subject', v)} options={employees} placeholder="Choose" />}
                            {form.data.subject_type === 'user' && <SelectField label="User" name="subject" value={form.data.subject} onChange={(v) => form.setData('subject', v)} options={users} placeholder="Choose" />}
                            {!['employee', 'user'].includes(form.data.subject_type) && <span />}
                            <Field label="Details" name="details" value={form.data.details} onChange={(e) => form.setData('details', e.target.value)} />
                        </div>
                        <div><Button type="submit" disabled={form.processing}>Record request</Button></div>
                    </form>
                    {requests.length > 0 && <ul className="grid gap-2">{requests.map((r) => <RequestRow key={r.id} request={r} />)}</ul>}
                </section>

                <section className="grid gap-3 border-t-2 border-ink pt-4">
                    <div className="flex flex-wrap items-center justify-between gap-3">
                        <h2 className="text-lg font-bold">How long records are kept</h2>
                        <Button variant="secondary" size="sm" onClick={() => window.confirm('Run the clean-up now? Records past their period will be removed or anonymised. This cannot be undone.') && router.post('/settings/popia/retention/run', {}, { preserveScroll: true })}>Run clean-up now</Button>
                    </div>
                    <p className="text-sm text-ink-soft">The clean-up also runs automatically on the 1st of each month. Former employees are anonymised, not deleted, so totals still add up.</p>
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                        {rules.map((r) => <RuleRow key={r.record} rule={r} />)}
                    </ul>
                </section>

                <section className="grid gap-3 border-t-2 border-ink pt-4">
                    <h2 className="text-lg font-bold">Register of personal information</h2>
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full min-w-[800px] text-left text-sm [&_td]:px-3 [&_td]:py-2 [&_td]:align-top [&_th]:px-3 [&_th]:py-2 [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr><th>Whose</th><th>What</th><th>Why</th><th>Lawful basis</th><th>Who can see it</th></tr></thead>
                            <tbody>{register.map((r) => <tr key={r.category}><td className="font-medium">{r.category}</td><td>{r.data}</td><td>{r.purpose}</td><td>{r.basis}</td><td>{r.access}</td></tr>)}</tbody>
                        </table>
                    </div>
                </section>
            </div>
        </>
    );
}

function RuleRow({ rule: r }: { rule: Props['rules'][number] }) {
    const [months, setMonths] = useState(String(r.months));
    return (
        <li className="flex flex-wrap items-center justify-between gap-3 px-3 py-2">
            <span>{r.label}<span className="block text-xs text-ink-soft">{r.lastRun ? `Last run ${formatDateTime(r.lastRun)}: ${r.lastCount} records` : 'Not run yet'}. Minimum {r.minimum} months.</span></span>
            <span className="flex items-center gap-2">
                <input type="number" min={r.minimum} value={months} onChange={(e) => setMonths(e.target.value)} className="h-9 w-20 rounded-[var(--radius-control)] border border-concrete px-2 text-right" aria-label={`Months to keep ${r.label}`} /> months
                {Number(months) !== r.months && <Button size="sm" onClick={() => router.patch(`/settings/popia/retention/${r.record}`, { months: Number(months) }, { preserveScroll: true })}>Save</Button>}
            </span>
        </li>
    );
}

function RequestRow({ request: r }: { request: Props['requests'][number] }) {
    const [outcome, setOutcome] = useState(r.outcome ?? '');
    const done = ['completed', 'refused'].includes(r.status);
    const set = (status: string) => router.patch(`/settings/popia/requests/${r.id}`, { status, outcome: outcome || null }, { preserveScroll: true });
    return (
        <li className={cn('rounded-[var(--radius-panel)] border bg-surface p-3 text-sm', r.overdue ? 'border-brick' : 'border-concrete')}>
            <div className="flex flex-wrap justify-between gap-2">
                <span><span className="font-semibold">Request {r.number}: {r.name}</span> wants to {TYPES.find((t) => t.key === r.type)?.label.toLowerCase()} ({SUBJECTS.find((s) => s.key === r.subjectType)?.label.toLowerCase()})</span>
                <span className={cn(r.overdue && 'font-semibold text-brick')}>Received {formatDate(r.received)}, respond by {formatDate(r.due)}{r.overdue && ' (overdue)'}</span>
            </div>
            {r.details && <p className="mt-1 text-ink-soft">{r.details}</p>}
            {done ? <p className="mt-1"><span className="capitalize">{r.status}</span> by {r.handler}: {r.outcome}</p> : (
                <div className="mt-2 flex flex-wrap items-center gap-2">
                    {r.hasSubject && <Button size="sm" variant="secondary" asChild><a href={`/settings/popia/requests/${r.id}/export`}>Download their information</a></Button>}
                    <input value={outcome} onChange={(e) => setOutcome(e.target.value)} placeholder="What was done" className="h-9 min-w-56 flex-1 rounded-[var(--radius-control)] border border-concrete px-3" aria-label="Outcome" />
                    {r.status === 'open' && <Button size="sm" variant="ghost" onClick={() => set('in_progress')}>Start</Button>}
                    <Button size="sm" onClick={() => set('completed')}>Complete</Button>
                    <Button size="sm" variant="ghost" className="text-brick" onClick={() => set('refused')}>Refuse</Button>
                </div>
            )}
        </li>
    );
}

Popia.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
