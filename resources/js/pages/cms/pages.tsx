import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDateTime, PageHeader, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Row {
    id: string; title: string; slug: string; path: string; template: string; status: string;
    isHome: boolean; live: boolean; publishFrom: string | null; published: string | null; updated: string; versions: number;
}

export default function Pages({ pages, templates }: { pages: Row[]; templates: { key: string; label: string }[] }) {
    const [adding, setAdding] = useState(false);
    const form = useForm({ title: '', slug: '', template: 'page' });

    return (
        <>
            <Head title="Website pages" />
            <div className="mx-auto grid max-w-5xl gap-5">
                <PageHeader title="Website" description="The pages the public sees. Changes go live only when you publish them."
                    action={<div className="flex gap-2">
                        <Button variant="ghost" asChild><Link href="/website/developments">Photographs</Link></Button>
                        <Button variant="ghost" asChild><Link href="/website/media">Media</Link></Button>
                        <Button variant="ghost" asChild><Link href="/website/menus">Menus</Link></Button>
                        <Button onClick={() => setAdding(!adding)}>New page</Button>
                    </div>} />

                {adding && (
                    <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, slug: d.slug || null })); form.post('/website/pages'); }}
                        className="grid items-end gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5 sm:grid-cols-4">
                        <Field label="Page title" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} />
                        <Field label="Web address (optional)" name="slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} placeholder="about-us" />
                        <SelectField label="Layout" name="template" value={form.data.template} onChange={(v) => form.setData('template', v)} options={templates} />
                        <Button type="submit" disabled={form.processing}>Create</Button>
                    </form>
                )}

                <ul className="grid gap-2">
                    {pages.map((p) => (
                        <li key={p.id} className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3">
                            <div>
                                <Link href={`/website/pages/${p.id}`} className="font-semibold hover:underline">{p.title}</Link>
                                {p.isHome && <span className="ml-2 rounded-full bg-line-wash px-2 py-0.5 text-xs text-line-deep">Home page</span>}
                                <span className="block text-sm text-ink-soft">
                                    <span className="font-mono">{p.path}</span> · {templates.find((t) => t.key === p.template)?.label} · edited {formatDateTime(p.updated)} · {p.versions} versions
                                </span>
                            </div>
                            <div className="flex items-center gap-3 text-sm">
                                <span className={cn('font-medium', p.live ? 'text-line-deep' : 'text-ink-soft')}>
                                    {p.live ? 'Live' : p.publishFrom ? `Scheduled ${formatDateTime(p.publishFrom)}` : 'Draft'}
                                </span>
                                {!p.isHome && p.status === 'published' && (
                                    <button className="text-ink-soft underline" onClick={() => router.post(`/website/pages/${p.id}/home`, {}, { preserveScroll: true })}>Make home</button>
                                )}
                                <Link href={`/website/pages/${p.id}`} className="text-line hover:underline">Edit</Link>
                            </div>
                        </li>
                    ))}
                    {pages.length === 0 && <li className="text-ink-soft">No pages yet.</li>}
                </ul>
                <p className="text-sm text-ink-soft">Articles and forms have their own screens: <Link href="/website/articles" className="underline">articles</Link>, <Link href="/website/forms" className="underline">forms</Link>, <Link href="/website/enquiries" className="underline">enquiries</Link>.</p>
            </div>
        </>
    );
}

Pages.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
