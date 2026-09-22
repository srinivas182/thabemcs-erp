import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { ArrowDown, ArrowUp, Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useMemo } from 'react';
import { SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type FieldDef = { key: string; label: string; type: string };
interface Props {
    datasets: { key: string; label: string; fields: FieldDef[] }[];
    report: { id: number; name: string; dataset: string; config: { columns: string[]; filters?: { field: string; op: string; value: string }[]; sort?: string | null; direction?: string; totals?: boolean; subtitle?: string | null } } | null;
}
type Filter = { field: string; op: string; value: string };

const OPS = [{ key: 'eq', label: 'is' }, { key: 'ne', label: 'is not' }, { key: 'contains', label: 'contains' }, { key: 'gte', label: 'is at least' }, { key: 'lte', label: 'is at most' }];

export default function Designer({ datasets, report }: Props) {
    const form = useForm<{ name: string; dataset: string; columns: string[]; filters: Filter[]; sort: string; direction: string; totals: boolean; subtitle: string }>({
        name: report?.name ?? '', dataset: report?.dataset ?? datasets[0]?.key ?? '', columns: report?.config.columns ?? [],
        filters: report?.config.filters ?? [], sort: report?.config.sort ?? '', direction: report?.config.direction ?? 'asc',
        totals: report?.config.totals ?? true, subtitle: report?.config.subtitle ?? '',
    });
    const fields = useMemo(() => datasets.find((d) => d.key === form.data.dataset)?.fields ?? [], [datasets, form.data.dataset]);
    const label = (k: string) => fields.find((f) => f.key === k)?.label ?? k;
    const move = (i: number, by: number) => {
        const cols = [...form.data.columns]; const j = i + by;
        if (j < 0 || j >= cols.length) return;
        [cols[i], cols[j]] = [cols[j]!, cols[i]!];
        form.setData('columns', cols);
    };

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, sort: d.sort || null, subtitle: d.subtitle || null }));
        if (report) form.put(`/reports/designer/${report.id}`); else form.post('/reports/designer');
    }

    return (
        <>
            <Head title={report ? `Edit ${report.name}` : 'Design a report'} />
            <form onSubmit={submit} className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/reports" className="hover:underline">Reports</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{report ? 'Edit report' : 'Design a report'}</h1>
                    <p className="text-ink-soft">Choose what to report on, the columns and their order, any filters, and how to sort. Saved reports can be printed, exported and scheduled like the standard ones.</p>
                </header>

                <div className="grid gap-4 sm:grid-cols-2">
                    <Field label="Report name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="e.g. Open orders over R50 000" />
                    <SelectField label="Report on" name="dataset" value={form.data.dataset} onChange={(v) => form.setData((d) => ({ ...d, dataset: v, columns: [], filters: [], sort: '' }))} options={datasets.map((d) => ({ key: d.key, label: d.label }))} />
                </div>

                <section className="grid gap-4 lg:grid-cols-2">
                    <div className="grid content-start gap-2">
                        <p className="font-semibold">Available fields</p>
                        <div className="flex flex-wrap gap-2">
                            {fields.filter((f) => !form.data.columns.includes(f.key)).map((f) => (
                                <button type="button" key={f.key} onClick={() => form.setData('columns', [...form.data.columns, f.key])} className="flex items-center gap-1 rounded-full border border-concrete bg-surface px-3 py-1 text-sm hover:border-line">
                                    <Plus className="size-3.5" /> {f.label}
                                </button>
                            ))}
                        </div>
                    </div>
                    <div className="grid content-start gap-2">
                        <p className="font-semibold">Columns in the report</p>
                        {form.data.columns.length === 0 && <p className="text-sm text-ink-soft">Click fields on the left to add them.</p>}
                        {form.errors.columns && <p className="text-sm text-brick">{form.errors.columns}</p>}
                        <ol className="grid gap-1">
                            {form.data.columns.map((c, i) => (
                                <li key={c} className="flex items-center justify-between rounded-[var(--radius-control)] border border-concrete bg-surface px-3 py-1.5 text-sm">
                                    <span>{i + 1}. {label(c)}</span>
                                    <span className="flex gap-1 text-ink-soft">
                                        <button type="button" onClick={() => move(i, -1)} aria-label="Move up" className="hover:text-ink"><ArrowUp className="size-4" /></button>
                                        <button type="button" onClick={() => move(i, 1)} aria-label="Move down" className="hover:text-ink"><ArrowDown className="size-4" /></button>
                                        <button type="button" onClick={() => form.setData('columns', form.data.columns.filter((x) => x !== c))} aria-label="Remove" className="hover:text-brick"><Trash2 className="size-4" /></button>
                                    </span>
                                </li>
                            ))}
                        </ol>
                    </div>
                </section>

                <section className="grid gap-2">
                    <p className="font-semibold">Only include rows where</p>
                    {form.data.filters.map((f, i) => (
                        <div key={i} className="grid items-end gap-2 sm:grid-cols-[1fr_160px_1fr_auto]">
                            <SelectField label="" name={`f${i}`} value={f.field} onChange={(v) => form.setData('filters', form.data.filters.map((x, j) => (j === i ? { ...x, field: v } : x)))} options={fields.map((d) => ({ key: d.key, label: d.label }))} />
                            <SelectField label="" name={`o${i}`} value={f.op} onChange={(v) => form.setData('filters', form.data.filters.map((x, j) => (j === i ? { ...x, op: v } : x)))} options={OPS} />
                            <Field label="" aria-label="Value" name={`v${i}`} value={f.value} onChange={(e) => form.setData('filters', form.data.filters.map((x, j) => (j === i ? { ...x, value: e.target.value } : x)))} />
                            <button type="button" onClick={() => form.setData('filters', form.data.filters.filter((_, j) => j !== i))} className="mb-2 text-ink-soft hover:text-brick" aria-label="Remove filter"><Trash2 className="size-4" /></button>
                        </div>
                    ))}
                    <Button type="button" variant="ghost" size="sm" className="justify-self-start" onClick={() => form.setData('filters', [...form.data.filters, { field: fields[0]?.key ?? '', op: 'eq', value: '' }])}><Plus className="size-4" /> Add a condition</Button>
                </section>

                <section className="grid gap-4 sm:grid-cols-4">
                    <SelectField label="Sort by" name="sort" value={form.data.sort} onChange={(v) => form.setData('sort', v)} options={fields.map((d) => ({ key: d.key, label: d.label }))} placeholder="Default order" />
                    <SelectField label="Direction" name="direction" value={form.data.direction} onChange={(v) => form.setData('direction', v)} options={[{ key: 'asc', label: 'A to Z, low to high' }, { key: 'desc', label: 'Z to A, high to low' }]} />
                    <Field label="Subtitle (optional)" name="subtitle" value={form.data.subtitle} onChange={(e) => form.setData('subtitle', e.target.value)} />
                    <label className={cn('flex items-center gap-2 self-end pb-2 text-sm')}><input type="checkbox" className="size-4 accent-line" checked={form.data.totals} onChange={(e) => form.setData('totals', e.target.checked)} /> Show totals</label>
                </section>

                <div className="flex flex-wrap gap-3">
                    <Button type="submit" disabled={form.processing}>{report ? 'Save and run' : 'Save and run report'}</Button>
                    {report && <Button type="button" variant="ghost" className="text-brick" onClick={() => window.confirm(`Delete "${report.name}"? Any schedules for it stop.`) && router.delete(`/reports/designer/${report.id}`)}>Delete report</Button>}
                </div>
            </form>
        </>
    );
}

Designer.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
