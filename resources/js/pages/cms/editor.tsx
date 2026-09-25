import { Head, Link, router, usePage } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { formatDateTime, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

// eslint-disable-next-line @typescript-eslint/no-explicit-any
type Value = any;
type FieldDef = { name: string; type: string; label: string; required?: boolean; options?: string[]; default?: Value; fields?: Record<string, FieldDef> };
type BlockType = { key: string; label: string; help: string | null; fields: FieldDef[] };
type Block = { type: string; data: Record<string, Value> };
interface Props {
    page: { id: string; title: string; slug: string; path: string; template: string; blocks: Block[]; seo: Record<string, string>; status: string; isHome: boolean; showInSearch: boolean; version: number; live: boolean };
    blockTypes: BlockType[];
    templates: { key: string; label: string }[];
    media: { id: string; url: string; alt: string | null; name: string }[];
    forms: { key: string; label: string }[];
    versions: { id: number; version: number; note: string | null; at: string }[];
}

/** Builds a page out of blocks. What you see here is what the website shows once you publish. */
export default function Editor({ page, blockTypes, templates, media, forms, versions }: Props) {
    // The blocks are free-form JSON, so the editor keeps them in state and posts them itself; the server
    // strips anything that is not a known block or field before saving.
    const { errors } = usePage().props as { errors: Record<string, string> };
    const [data, setData] = useState({
        title: page.title, slug: page.slug, template: page.template, blocks: page.blocks,
        seo: page.seo, show_in_search: page.showInSearch,
    });
    const [saving, setSaving] = useState(false);
    const form = {
        data,
        errors,
        processing: saving,
        setData: (key: string, value: Value) => setData((d) => ({ ...d, [key]: value })),
        put: (url: string) => {
            setSaving(true);
            router.put(url, data, { preserveScroll: true, onFinish: () => setSaving(false) });
        },
    };
    const [open, setOpen] = useState<number | null>(page.blocks.length === 0 ? null : 0);
    const [adding, setAdding] = useState(false);

    const setBlock = (i: number, blockData: Record<string, Value>) =>
        form.setData('blocks', form.data.blocks.map((b, j) => (j === i ? { ...b, data: blockData } : b)));
    const move = (i: number, by: number) => {
        const next = [...form.data.blocks]; const j = i + by;
        if (j < 0 || j >= next.length) return;
        [next[i], next[j]] = [next[j]!, next[i]!];
        form.setData('blocks', next);
        setOpen(j);
    };

    return (
        <>
            <Head title={`Edit ${page.title}`} />
            <div className="mx-auto grid max-w-5xl gap-5">
                <header className="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/website/pages" className="hover:underline">Website</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{page.title}</h1>
                        <p className="text-ink-soft"><span className="font-mono">{page.path}</span> · version {page.version} · {page.live ? 'live on the website' : 'draft'}</p>
                    </div>
                    <div className="flex flex-wrap gap-2">
                        <Button variant="secondary" onClick={() => form.put(`/website/pages/${page.id}`)} disabled={form.processing}>Save</Button>
                        {page.live
                            ? <Button variant="ghost" className="text-brick" onClick={() => router.post(`/website/pages/${page.id}/unpublish`, {}, { preserveScroll: true })}>Take off the website</Button>
                            : <Button onClick={() => router.post(`/website/pages/${page.id}/publish`, {}, { preserveScroll: true })}>Publish</Button>}
                    </div>
                </header>

                <section className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5 sm:grid-cols-3">
                    <Field label="Page title" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} />
                    <Field label="Web address" name="slug" value={form.data.slug} onChange={(e) => form.setData('slug', e.target.value)} error={form.errors.slug} />
                    <SelectField label="Layout" name="template" value={form.data.template} onChange={(v) => form.setData('template', v)} options={templates} />
                </section>

                <section className="grid gap-2">
                    <h2 className="text-lg font-bold">Content</h2>
                    {form.data.blocks.length === 0 && <p className="text-ink-soft">This page is empty. Add a block to start.</p>}
                    <ol className="grid gap-2">
                        {form.data.blocks.map((block, i) => {
                            const type = blockTypes.find((t) => t.key === block.type);
                            return (
                                <li key={i} className="rounded-[var(--radius-panel)] border border-concrete bg-surface">
                                    <div className="flex flex-wrap items-center justify-between gap-2 p-3">
                                        <button className="text-left font-semibold" onClick={() => setOpen(open === i ? null : i)} aria-expanded={open === i}>
                                            {i + 1}. {type?.label ?? block.type}
                                            <span className="ml-2 text-sm font-normal text-ink-soft">{String(block.data.heading ?? block.data.title ?? '')}</span>
                                        </button>
                                        <span className="flex gap-2 text-ink-soft">
                                            <button aria-label="Move up" onClick={() => move(i, -1)}><ArrowUp className="size-4" /></button>
                                            <button aria-label="Move down" onClick={() => move(i, 1)}><ArrowDown className="size-4" /></button>
                                            <button aria-label="Remove block" className="hover:text-brick" onClick={() => form.setData('blocks', form.data.blocks.filter((_, j) => j !== i))}><Trash2 className="size-4" /></button>
                                        </span>
                                    </div>
                                    {open === i && type && (
                                        <div className="grid gap-4 border-t border-concrete p-4">
                                            {type.help && <p className="text-sm text-ink-soft">{type.help}</p>}
                                            {type.fields.map((f) => (
                                                <BlockField key={f.name} field={f} value={block.data[f.name]} media={media} forms={forms}
                                                    onChange={(v) => setBlock(i, { ...block.data, [f.name]: v })} />
                                            ))}
                                        </div>
                                    )}
                                </li>
                            );
                        })}
                    </ol>

                    {adding ? (
                        <div className="grid gap-2 rounded-[var(--radius-panel)] border-2 border-ink bg-surface p-4">
                            <p className="font-semibold">Add a block</p>
                            <div className="flex flex-wrap gap-2">
                                {blockTypes.map((t) => (
                                    <button key={t.key} className="rounded-full border border-concrete px-3 py-1 text-sm hover:border-line"
                                        onClick={() => { form.setData('blocks', [...form.data.blocks, { type: t.key, data: {} }]); setOpen(form.data.blocks.length); setAdding(false); }}>
                                        {t.label}
                                    </button>
                                ))}
                            </div>
                        </div>
                    ) : (
                        <Button variant="secondary" className="justify-self-start" onClick={() => setAdding(true)}><Plus className="size-4" /> Add a block</Button>
                    )}
                </section>

                <section className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                    <h2 className="font-bold">How this page appears in search results</h2>
                    <div className="grid gap-4 sm:grid-cols-2">
                        <Field label="Title in search results" name="seo_title" value={form.data.seo.title ?? ''} onChange={(e) => form.setData('seo', { ...form.data.seo, title: e.target.value })} placeholder={form.data.title} />
                        <Field label="Description" name="seo_description" value={form.data.seo.description ?? ''} onChange={(e) => form.setData('seo', { ...form.data.seo, description: e.target.value })} placeholder="One or two lines describing the page" />
                    </div>
                    <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.show_in_search} onChange={(e) => form.setData('show_in_search', e.target.checked)} /> Let search engines list this page</label>
                </section>

                {versions.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="font-bold">Earlier versions</h2>
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {versions.map((v) => (
                                <li key={v.id} className="flex items-center justify-between gap-3 px-3 py-2">
                                    <span>Version {v.version} · {formatDateTime(v.at)}{v.note ? ` · ${v.note}` : ''}</span>
                                    <button className="text-line hover:underline"
                                        onClick={() => window.confirm(`Put the page back to version ${v.version}? The current content is kept as a version first.`) && router.post(`/website/pages/${page.id}/restore/${v.id}`, {}, { preserveScroll: true })}>
                                        Put this back
                                    </button>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

function BlockField({ field, value, onChange, media, forms }: { field: FieldDef; value: Value; onChange: (v: Value) => void; media: Props['media']; forms: Props['forms'] }) {
    if (field.type === 'toggle') {
        return <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={Boolean(value ?? field.default)} onChange={(e) => onChange(e.target.checked)} /> {field.label}</label>;
    }
    if (field.type === 'textarea' || field.type === 'richtext') {
        return (
            <label className="grid gap-1.5 text-sm font-medium">{field.label}
                <textarea rows={field.type === 'richtext' ? 6 : 3} value={String(value ?? '')} onChange={(e) => onChange(e.target.value)}
                    className="rounded-[var(--radius-control)] border border-concrete p-3 font-normal" />
            </label>
        );
    }
    if (field.type === 'select') {
        return <SelectField label={field.label} name={field.name} value={String(value ?? '')} onChange={onChange}
            options={(field.options ?? []).map((o) => ({ key: o, label: o.replace(/_/g, ' ') }))} placeholder="Choose" />;
    }
    if (field.type === 'select_form') {
        return <SelectField label={field.label} name={field.name} value={String(value ?? '')} onChange={onChange} options={forms} placeholder="Choose a form" />;
    }
    if (field.type === 'image') {
        return (
            <div className="grid gap-2">
                <p className="text-sm font-medium">{field.label}</p>
                <div className="flex flex-wrap gap-2">
                    {media.map((m) => (
                        <button key={m.id} onClick={() => onChange(m.url)} aria-label={m.alt ?? m.name}
                            className={cn('size-16 overflow-hidden rounded-[var(--radius-control)] border-2', value === m.url ? 'border-line' : 'border-concrete')}>
                            <img src={m.url} alt="" className="size-full object-cover" />
                        </button>
                    ))}
                    {media.length === 0 && <Link href="/website/media" className="text-sm text-line underline">Upload some images first</Link>}
                </div>
            </div>
        );
    }
    if (field.type === 'repeater') {
        const rows = Array.isArray(value) ? (value as Record<string, Value>[]) : [];
        const sub = Object.entries(field.fields ?? {}).map(([name, f]) => ({ ...f, name }));
        return (
            <div className="grid gap-2">
                <p className="text-sm font-medium">{field.label}</p>
                {rows.map((row, i) => (
                    <div key={i} className="grid gap-3 rounded-[var(--radius-control)] border border-concrete p-3">
                        {sub.map((f) => (
                            <BlockField key={f.name} field={f} value={row[f.name]} media={media} forms={forms}
                                onChange={(v) => onChange(rows.map((r, j) => (j === i ? { ...r, [f.name]: v } : r)))} />
                        ))}
                        <button className="justify-self-start text-sm text-brick underline" onClick={() => onChange(rows.filter((_, j) => j !== i))}>Remove</button>
                    </div>
                ))}
                <button className="justify-self-start text-sm text-line underline" onClick={() => onChange([...rows, {}])}>Add another</button>
            </div>
        );
    }
    return <Field label={field.label} name={field.name} type={field.type === 'number' ? 'number' : 'text'}
        value={String(value ?? field.default ?? '')} onChange={(e) => onChange(e.target.value)} />;
}

Editor.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
