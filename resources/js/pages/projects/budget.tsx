import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { STATUS_LABEL } from '@/components/approval-trail';
import { formatRand, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Line { id: number; code: string; description: string; original: number; variations: number; revised: number; committed: number; direct: number; available: number; used: number }
interface Props {
    project: { id: string; name: string; code: string };
    lines: Line[];
    totals: Omit<Line, 'id' | 'code' | 'description' | 'used'>;
    variations: { id: string; reference: string; title: string; code: string; reason: string; amount: number; days: number; status: string; by: string }[];
    instructions: { key: string; label: string }[];
    library: { key: string; label: string }[];
    can: { manage: boolean; vary: boolean };
}

const REASONS = [
    { key: 'client_request', label: 'Client request' }, { key: 'design_change', label: 'Design change' }, { key: 'unforeseen', label: 'Unforeseen site condition' },
    { key: 'error', label: 'Error or omission' }, { key: 'regulatory', label: 'Regulatory requirement' }, { key: 'other', label: 'Other' },
];

function UsageBar({ used }: { used: number }) {
    return (
        <div className="flex items-center gap-2">
            <div className="h-2 w-24 overflow-hidden rounded-full bg-concrete-soft">
                <div className={cn('h-full', used >= 100 ? 'bg-brick' : used >= 80 ? 'bg-hivis' : 'bg-line')} style={{ width: `${Math.min(100, used)}%` }} />
            </div>
            <span className={cn('text-xs tabular-nums', used >= 100 && 'font-semibold text-brick')}>{used}%</span>
        </div>
    );
}

export default function Budget({ project, lines, totals, variations, instructions, library, can }: Props) {
    const [importing, setImporting] = useState(false);
    const upload = useForm<{ file: File | null }>({ file: null });
    const line = useForm({ cost_code_id: '', code: '', description: '', original_amount: '' });
    const vo = useForm({ budget_line_id: '', title: '', description: '', reason: 'client_request', amount: '', time_impact_days: '0', site_instruction_id: '' });

    function raise(e: FormEvent) {
        e.preventDefault();
        vo.transform((d) => ({ ...d, site_instruction_id: d.site_instruction_id || null }));
        vo.post(`/projects/${project.id}/variations`, { preserveScroll: true, onSuccess: () => vo.reset() });
    }

    return (
        <>
            <Head title={`Budget: ${project.name}`} />
            <div className="mx-auto grid max-w-7xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Budget</h1>
                        <p className="text-ink-soft">{project.name}. Excl. VAT. Committed means approved purchase orders; direct means approved invoices without an order.</p>
                    </div>
                    {can.manage && lines.length > 0 && <Button variant="secondary" onClick={() => setImporting(!importing)}>Import from CSV</Button>}
                </header>

                {lines.length === 0 && can.manage && (
                    <div className="flex flex-wrap items-center gap-3 rounded-[var(--radius-panel)] border border-dashed border-concrete p-6">
                        <p className="flex-1">No budget yet. Start from the approved feasibility, or import a bill of quantities.</p>
                        <Button onClick={() => router.post(`/projects/${project.id}/budget/from-feasibility`)}>Create from feasibility</Button>
                        <Button variant="secondary" onClick={() => setImporting(true)}>Import from CSV</Button>
                    </div>
                )}

                {importing && (
                    <form onSubmit={(e) => { e.preventDefault(); upload.post(`/projects/${project.id}/budget/import`, { forceFormData: true, preserveScroll: true, onSuccess: () => setImporting(false) }); }} className="grid gap-2 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 text-sm">
                        <p>Save your BOQ or budget from Excel as CSV. The first row must have the column names <strong>code</strong>, <strong>description</strong> and <strong>amount</strong> (or <strong>quantity</strong> and <strong>rate</strong>). Existing codes are updated.</p>
                        <div className="flex items-center gap-3">
                            <input type="file" accept=".csv" onChange={(e) => upload.setData('file', e.target.files?.[0] ?? null)} aria-label="CSV file" />
                            <Button type="submit" disabled={!upload.data.file || upload.processing}>Import</Button>
                        </div>
                        {upload.errors.file && <p className="text-brick">{upload.errors.file}</p>}
                    </form>
                )}

                {lines.length > 0 && (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full min-w-[900px] text-left text-sm [&_td]:px-3 [&_td]:py-2.5 [&_th]:px-3 [&_th]:py-2.5 [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead>
                                <tr><th>Cost code</th><th className="text-right">Original</th><th className="text-right">Variations</th><th className="text-right">Revised</th><th className="text-right">Committed</th><th className="text-right">Direct</th><th className="text-right">Available</th><th>Used</th></tr>
                            </thead>
                            <tbody>
                                {lines.map((l) => (
                                    <tr key={l.id}>
                                        <td><span className="tabular-nums text-ink-soft">{l.code}</span> {l.description}</td>
                                        <td className="text-right tabular-nums">{formatRand(l.original)}</td>
                                        <td className="text-right tabular-nums">{l.variations ? formatRand(l.variations) : ''}</td>
                                        <td className="text-right tabular-nums">{formatRand(l.revised)}</td>
                                        <td className="text-right tabular-nums">{formatRand(l.committed)}</td>
                                        <td className="text-right tabular-nums">{l.direct ? formatRand(l.direct) : ''}</td>
                                        <td className={cn('text-right tabular-nums', l.available < 0 && 'font-semibold text-brick')}>{formatRand(l.available)}</td>
                                        <td><UsageBar used={l.used} /></td>
                                    </tr>
                                ))}
                                <tr className="font-semibold">
                                    <td>Total</td>
                                    <td className="text-right tabular-nums">{formatRand(totals.original)}</td><td className="text-right tabular-nums">{formatRand(totals.variations)}</td>
                                    <td className="text-right tabular-nums">{formatRand(totals.revised)}</td><td className="text-right tabular-nums">{formatRand(totals.committed)}</td>
                                    <td className="text-right tabular-nums">{formatRand(totals.direct)}</td><td className="text-right tabular-nums">{formatRand(totals.available)}</td><td />
                                </tr>
                            </tbody>
                        </table>
                    </div>
                )}

                {can.manage && lines.length > 0 && (
                    <form onSubmit={(e) => { e.preventDefault(); line.transform((d) => ({ ...d, cost_code_id: d.cost_code_id || null })); line.post(`/projects/${project.id}/budget/lines`, { preserveScroll: true, onSuccess: () => line.reset() }); }} className="grid items-end gap-3 sm:grid-cols-[1.4fr_120px_1fr_200px_auto]">
                        <SelectField label="From the cost code library" name="cost_code_id" value={line.data.cost_code_id} onChange={(v) => line.setData('cost_code_id', v)} options={library} placeholder="Or type a new code" />
                        <Field label="Code" name="code" value={line.data.code} onChange={(e) => line.setData('code', e.target.value)} error={line.errors.code} placeholder="05.04" disabled={!!line.data.cost_code_id} />
                        <Field label="Description" name="description" value={line.data.description} onChange={(e) => line.setData('description', e.target.value)} error={line.errors.description} disabled={!!line.data.cost_code_id} />
                        <Field label="Budget (R)" name="original_amount" type="number" value={line.data.original_amount} onChange={(e) => line.setData('original_amount', e.target.value)} error={line.errors.original_amount} />
                        <Button type="submit" variant="secondary" disabled={line.processing}>Add</Button>
                    </form>
                )}

                <section id="variations" className="grid gap-3 border-t-2 border-ink pt-4">
                    <h2 className="text-lg font-bold">Variation orders</h2>
                    {variations.length === 0 ? <p className="text-ink-soft">No variations yet.</p> : (
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {variations.map((v) => (
                                <li key={v.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                                    <span><span className="font-semibold">{v.reference}</span> {v.title} <span className="text-ink-soft">({v.code}, {REASONS.find((r) => r.key === v.reason)?.label}, {v.by}{v.days ? `, ${v.days > 0 ? '+' : ''}${v.days} days` : ''})</span></span>
                                    <span className="flex items-center gap-3">
                                        <span className={cn('tabular-nums font-semibold', v.amount < 0 && 'text-line-deep')}>{v.amount > 0 ? '+' : ''}{formatRand(v.amount)}</span>
                                        <span className={cn(v.status === 'approved' ? 'text-line-deep' : v.status === 'rejected' ? 'text-brick' : 'text-ink-soft')}>{STATUS_LABEL[v.status] ?? v.status}</span>
                                    </span>
                                </li>
                            ))}
                        </ul>
                    )}
                    {can.vary && lines.length > 0 && (
                        <form onSubmit={raise} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <p className="font-semibold">Raise a variation</p>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Field label="Title" name="title" value={vo.data.title} onChange={(e) => vo.setData('title', e.target.value)} error={vo.errors.title} placeholder="e.g. Upgrade to porcelain floor tiles" />
                                <SelectField label="Cost code" name="budget_line_id" value={vo.data.budget_line_id} onChange={(v) => vo.setData('budget_line_id', v)} options={lines.map((l) => ({ key: String(l.id), label: `${l.code} ${l.description}` }))} placeholder="Choose" error={vo.errors.budget_line_id} />
                                <SelectField label="Reason" name="reason" value={vo.data.reason} onChange={(v) => vo.setData('reason', v)} options={REASONS} />
                            </div>
                            <div className="grid gap-3 sm:grid-cols-3">
                                <Field label="Amount (R, negative for a saving)" name="amount" type="number" value={vo.data.amount} onChange={(e) => vo.setData('amount', e.target.value)} error={vo.errors.amount} />
                                <Field label="Time impact (days)" name="time_impact_days" type="number" value={vo.data.time_impact_days} onChange={(e) => vo.setData('time_impact_days', e.target.value)} />
                                <SelectField label="From site instruction" name="site_instruction_id" value={vo.data.site_instruction_id} onChange={(v) => vo.setData('site_instruction_id', v)} options={instructions} placeholder="None" />
                            </div>
                            <Field label="Description" name="description" value={vo.data.description} onChange={(e) => vo.setData('description', e.target.value)} />
                            <div><Button type="submit" disabled={vo.processing}>Submit for approval</Button></div>
                        </form>
                    )}
                </section>
            </div>
        </>
    );
}

Budget.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
