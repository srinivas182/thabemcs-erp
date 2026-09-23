import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { STATUS_LABEL } from '@/components/approval-trail';
import { formatDate, formatRand, PageHeader, Pager, SelectField, tableClass } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

interface Row { id: string; reference: string; title: string; project: string; status: string; total: number; quotes: number; neededBy: string | null; by: string }
type Line = { description: string; quantity: string; unit: string; estimated_unit_price: string };

export default function Requisitions({ requisitions, filter, units, canRaise }: { requisitions: Paginated<Row>; filter: string; units: { key: string; label: string }[]; canRaise: boolean }) {
    const [adding, setAdding] = useState(false);
    const blank: Line = { description: '', quantity: '1', unit: 'each', estimated_unit_price: '' };
    const form = useForm<{ project: string; title: string; needed_by: string; notes: string; lines: Line[]; submit: boolean }>({ project: '', title: '', needed_by: '', notes: '', lines: [blank], submit: true });
    const total = form.data.lines.reduce((s, l) => s + Number(l.quantity || 0) * Number(l.estimated_unit_price || 0), 0);
    const setLine = (i: number, patch: Partial<Line>) => form.setData('lines', form.data.lines.map((l, j) => (j === i ? { ...l, ...patch } : l)));

    function submit(e: FormEvent, send: boolean) {
        e.preventDefault();
        form.transform((d) => ({ ...d, submit: send, needed_by: d.needed_by || null }));
        form.post('/requisitions');
    }

    return (
        <>
            <Head title="Requisitions" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader title="Requisitions" description="Ask to buy something for a project. Once approved, Procurement gets quotes and raises the purchase order."
                    action={<div className="flex gap-2"><Button variant="secondary" asChild><Link href="/purchase-orders">Purchase orders</Link></Button>{canRaise && <Button onClick={() => setAdding(!adding)}>New requisition</Button>}</div>} />

                {adding && (
                    <form onSubmit={(e) => submit(e, true)} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <LookupField label="Project" name="project" value={form.data.project} onChange={(v) => form.setData('project', v)} type="projects" params={{ status: 'active' }} placeholder="Choose" error={form.errors.project} />
                            <Field label="What is needed" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} error={form.errors.title} placeholder="e.g. Cement and aggregate for Block C slab" />
                            <Field label="Needed by" name="needed_by" type="date" value={form.data.needed_by} onChange={(e) => form.setData('needed_by', e.target.value)} error={form.errors.needed_by} />
                        </div>
                        <div className="grid gap-2">
                            {form.data.lines.map((l, i) => (
                                <div key={i} className="grid items-end gap-2 sm:grid-cols-[1fr_100px_100px_160px_auto]">
                                    <Field label={i === 0 ? 'Item' : ''} aria-label="Item" name={`lines.${i}.description`} value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} error={form.errors[`lines.${i}.description` as 'title']} />
                                    <Field label={i === 0 ? 'Qty' : ''} aria-label="Quantity" name={`lines.${i}.quantity`} type="number" value={l.quantity} onChange={(e) => setLine(i, { quantity: e.target.value })} />
                                    <SelectField label={i === 0 ? 'Unit' : ''} name={`lines.${i}.unit`} value={l.unit} onChange={(v) => setLine(i, { unit: v })} options={units} />
                                    <Field label={i === 0 ? 'Estimated price (R)' : ''} aria-label="Estimated unit price" name={`lines.${i}.estimated_unit_price`} type="number" value={l.estimated_unit_price} onChange={(e) => setLine(i, { estimated_unit_price: e.target.value })} />
                                    <button type="button" className="mb-2 p-1 text-ink-soft hover:text-brick disabled:opacity-30" disabled={form.data.lines.length === 1} onClick={() => form.setData('lines', form.data.lines.filter((_, j) => j !== i))} aria-label="Remove line"><Trash2 className="size-4" /></button>
                                </div>
                            ))}
                            <div className="flex items-center justify-between">
                                <Button type="button" variant="ghost" size="sm" onClick={() => form.setData('lines', [...form.data.lines, blank])}><Plus className="size-4" /> Add item</Button>
                                <p className="text-sm">Estimated total <span className="font-semibold tabular-nums">{formatRand(total)}</span> excl. VAT</p>
                            </div>
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>Submit for approval</Button>
                            <Button type="button" variant="secondary" disabled={form.processing} onClick={(e) => submit(e as unknown as FormEvent, false)}>Save draft</Button>
                        </div>
                    </form>
                )}

                <div className="flex flex-wrap gap-1.5">
                    {['', 'draft', 'submitted', 'approved', 'awarded', 'rejected'].map((s) => (
                        <button key={s} onClick={() => router.get('/requisitions', s ? { status: s } : {})} className={cn('rounded-full border px-3 py-1 text-sm', filter === s ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>
                            {s ? STATUS_LABEL[s] : 'All'}
                        </button>
                    ))}
                </div>

                {requisitions.data.length === 0 ? <p className="text-ink-soft">No requisitions.</p> : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Requisition</th><th>Project</th><th>Status</th><th className="text-right">Estimate</th><th className="text-right">Quotes</th></tr></thead>
                            <tbody>
                                {requisitions.data.map((r) => (
                                    <tr key={r.id} className="hover:bg-plaster">
                                        <td><Link href={`/requisitions/${r.id}`} className="font-semibold hover:underline">{r.reference} {r.title}</Link><p className="text-ink-soft">{r.by}{r.neededBy && `, needed ${formatDate(r.neededBy)}`}</p></td>
                                        <td>{r.project}</td>
                                        <td>{STATUS_LABEL[r.status] ?? r.status}</td>
                                        <td className="text-right tabular-nums">{formatRand(r.total)}</td>
                                        <td className="text-right tabular-nums">{r.quotes}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
                <Pager prev={requisitions.prev_page_url} next={requisitions.next_page_url} page={requisitions.current_page} last={requisitions.last_page} />
            </div>
        </>
    );
}

Requisitions.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
