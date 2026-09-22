import { Head, Link, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import type { FormEvent, ReactNode } from 'react';
import { formatDateTime, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Props {
    project: { id: string; name: string; code: string };
    meetings: { id: string; reference: string; title: string; at: string; status: string; actions: number; open: number }[];
    types: { key: string; label: string }[];
    canManage: boolean;
}

export default function Meetings({ project, meetings, types, canManage }: Props) {
    const now = new Date(); now.setMinutes(now.getMinutes() - now.getTimezoneOffset());
    const form = useForm({ type: 'site', title: '', held_at: now.toISOString().slice(0, 16), location: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post(`/projects/${project.id}/meetings`);
    }
    return (
        <>
            <Head title={`Meetings: ${project.name}`} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Meetings and minutes</h1>
                    <p className="text-ink-soft">{project.name}. Action items become tasks for the people responsible.</p>
                </header>
                {canManage && (
                    <form onSubmit={submit} className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-[180px_1fr_200px_1fr_auto]">
                        <SelectField label="Type" name="type" value={form.data.type} onChange={(v) => form.setData('type', v)} options={types} />
                        <Field label="Title" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} placeholder="e.g. Weekly site meeting" />
                        <Field label="When" name="held_at" type="datetime-local" value={form.data.held_at} onChange={(e) => form.setData('held_at', e.target.value)} />
                        <Field label="Where" name="location" value={form.data.location} onChange={(e) => form.setData('location', e.target.value)} placeholder="Site office" />
                        <Button type="submit" disabled={form.processing}>New meeting</Button>
                    </form>
                )}
                {meetings.length === 0 ? <p className="text-ink-soft">No meetings recorded.</p> : (
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {meetings.map((m) => (
                            <li key={m.id} className="flex flex-wrap items-center justify-between gap-3 p-3">
                                <span>
                                    <Link href={`/projects/${project.id}/meetings/${m.id}`} className="font-semibold hover:underline">{m.reference}: {m.title}</Link>
                                    <span className="block text-sm text-ink-soft">{formatDateTime(m.at)}, {m.actions} actions{m.open > 0 && `, ${m.open} open`}</span>
                                </span>
                                <span className={cn('text-sm', m.status === 'issued' ? 'text-line-deep' : 'text-ink-soft')}>{m.status === 'issued' ? 'Minutes issued' : 'Draft'}</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Meetings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
