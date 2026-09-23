import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Position {
    sourceId: number; source: string; investor: string; contributed: number; capitalOutstanding: number;
    preferredRate: number; preferredOutstanding: number; profitPaid: number; profitSharePercent: number | null; paidToDate: number;
}
interface Line { id: number; investor: string; capital: number; preferred: number; profit: number; total: number }
interface Distribution { id: string; reference: string; declared: string; amount: number; status: string; paid: string | null; notes: string | null; lines: Line[] }
interface Props {
    project: { id: string; name: string; code: string };
    positions: Position[];
    preview: { lines: (Position & { capital: number; preferred: number; profit: number; total: number })[]; capital: number; preferred: number; profit: number; unallocated: number } | null;
    previewAmount: number | null;
    distributions: Distribution[];
    reinvestments: { id: number; investor: string; amount: number; on: string; notes: string | null }[];
    canManage: boolean;
    canApprove: boolean;
}

export default function Distributions({ project, positions, preview, previewAmount, distributions, reinvestments, canManage, canApprove }: Props) {
    const today = new Date().toLocaleDateString('en-CA');
    const [amount, setAmount] = useState(previewAmount ? String(previewAmount) : '');
    const declare = useForm({ amount: '', declared_on: today, notes: '' });

    return (
        <>
            <Head title={`Investor returns: ${project.name}`} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}/closeout`} className="hover:underline">{project.code} close-out</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Investor returns</h1>
                    <p className="text-ink-soft">{project.name}. Cash is applied in order: capital back, then the preferred return, then the remaining profit.</p>
                </header>

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Where each investor stands</h2>
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full min-w-[720px] text-sm [&_td]:px-3 [&_td]:py-2.5 [&_th]:px-3 [&_th]:py-2.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr><th>Investor</th><th className="text-right">Put in</th><th className="text-right">Capital owed</th><th className="text-right">Preferred owed</th><th className="text-right">Paid so far</th></tr></thead>
                            <tbody>
                                {positions.map((p) => (
                                    <tr key={p.sourceId}>
                                        <td>{p.investor}<span className="block text-xs text-ink-soft">{p.source}{p.preferredRate > 0 ? `, ${p.preferredRate}% preferred` : ''}{p.profitSharePercent ? `, ${p.profitSharePercent}% of profit` : ''}</span></td>
                                        <td className="text-right tabular-nums">{formatRand(p.contributed)}</td>
                                        <td className="text-right tabular-nums">{formatRand(p.capitalOutstanding)}</td>
                                        <td className="text-right tabular-nums">{formatRand(p.preferredOutstanding)}</td>
                                        <td className="text-right tabular-nums">{formatRand(p.paidToDate)}</td>
                                    </tr>
                                ))}
                                {positions.length === 0 && <tr><td colSpan={5} className="text-ink-soft">No investors have paid money into this project.</td></tr>}
                            </tbody>
                        </table>
                    </div>
                </section>

                {canManage && positions.length > 0 && (
                    <section className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                        <h2 className="text-lg font-bold">Work out a distribution</h2>
                        <form onSubmit={(e) => { e.preventDefault(); router.get(`/projects/${project.id}/distributions`, { preview: amount }, { preserveState: true }); }} className="flex flex-wrap items-end gap-3">
                            <div className="w-56"><Field label="Cash to distribute (R)" name="preview" type="number" value={amount} onChange={(e) => setAmount(e.target.value)} /></div>
                            <Button type="submit" variant="secondary">Show the split</Button>
                        </form>

                        {preview && (
                            <>
                                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete">
                                    <table className="w-full text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                                        <thead><tr><th>Investor</th><th className="text-right">Capital</th><th className="text-right">Preferred</th><th className="text-right">Profit</th><th className="text-right">Total</th></tr></thead>
                                        <tbody>
                                            {preview.lines.map((l) => (
                                                <tr key={l.sourceId}><td>{l.investor}</td>
                                                    <td className="text-right tabular-nums">{formatRand(l.capital)}</td>
                                                    <td className="text-right tabular-nums">{formatRand(l.preferred)}</td>
                                                    <td className="text-right tabular-nums">{formatRand(l.profit)}</td>
                                                    <td className="text-right font-semibold tabular-nums">{formatRand(l.total)}</td></tr>
                                            ))}
                                            <tr className="bg-plaster font-semibold"><td>Total</td>
                                                <td className="text-right tabular-nums">{formatRand(preview.capital)}</td>
                                                <td className="text-right tabular-nums">{formatRand(preview.preferred)}</td>
                                                <td className="text-right tabular-nums">{formatRand(preview.profit)}</td>
                                                <td className="text-right tabular-nums">{formatRand(preview.capital + preview.preferred + preview.profit)}</td></tr>
                                        </tbody>
                                    </table>
                                </div>
                                {preview.unallocated > 0 && <p className="text-sm text-brick">{formatRand(preview.unallocated)} could not be allocated.</p>}
                                <form onSubmit={(e) => { e.preventDefault(); declare.transform((d) => ({ ...d, amount, notes: d.notes || null })); declare.post(`/projects/${project.id}/distributions`, { preserveScroll: true }); }} className="flex flex-wrap items-end gap-3">
                                    <div className="w-48"><Field label="Declared on" name="declared_on" type="date" value={declare.data.declared_on} onChange={(e) => declare.setData('declared_on', e.target.value)} /></div>
                                    <div className="w-64"><Field label="Notes" name="notes" value={declare.data.notes} onChange={(e) => declare.setData('notes', e.target.value)} /></div>
                                    <Button type="submit" disabled={declare.processing}>Prepare this distribution</Button>
                                </form>
                            </>
                        )}
                    </section>
                )}

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Distributions</h2>
                    {distributions.length === 0 ? <p className="text-ink-soft">None yet.</p> : (
                        <ul className="grid gap-3">
                            {distributions.map((d) => (
                                <li key={d.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                                    <div className="flex flex-wrap items-start justify-between gap-2">
                                        <div>
                                            <p className="font-semibold">{d.reference}: {formatRand(d.amount)}</p>
                                            <p className="text-sm text-ink-soft">Declared {formatDate(d.declared)}{d.paid ? `, paid ${formatDate(d.paid)}` : ''}{d.notes ? ` — ${d.notes}` : ''}</p>
                                        </div>
                                        <div className="flex items-center gap-2">
                                            <span className={cn('text-sm capitalize', d.status === 'paid' && 'text-line-deep')}>{d.status}</span>
                                            {canApprove && d.status === 'draft' && <Button size="sm" onClick={() => router.post(`/distributions/${d.id}/approve`, {}, { preserveScroll: true })}>Approve</Button>}
                                            {canManage && d.status === 'approved' && <Button size="sm" onClick={() => router.post(`/distributions/${d.id}/pay`, { paid_on: today }, { preserveScroll: true })}>Mark paid</Button>}
                                        </div>
                                    </div>
                                    <ul className="mt-2 grid gap-0.5 text-sm">
                                        {d.lines.map((l) => (
                                            <li key={l.id} className="flex justify-between gap-3">
                                                <span>{l.investor}</span>
                                                <span className="tabular-nums">{formatRand(l.total)} <span className="text-ink-soft">(capital {formatRand(l.capital)}, preferred {formatRand(l.preferred)}, profit {formatRand(l.profit)})</span></span>
                                            </li>
                                        ))}
                                    </ul>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>

                {canManage && <Reinvest projectId={project.id} today={today} reinvestments={reinvestments} />}
            </div>
        </>
    );
}

function Reinvest({ projectId, today, reinvestments }: { projectId: string; today: string; reinvestments: Props['reinvestments'] }) {
    const form = useForm({ investor: '', target: '', amount: '', occurred_on: today, notes: '' });
    return (
        <section className="grid gap-3">
            <h2 className="text-lg font-bold">Reinvestment</h2>
            {reinvestments.length > 0 && (
                <ul className="text-sm">
                    {reinvestments.map((r) => <li key={r.id}>{r.investor} put {formatRand(r.amount)} back in on {formatDate(r.on)}{r.notes ? ` — ${r.notes}` : ''}</li>)}
                </ul>
            )}
            <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, notes: d.notes || null })); form.post(`/projects/${projectId}/reinvestments`, { preserveScroll: true, onSuccess: () => form.reset() }); }}
                className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-5">
                <LookupField label="Investor" name="investor" type="investors" value={form.data.investor} onChange={(v) => form.setData('investor', v)} error={form.errors.investor} />
                <LookupField label="Into which funding" name="target" type="funding-sources" value={form.data.target} onChange={(v) => form.setData('target', v)} error={form.errors.target} placeholder="Another project's funding" />
                <Field label="Amount (R)" name="amount" type="number" value={form.data.amount} onChange={(e) => form.setData('amount', e.target.value)} error={form.errors.amount} />
                <Field label="On" name="occurred_on" type="date" value={form.data.occurred_on} onChange={(e) => form.setData('occurred_on', e.target.value)} />
                <Button type="submit" disabled={form.processing}>Record</Button>
            </form>
            <p className="text-xs text-ink-soft">Recording a reinvestment adds the money to the chosen project's funding, so both projects stay right.</p>
        </section>
    );
}

Distributions.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
