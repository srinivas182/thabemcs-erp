import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { PageHeader, Pager } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Item { id: string; url: string; name: string; alt: string | null; size: string; dimensions: string | null; uploaded: string }

export default function Media({ media }: { media: { data: Item[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number } }) {
    const form = useForm<{ file: File | null; alt: string }>({ file: null, alt: '' });
    const [editing, setEditing] = useState<string | null>(null);

    return (
        <>
            <Head title="Media" />
            <div className="mx-auto grid max-w-5xl gap-5">
                <PageHeader title="Media" description="Photographs and documents used on the website. Everything here is public, so do not put private files in it."
                    action={<Button variant="ghost" asChild><Link href="/website/pages">Pages</Link></Button>} />

                <form onSubmit={(e) => { e.preventDefault(); form.post('/website/media', { forceFormData: true, preserveScroll: true, onSuccess: () => form.reset() }); }}
                    className="grid items-end gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5 sm:grid-cols-[1fr_1fr_auto]">
                    <div className="grid gap-1.5">
                        <label htmlFor="file" className="text-sm font-medium">Image or PDF (up to 10 MB)</label>
                        <input id="file" type="file" accept="image/*,application/pdf" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm" />
                    </div>
                    <Field label="Describe the image (for screen readers and search)" name="alt" value={form.data.alt} onChange={(e) => form.setData('alt', e.target.value)} />
                    <Button type="submit" disabled={!form.data.file || form.processing}>Upload</Button>
                </form>

                <ul className="grid grid-cols-2 gap-3 sm:grid-cols-4">
                    {media.data.map((m) => (
                        <li key={m.id} className="overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-surface">
                            <img src={m.url} alt={m.alt ?? ''} className="h-32 w-full bg-plaster object-cover" />
                            <div className="grid gap-1 p-2 text-xs">
                                <p className="truncate font-medium">{m.name}</p>
                                <p className="text-ink-soft">{m.size}{m.dimensions ? ` · ${m.dimensions}` : ''}</p>
                                {editing === m.id ? <AltForm id={m.id} alt={m.alt ?? ''} onDone={() => setEditing(null)} /> : (
                                    <p className="flex gap-2">
                                        <button className="text-line underline" onClick={() => setEditing(m.id)}>{m.alt ? 'Edit description' : 'Add description'}</button>
                                        <button className="text-brick underline" onClick={() => window.confirm('Delete this file? Any page using it will show a gap.') && router.delete(`/website/media/${m.id}`, { preserveScroll: true })}>Delete</button>
                                    </p>
                                )}
                            </div>
                        </li>
                    ))}
                    {media.data.length === 0 && <li className="text-ink-soft">Nothing uploaded yet.</li>}
                </ul>
                <Pager prev={media.prev_page_url} next={media.next_page_url} page={media.current_page} last={media.last_page} />
            </div>
        </>
    );
}

function AltForm({ id, alt, onDone }: { id: string; alt: string; onDone: () => void }) {
    const form = useForm({ alt });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.patch(`/website/media/${id}`, { preserveScroll: true, onSuccess: onDone }); }} className="grid gap-1">
            <input value={form.data.alt} onChange={(e) => form.setData('alt', e.target.value)} className="rounded border border-concrete px-2 py-1" aria-label="Description" />
            <Button size="sm" type="submit">Save</Button>
        </form>
    );
}

Media.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
