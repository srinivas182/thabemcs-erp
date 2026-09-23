import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatDateTime, formatRand, PageHeader } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Item {
    id: string; title: string; url: string; amount: number; policy: string; requester: string; submittedAt: string;
    step: number; steps: number; status: string; stepRole: string | null; overdue: boolean;
}

interface Props {
    waiting: Item[];
    mine: Item[];
    delegations: { id: number; delegate: string; from: string; to: string }[];
    }

const POLICY: Record<string, string> = { requisition: 'Requisition', purchase_order: 'Purchase order' };

export default function Inbox({ waiting, mine, delegations }: Props) {
    return (
        <>
            <Head title="Approvals" />
            <div className="mx-auto grid max-w-5xl gap-8">
                <PageHeader title="Approvals" description="Everything waiting for your decision. Nobody can approve their own request, and each step needs a different person." />

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Waiting for you {waiting.length > 0 && <span className="font-normal text-ink-soft">({waiting.length})</span>}</h2>
                    {waiting.length === 0 ? <p className="text-ink-soft">Nothing is waiting for you.</p> : (
                        <ul className="grid gap-3">{waiting.map((i) => <Decision key={i.id} item={i} />)}</ul>
                    )}
                </section>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Your requests</h2>
                    {mine.length === 0 ? <p className="text-ink-soft">You have not submitted anything for approval.</p> : (
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {mine.map((i) => (
                                <li key={i.id} className="flex flex-wrap justify-between gap-2 p-3">
                                    <Link href={i.url} className="font-medium hover:underline">{i.title}</Link>
                                    <span className={cn(i.status === 'rejected' ? 'text-brick' : i.status === 'approved' ? 'text-line-deep' : 'text-ink-soft')}>
                                        {i.status === 'pending' ? `Step ${i.step} of ${i.steps}: waiting for ${i.stepRole}` : i.status === 'approved' ? 'Approved' : i.status === 'rejected' ? 'Rejected' : i.status}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                <Delegation delegations={delegations} />
            </div>
        </>
    );
}

function Decision({ item }: { item: Item }) {
    const [comment, setComment] = useState('');
    const [busy, setBusy] = useState(false);

    function decide(decision: 'approve' | 'reject') {
        if (decision === 'reject' && !comment.trim()) return alert('Give a reason for rejecting.');
        setBusy(true);
        router.post(`/inbox/${item.id}`, { decision, comment: comment || null }, { preserveScroll: true, onFinish: () => setBusy(false) });
    }

    return (
        <li className={cn('rounded-[var(--radius-panel)] border bg-surface p-4', item.overdue ? 'border-hivis' : 'border-concrete')}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="text-sm text-ink-soft">{POLICY[item.policy] ?? item.policy}, step {item.step} of {item.steps} ({item.stepRole})</p>
                    <Link href={item.url} className="text-lg font-semibold hover:underline">{item.title}</Link>
                    <p className="text-sm text-ink-soft">From {item.requester}, {formatDateTime(item.submittedAt)}{item.overdue && <span className="font-semibold text-ink">. Overdue</span>}</p>
                </div>
                <p className="text-xl font-bold tabular-nums">{formatRand(item.amount)}<span className="block text-right text-xs font-normal text-ink-soft">excl. VAT</span></p>
            </div>
            <div className="mt-3 flex flex-wrap items-center gap-2">
                <input value={comment} onChange={(e) => setComment(e.target.value)} placeholder="Comment (required to reject)" aria-label="Comment" className="h-10 min-w-56 flex-1 rounded-[var(--radius-control)] border border-concrete px-3 text-sm" />
                <Button onClick={() => decide('approve')} disabled={busy}>Approve</Button>
                <Button variant="secondary" className="text-brick" onClick={() => decide('reject')} disabled={busy}>Reject</Button>
            </div>
        </li>
    );
}

function Delegation({ delegations }: { delegations: Props['delegations'] }) {
    const today = new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());
    const form = useForm({ delegate: '', starts_on: today, ends_on: '', reason: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/delegations', { preserveScroll: true, onSuccess: () => form.reset('delegate', 'ends_on', 'reason') });
    }
    return (
        <section className="grid gap-3 border-t-2 border-ink pt-4">
            <h2 className="font-bold">Going away?</h2>
            <p className="text-sm text-ink-soft">Let someone approve on your behalf while you are on leave. Their approvals are recorded as made for you.</p>
            {delegations.map((d) => (
                <p key={d.id} className="text-sm">
                    {d.delegate} approves for you from {formatDate(d.from)} to {formatDate(d.to)}.{' '}
                    <button className="text-brick underline" onClick={() => router.delete(`/delegations/${d.id}`, { preserveScroll: true })}>End now</button>
                </p>
            ))}
            <form onSubmit={submit} className="grid items-end gap-3 sm:grid-cols-[1fr_160px_160px_1fr_auto]">
                <LookupField label="Delegate to" name="delegate" value={form.data.delegate} onChange={(v) => form.setData('delegate', v)} type="people" placeholder="Choose a person" error={form.errors.delegate} />
                <Field label="From" name="starts_on" type="date" value={form.data.starts_on} onChange={(e) => form.setData('starts_on', e.target.value)} error={form.errors.starts_on} />
                <Field label="Until" name="ends_on" type="date" value={form.data.ends_on} onChange={(e) => form.setData('ends_on', e.target.value)} error={form.errors.ends_on} />
                <Field label="Reason" name="reason" value={form.data.reason} onChange={(e) => form.setData('reason', e.target.value)} placeholder="e.g. Annual leave" />
                <Button type="submit" disabled={form.processing}>Delegate</Button>
            </form>
        </section>
    );
}

Inbox.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
