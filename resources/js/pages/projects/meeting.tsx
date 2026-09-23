import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import type { FormEvent, ReactNode } from 'react';
import { formatDate, formatDateTime } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Props {
    project: { id: string; name: string; code: string };
    meeting: { id: string; reference: string; title: string; type: string; at: string; location: string | null; attendees: string | null; apologies: string | null; minutes: string | null; status: string; issuedAt: string | null; by: string };
    actions: { id: string; title: string; owner: string | null; due: string | null; status: string }[];
    canManage: boolean;
}

export default function MeetingPage({ project, meeting: m, actions, canManage }: Props) {
    const draft = m.status === 'draft' && canManage;
    const notes = useForm({ attendees: m.attendees ?? '', apologies: m.apologies ?? '', minutes: m.minutes ?? '' });
    const action = useForm({ title: '', owner: '', due_date: '' });
    const base = `/projects/${project.id}/meetings/${m.id}`;
    function save(e: FormEvent) { e.preventDefault(); notes.put(base, { preserveScroll: true }); }
    function addAction(e: FormEvent) {
        e.preventDefault();
        action.transform((d) => ({ ...d, owner: d.owner || null, due_date: d.due_date || null }));
        action.post(`${base}/actions`, { preserveScroll: true, onSuccess: () => action.reset() });
    }
    const area = 'w-full rounded-[var(--radius-control)] border border-concrete bg-surface p-3 text-sm disabled:bg-plaster';

    return (
        <>
            <Head title={m.reference} />
            <div className="mx-auto grid max-w-4xl gap-6 print:max-w-none">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-sm text-ink-soft print:hidden"><Link href={`/projects/${project.id}/meetings`} className="hover:underline">Meetings</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{m.reference}: {m.title}</h1>
                        <p className="text-ink-soft">{project.name}, {formatDateTime(m.at)}{m.location && `, ${m.location}`}. Minutes by {m.by}{m.issuedAt && `, issued ${formatDateTime(m.issuedAt)}`}.</p>
                    </div>
                    <div className="flex gap-2 print:hidden">
                        <Button variant="secondary" onClick={() => window.print()}>Print</Button>
                        {draft && <Button onClick={() => window.confirm('Issue the minutes? They will be locked and everyone with an action will be told.') && router.post(`${base}/issue`, {}, { preserveScroll: true })}>Issue minutes</Button>}
                    </div>
                </header>

                <form onSubmit={save} className="grid gap-4">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <label className="grid gap-1.5 text-sm font-medium">Present<textarea rows={3} className={area} disabled={!draft} value={notes.data.attendees} onChange={(e) => notes.setData('attendees', e.target.value)} placeholder="Names and companies" /></label>
                        <label className="grid gap-1.5 text-sm font-medium">Apologies<textarea rows={3} className={area} disabled={!draft} value={notes.data.apologies} onChange={(e) => notes.setData('apologies', e.target.value)} /></label>
                    </div>
                    <label className="grid gap-1.5 text-sm font-medium">Minutes<textarea rows={14} className={area} disabled={!draft} value={notes.data.minutes} onChange={(e) => notes.setData('minutes', e.target.value)} placeholder={'1. Safety\n2. Progress against programme\n3. Drawings and information required\n4. Quality\n5. General'} /></label>
                    {draft && <div className="print:hidden"><Button type="submit" disabled={notes.processing}>Save minutes</Button></div>}
                </form>

                <section className="grid gap-3 border-t-2 border-ink pt-4">
                    <h2 className="text-lg font-bold">Actions</h2>
                    {actions.length === 0 ? <p className="text-sm text-ink-soft">No actions yet.</p> : (
                        <ol className="grid gap-1 text-sm">
                            {actions.map((a, i) => (
                                <li key={a.id} className={cn('flex flex-wrap justify-between gap-2', a.status === 'done' && 'text-ink-soft line-through')}>
                                    <span>{i + 1}. {a.title}</span>
                                    <span className="text-ink-soft">{a.owner ?? 'Unassigned'}{a.due && `, by ${formatDate(a.due)}`}</span>
                                </li>
                            ))}
                        </ol>
                    )}
                    {canManage && (
                        <form onSubmit={addAction} className="grid items-end gap-3 print:hidden sm:grid-cols-[1fr_200px_160px_auto]">
                            <Field label="Action" name="title" value={action.data.title} onChange={(e) => action.setData('title', e.target.value)} error={action.errors.title} />
                            <LookupField label="Who" name="owner" value={action.data.owner} onChange={(v) => action.setData('owner', v)} type="people" placeholder="Choose" />
                            <Field label="By when" name="due_date" type="date" value={action.data.due_date} onChange={(e) => action.setData('due_date', e.target.value)} />
                            <Button type="submit" disabled={action.processing}>Add action</Button>
                        </form>
                    )}
                </section>
            </div>
        </>
    );
}

MeetingPage.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
