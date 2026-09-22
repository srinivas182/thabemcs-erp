import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Plus, Trash2 } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDateTime, formatRand, selectClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Line {
    category: string;
    description: string;
    basis: string;
    amount: string | number | null;
    rate: string | number | null;
    start_month: number;
    end_month: number;
}

interface Results {
    revenue: number;
    cost: number;
    profit: number;
    marginOnRevenue: number | null;
    profitOnCost: number | null;
    peakFunding: number;
    peakMonth: number | null;
    irr: number | null;
    byCategory: Record<string, number>;
    monthly: { month: number; in: number; out: number; cumulative: number }[];
}

interface Props {
    project: { id: string; name: string; code: string };
    scenarios: { id: string; name: string; isBaseline: boolean; status: string }[];
    scenario: {
        id: string;
        name: string;
        durationMonths: number;
        units: number | null;
        status: string;
        isBaseline: boolean;
        approvedBy: string | null;
        approvedAt: string | null;
        lines: Line[];
        results: Results;
    } | null;
    categories: Option[];
    bases: Option[];
    can: { edit: boolean; approve: boolean };
}

const pct = (v: number | null) => (v === null ? 'n/a' : `${v.toFixed(1)}%`);

export default function Feasibility({ project, scenarios, scenario, categories, bases, can }: Props) {
    const [creating, setCreating] = useState(scenario === null);

    return (
        <>
            <Head title={`Feasibility: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft">
                        <Link href="/projects" className="hover:underline">Projects</Link> /{' '}
                        <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link>
                    </p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Feasibility</h1>
                    <p className="text-ink-soft">{project.name}. All amounts in rand, excluding VAT.</p>
                </header>

                <div className="flex flex-wrap items-center gap-1.5">
                    {scenarios.map((s) => (
                        <Link
                            key={s.id}
                            href={`/projects/${project.id}/feasibility?scenario=${s.id}`}
                            className={cn('rounded-full border px-3 py-1 text-sm', scenario?.id === s.id ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}
                        >
                            {s.name}
                            {s.isBaseline && ' (baseline)'}
                        </Link>
                    ))}
                    {can.edit && (
                        <button onClick={() => setCreating(!creating)} className="flex items-center gap-1 rounded-full border border-dashed border-ink-soft/50 px-3 py-1 text-sm">
                            <Plus className="size-3.5" /> New scenario
                        </button>
                    )}
                </div>

                {creating && can.edit && <NewScenario projectId={project.id} scenarios={scenarios} onDone={() => setCreating(false)} />}

                {scenario ? (
                    <>
                        <ResultsPanel results={scenario.results} />
                        <CashFlowChart monthly={scenario.results.monthly} peakMonth={scenario.results.peakMonth} />
                        {scenario.status === 'approved' ? (
                            <p className="rounded-[var(--radius-control)] bg-line-wash px-4 py-3 text-sm text-line-deep">
                                Approved as the project baseline by {scenario.approvedBy}, {formatDateTime(scenario.approvedAt)}. It is locked; create a new scenario (copying this one) to model changes.
                            </p>
                        ) : (
                            can.approve && (
                                <div className="flex items-center justify-between gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                                    <p className="text-sm">Approving makes this scenario the baseline for the project's budget and funding requirement.</p>
                                    <Button onClick={() => window.confirm(`Approve "${scenario.name}" as the baseline?`) && router.post(`/feasibilities/${scenario.id}/approve`, {}, { preserveScroll: true })}>
                                        Approve as baseline
                                    </Button>
                                </div>
                            )
                        )}
                        <LinesEditor key={scenario.id} scenario={scenario} categories={categories} bases={bases} editable={can.edit && scenario.status !== 'approved'} />
                    </>
                ) : (
                    !creating && <p className="text-ink-soft">No feasibility yet.</p>
                )}
            </div>
        </>
    );
}

function NewScenario({ projectId, scenarios, onDone }: { projectId: string; scenarios: Props['scenarios']; onDone: () => void }) {
    const form = useForm({ name: scenarios.length === 0 ? 'Base case' : '', duration_months: '24', units: '', copy_from: '' });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, duration_months: Number(d.duration_months), units: d.units ? Number(d.units) : null, copy_from: d.copy_from || null }));
        form.post(`/projects/${projectId}/feasibility`, { onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="grid items-end gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 sm:grid-cols-[1fr_140px_120px_200px_auto]">
            <Field label="Scenario name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="e.g. Slower sales" />
            <Field label="Duration (months)" name="duration_months" type="number" min={6} value={form.data.duration_months} onChange={(e) => form.setData('duration_months', e.target.value)} error={form.errors.duration_months} />
            <Field label="Units" name="units" type="number" min={1} value={form.data.units} onChange={(e) => form.setData('units', e.target.value)} />
            <div className="grid gap-1.5">
                <label htmlFor="copy_from" className="text-sm font-medium">Start from</label>
                <select id="copy_from" className={selectClass} value={form.data.copy_from} onChange={(e) => form.setData('copy_from', e.target.value)}>
                    <option value="">Standard SA template</option>
                    {scenarios.map((s) => (
                        <option key={s.id} value={s.id}>Copy of {s.name}</option>
                    ))}
                </select>
            </div>
            <Button type="submit" disabled={form.processing}>Create</Button>
        </form>
    );
}

function ResultsPanel({ results }: { results: Results }) {
    const figures = [
        { label: 'Total revenue', value: formatRand(results.revenue) },
        { label: 'Total cost', value: formatRand(results.cost) },
        { label: 'Profit', value: formatRand(results.profit), tone: results.profit < 0 ? 'text-brick' : '' },
        { label: 'Margin on revenue', value: pct(results.marginOnRevenue) },
        { label: 'Profit on cost', value: pct(results.profitOnCost) },
        { label: 'Peak funding required', value: formatRand(results.peakFunding), detail: results.peakMonth ? `in month ${results.peakMonth}` : undefined },
        { label: 'IRR (annualised)', value: pct(results.irr) },
    ];

    return (
        <section aria-label="Results" className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete sm:grid-cols-4 lg:grid-cols-7">
            {figures.map((f) => (
                <div key={f.label} className="bg-surface p-3">
                    <p className="text-xs text-ink-soft">{f.label}</p>
                    <p className={cn('mt-1 text-lg font-bold tabular-nums', f.tone)}>{f.value}</p>
                    {f.detail && <p className="text-xs text-ink-soft">{f.detail}</p>}
                </div>
            ))}
        </section>
    );
}

/** Monthly net cash flow (bars) and cumulative position (line). The lowest point is the peak funding requirement. */
function CashFlowChart({ monthly, peakMonth }: { monthly: Results['monthly']; peakMonth: number | null }) {
    if (monthly.length === 0) return null;
    const width = 720;
    const height = 180;
    const pad = 8;
    const values = monthly.flatMap((m) => [m.in - m.out, m.cumulative, 0]);
    const max = Math.max(...values);
    const min = Math.min(...values);
    const range = max - min || 1;
    const y = (v: number) => pad + ((max - v) / range) * (height - pad * 2);
    const step = width / monthly.length;
    const line = monthly.map((m, i) => `${i === 0 ? 'M' : 'L'}${(i + 0.5) * step},${y(m.cumulative)}`).join(' ');

    return (
        <figure className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
            <figcaption className="mb-2 flex flex-wrap justify-between gap-2 text-sm">
                <span className="font-semibold">Cash flow by month</span>
                <span className="text-ink-soft">Bars: net cash in the month. Line: cumulative position.</span>
            </figcaption>
            <svg viewBox={`0 0 ${width} ${height}`} className="h-44 w-full" role="img" aria-label="Monthly cash flow chart">
                <line x1={0} x2={width} y1={y(0)} y2={y(0)} stroke="var(--color-concrete)" />
                {monthly.map((m, i) => {
                    const net = m.in - m.out;
                    return (
                        <rect
                            key={m.month}
                            x={i * step + step * 0.15}
                            width={step * 0.7}
                            y={Math.min(y(net), y(0))}
                            height={Math.max(1, Math.abs(y(net) - y(0)))}
                            fill={net >= 0 ? 'var(--color-line)' : 'var(--color-brick)'}
                            opacity={0.55}
                        >
                            <title>{`Month ${m.month}: ${formatRand(net)}`}</title>
                        </rect>
                    );
                })}
                <path d={line} fill="none" stroke="var(--color-ink)" strokeWidth={2} />
                {peakMonth && <circle cx={(peakMonth - 0.5) * step} cy={y(monthly[peakMonth - 1]?.cumulative ?? 0)} r={4} fill="var(--color-hivis)" stroke="var(--color-ink)" />}
            </svg>
        </figure>
    );
}

function LinesEditor({ scenario, categories, bases, editable }: { scenario: NonNullable<Props['scenario']>; categories: Option[]; bases: Option[]; editable: boolean }) {
    const form = useForm({ duration_months: scenario.durationMonths, units: scenario.units ?? '', lines: scenario.lines });

    function setLine(index: number, patch: Partial<Line>) {
        form.setData('lines', form.data.lines.map((l, i) => (i === index ? { ...l, ...patch } : l)));
    }

    function addLine() {
        form.setData('lines', [...form.data.lines, { category: 'other', description: '', basis: 'amount', amount: 0, rate: null, start_month: 1, end_month: form.data.duration_months }]);
    }

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, units: d.units === '' ? null : Number(d.units) }));
        form.put(`/feasibilities/${scenario.id}`, { preserveScroll: true });
    }

    const input = 'h-9 w-full rounded-[var(--radius-control)] border border-concrete bg-surface px-2 text-sm focus:border-line focus:outline-none disabled:bg-transparent disabled:border-transparent';
    const firstError = Object.entries(form.errors)[0]?.[1];

    return (
        <form onSubmit={save} className="grid gap-4">
            <div className="flex flex-wrap items-end gap-4">
                <h2 className="text-lg font-bold">Costs and revenue</h2>
                <label className="text-sm">
                    Duration (months)
                    <input type="number" min={6} disabled={!editable} value={form.data.duration_months} onChange={(e) => form.setData('duration_months', Number(e.target.value))} className={input + ' ml-2 w-20'} />
                </label>
                <label className="text-sm">
                    Units
                    <input type="number" min={1} disabled={!editable} value={form.data.units} onChange={(e) => form.setData('units', e.target.value)} className={input + ' ml-2 w-20'} />
                </label>
            </div>

            {firstError && <p className="rounded-[var(--radius-control)] bg-brick-wash px-3 py-2 text-sm text-brick">{firstError}</p>}

            <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                <table className="w-full min-w-[860px] text-left text-sm [&_td]:px-2 [&_td]:py-1.5 [&_th]:px-2 [&_th]:py-2 [&_th]:font-semibold [&_th]:text-ink-soft">
                    <thead>
                        <tr>
                            <th className="w-44">Heading</th>
                            <th>Description</th>
                            <th className="w-40">Basis</th>
                            <th className="w-40 text-right">Amount (R) or %</th>
                            <th className="w-20">From</th>
                            <th className="w-20">To</th>
                            <th className="w-10" />
                        </tr>
                    </thead>
                    <tbody>
                        {form.data.lines.map((l, i) => (
                            <tr key={i} className={cn('border-t border-concrete', l.category === 'revenue' && 'bg-line-wash/40')}>
                                <td>
                                    <select disabled={!editable} className={input} value={l.category} onChange={(e) => setLine(i, { category: e.target.value })} aria-label="Heading">
                                        {categories.map((c) => (<option key={c.key} value={c.key}>{c.label}</option>))}
                                    </select>
                                </td>
                                <td>
                                    <input disabled={!editable} className={input} value={l.description} onChange={(e) => setLine(i, { description: e.target.value })} aria-label="Description" />
                                </td>
                                <td>
                                    <select disabled={!editable} className={input} value={l.basis} onChange={(e) => setLine(i, { basis: e.target.value, amount: e.target.value === 'amount' ? 0 : null, rate: e.target.value === 'amount' ? null : 0 })} aria-label="Basis">
                                        {bases.map((b) => (<option key={b.key} value={b.key}>{b.label}</option>))}
                                    </select>
                                </td>
                                <td>
                                    {l.basis === 'amount' ? (
                                        <input disabled={!editable} type="number" min={0} step="1000" className={input + ' text-right tabular-nums'} value={l.amount ?? ''} onChange={(e) => setLine(i, { amount: e.target.value })} aria-label="Amount" />
                                    ) : (
                                        <input disabled={!editable} type="number" min={0} max={100} step="0.1" className={input + ' text-right tabular-nums'} value={l.rate ?? ''} onChange={(e) => setLine(i, { rate: e.target.value })} aria-label="Percentage" />
                                    )}
                                </td>
                                <td><input disabled={!editable} type="number" min={1} className={input} value={l.start_month} onChange={(e) => setLine(i, { start_month: Number(e.target.value) })} aria-label="Start month" /></td>
                                <td><input disabled={!editable} type="number" min={1} className={input} value={l.end_month} onChange={(e) => setLine(i, { end_month: Number(e.target.value) })} aria-label="End month" /></td>
                                <td>
                                    {editable && (
                                        <button type="button" onClick={() => form.setData('lines', form.data.lines.filter((_, j) => j !== i))} className="p-1 text-ink-soft hover:text-brick" aria-label="Remove line">
                                            <Trash2 className="size-4" />
                                        </button>
                                    )}
                                </td>
                            </tr>
                        ))}
                    </tbody>
                </table>
            </div>

            {editable && (
                <div className="flex gap-3">
                    <Button type="button" variant="secondary" onClick={addLine}><Plus className="size-4" /> Add line</Button>
                    <Button type="submit" disabled={form.processing}>{form.processing ? 'Calculating…' : 'Save and recalculate'}</Button>
                </div>
            )}
        </form>
    );
}

Feasibility.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
