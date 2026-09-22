import { Head, Link, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Copy, GripVertical, Plus, Trash2 } from 'lucide-react';
import { type DragEvent, type FormEvent, type ReactNode, useState } from 'react';
import { SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Question = { id: string; label: string; type: string; required: boolean; options?: string[] };
interface Props { template: { id: string; name: string; kind: string; fields: Question[]; active: boolean; version: number } | null; types: string[] }

const TYPE_LABEL: Record<string, string> = {
    passfail: 'Pass / fail / not applicable', yesno: 'Yes / no', text: 'Text', number: 'Number', choice: 'Choose one', photo: 'Photo', date: 'Date',
};
const newId = () => Math.random().toString(36).slice(2, 10).padEnd(8, '0');

/** Drag questions to reorder; the site app shows them in this order. */
export default function Builder({ template, types }: Props) {
    const form = useForm<{ name: string; kind: string; active: boolean; fields: Question[] }>({
        name: template?.name ?? '', kind: template?.kind ?? 'quality', active: template?.active ?? true,
        fields: template?.fields ?? [{ id: newId(), label: '', type: 'passfail', required: true }],
    });
    const [dragging, setDragging] = useState<number | null>(null);
    const update = (i: number, patch: Partial<Question>) => form.setData('fields', form.data.fields.map((q, j) => (j === i ? { ...q, ...patch } : q)));

    function drop(e: DragEvent, to: number) {
        e.preventDefault();
        if (dragging === null || dragging === to) return;
        const next = [...form.data.fields];
        const [moved] = next.splice(dragging, 1);
        if (moved) next.splice(to, 0, moved);
        form.setData('fields', next);
        setDragging(null);
    }

    function submit(e: FormEvent) {
        e.preventDefault();
        if (template) form.put(`/forms/${template.id}`); else form.post('/forms');
    }

    return (
        <>
            <Head title={template ? `Edit ${template.name}` : 'Build a form'} />
            <form onSubmit={submit} className="mx-auto grid max-w-4xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/forms" className="hover:underline">Forms</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{template ? `Edit form (version ${template.version})` : 'Build a form'}</h1>
                    {template && <p className="text-sm text-ink-soft">Changing the questions creates a new version. Forms already filled in keep the questions they were answered against.</p>}
                </header>
                <div className="grid gap-4 sm:grid-cols-[1fr_200px_auto]">
                    <Field label="Form name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="e.g. Pre-pour inspection" />
                    <SelectField label="Type" name="kind" value={form.data.kind} onChange={(v) => form.setData('kind', v)} options={[{ key: 'quality', label: 'Quality' }, { key: 'safety', label: 'Safety' }, { key: 'checklist', label: 'Other checklist' }]} />
                    {template && <label className="flex items-center gap-2 self-end pb-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.active} onChange={(e) => form.setData('active', e.target.checked)} /> In use</label>}
                </div>
                {form.errors.fields && <p className="text-sm text-brick">{form.errors.fields}</p>}

                <ol className="grid gap-2">
                    {form.data.fields.map((q, i) => (
                        <li key={q.id} draggable onDragStart={() => setDragging(i)} onDragOver={(e) => e.preventDefault()} onDrop={(e) => drop(e, i)} onDragEnd={() => setDragging(null)}
                            className={cn('grid gap-3 rounded-[var(--radius-panel)] border bg-surface p-3', dragging === i ? 'border-line opacity-60' : 'border-concrete')}>
                            <div className="grid items-end gap-2 sm:grid-cols-[auto_1fr_220px_auto_auto]">
                                <GripVertical className="mb-2.5 size-5 cursor-grab text-ink-soft" aria-label="Drag to reorder" />
                                <Field label={`Question ${i + 1}`} name={`q${i}`} value={q.label} onChange={(e) => update(i, { label: e.target.value })} placeholder="e.g. Cover to reinforcement at least 50 mm?" />
                                <SelectField label="Answer" name={`t${i}`} value={q.type} onChange={(v) => update(i, { type: v, options: v === 'choice' ? (q.options ?? ['', '']) : undefined })} options={types.map((t) => ({ key: t, label: TYPE_LABEL[t] ?? t }))} />
                                <label className="mb-2.5 flex items-center gap-1.5 text-sm"><input type="checkbox" className="accent-line" checked={q.required} onChange={(e) => update(i, { required: e.target.checked })} /> Required</label>
                                <span className="mb-2 flex gap-1 text-ink-soft">
                                    <button type="button" aria-label="Duplicate" onClick={() => form.setData('fields', [...form.data.fields.slice(0, i + 1), { ...q, id: newId() }, ...form.data.fields.slice(i + 1)])} className="hover:text-ink"><Copy className="size-4" /></button>
                                    <button type="button" aria-label="Remove" onClick={() => form.setData('fields', form.data.fields.filter((_, j) => j !== i))} className="hover:text-brick"><Trash2 className="size-4" /></button>
                                </span>
                            </div>
                            {q.type === 'choice' && (
                                <div className="flex flex-wrap items-center gap-2 pl-8">
                                    {(q.options ?? []).map((o, k) => (
                                        <input key={k} value={o} placeholder={`Choice ${k + 1}`} onChange={(e) => update(i, { options: (q.options ?? []).map((x, m) => (m === k ? e.target.value : x)) })} className="h-9 w-40 rounded-[var(--radius-control)] border border-concrete px-2 text-sm" />
                                    ))}
                                    <button type="button" className="text-sm text-line hover:underline" onClick={() => update(i, { options: [...(q.options ?? []), ''] })}>Add choice</button>
                                </div>
                            )}
                        </li>
                    ))}
                </ol>
                <div className="flex flex-wrap gap-2">
                    <Button type="button" variant="secondary" onClick={() => form.setData('fields', [...form.data.fields, { id: newId(), label: '', type: 'passfail', required: true }])}><Plus className="size-4" /> Add question</Button>
                    <Button type="submit" disabled={form.processing}>{template ? 'Save changes' : 'Save form'}</Button>
                </div>
                <p className="text-xs text-ink-soft">A form fails if any pass/fail question is answered "fail"; failures are highlighted on the project's Forms page.</p>
            </form>
        </>
    );
}

Builder.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
