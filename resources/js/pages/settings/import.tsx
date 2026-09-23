import { Head, useForm } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDateTime, PageHeader, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Type { key: string; label: string; needsProject: boolean; columns: { name: string; label: string; required: boolean }[] }
interface Run { id: string; type: string; file: string; read: number; imported: number; status: string; errors: { row: number; message: string }[] | null; at: string }

export default function Import({ types, runs }: { types: Type[]; runs: Run[] }) {
    const [type, setType] = useState(types[0]?.key ?? '');
    const chosen = types.find((t) => t.key === type);
    const form = useForm<{ type: string; project: string; file: File | null; confirm: boolean }>({ type, project: '', file: null, confirm: false });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, type, project: d.project || null }));
        form.post('/settings/import', { forceFormData: true, preserveScroll: true });
    }

    return (
        <>
            <Head title="Import data" />
            <div className="mx-auto grid max-w-4xl gap-6">
                <PageHeader title="Import opening data" description="Load your existing suppliers, employees, units, budgets and tenants from a spreadsheet. Nothing is saved unless every row is correct." />

                <section className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                    <div className="grid gap-4 sm:grid-cols-2">
                        <SelectField label="What are you importing" name="type" value={type} onChange={(v) => { setType(v); form.setData('project', ''); }} options={types.map((t) => ({ key: t.key, label: t.label }))} />
                        {chosen?.needsProject && <LookupField label="Which project" name="project" type="projects" value={form.data.project} onChange={(v) => form.setData('project', v)} error={form.errors.project} />}
                    </div>

                    {chosen && (
                        <div className="text-sm">
                            <p className="font-medium">Columns in the file</p>
                            <ul className="mt-1 grid gap-0.5 sm:grid-cols-2">
                                {chosen.columns.map((c) => <li key={c.name}><code className="text-xs">{c.name}</code> — {c.label}{c.required && <span className="text-brick"> (needed)</span>}</li>)}
                            </ul>
                            <Button variant="ghost" size="sm" className="mt-2" asChild><a href={`/settings/import/${chosen.key}/template`}>Download the template</a></Button>
                        </div>
                    )}

                    <form onSubmit={submit} className="grid gap-3 border-t border-concrete pt-4">
                        <input type="file" accept=".csv,text/csv" onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm" aria-label="CSV file" />
                        {form.errors.file && <p className="text-sm text-brick">{form.errors.file}</p>}
                        <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.confirm} onChange={(e) => form.setData('confirm', e.target.checked)} /> Import them (leave unticked to check the file first)</label>
                        <div><Button type="submit" disabled={!form.data.file || form.processing}>{form.data.confirm ? 'Import' : 'Check the file'}</Button></div>
                    </form>
                </section>

                {runs.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-lg font-bold">Recent imports</h2>
                        <ul className="grid gap-2">
                            {runs.map((r) => (
                                <li key={r.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-3 text-sm', r.status === 'failed' ? 'border-brick' : 'border-concrete')}>
                                    <p><span className="font-semibold">{r.file}</span> — {r.type}, {r.read} rows read, {r.imported} imported <span className="text-ink-soft">({formatDateTime(r.at)})</span></p>
                                    {r.errors && r.errors.length > 0 && (
                                        <ul className="mt-1 text-brick">
                                            {r.errors.slice(0, 8).map((e, i) => <li key={i}>Row {e.row}: {e.message}</li>)}
                                            {r.errors.length > 8 && <li>…and {r.errors.length - 8} more</li>}
                                        </ul>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

Import.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
