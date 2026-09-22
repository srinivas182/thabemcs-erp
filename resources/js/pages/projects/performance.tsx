import { Head, Link } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatRand } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Money { revenue: number; cost: number; profit: number; margin: number | null }
interface Props {
    project: { id: string; name: string; code: string };
    evm: { bac: number; pv: number; ev: number; ac: number; spi: number | null; cpi: number | null; eac: number | null; vac: number | null; curve: { month: string; pv: number }[]; weighting: string };
    history: { date: string; pv: number; ev: number; ac: number }[];
    profitability: { hasBaseline: boolean; baseline: Money | null; forecast: Money; costToDate: number; overruns: { code: string; description: string; over: number }[] };
}

function Index({ label, value, help }: { label: string; value: number | null; help: string }) {
    const tone = value === null ? '' : value >= 1 ? 'text-line-deep' : value >= 0.9 ? 'text-ink' : 'text-brick';
    return (
        <div className="bg-surface p-4">
            <p className="text-xs text-ink-soft">{label}</p>
            <p className={cn('mt-1 text-3xl font-bold tabular-nums', tone)}>{value ?? '–'}</p>
            <p className="mt-1 text-xs text-ink-soft">{help}</p>
        </div>
    );
}

/** Planned value S-curve (dashed) with recorded earned value and actual cost points. */
function Curve({ curve, history, ev, ac }: { curve: Props['evm']['curve']; history: Props['history']; ev: number; ac: number }) {
    const w = 720; const h = 230; const pad = 40;
    if (curve.length === 0) return null;
    const max = Math.max(1, ...curve.map((c) => c.pv), ...history.map((s) => Math.max(s.ev, s.ac)), ev, ac);
    const first = new Date(`${curve[0]?.month}-01`).getTime();
    const last = new Date(`${curve.at(-1)?.month}-28`).getTime();
    const x = (t: number) => pad + ((t - first) / Math.max(1, last - first)) * (w - pad * 2);
    const y = (v: number) => h - pad - (v / max) * (h - pad * 2);
    const pvPath = curve.map((c, i) => `${i ? 'L' : 'M'}${x(new Date(`${c.month}-28`).getTime()).toFixed(1)},${y(c.pv).toFixed(1)}`).join(' ');
    const line = (key: 'ev' | 'ac') => history.map((s, i) => `${i ? 'L' : 'M'}${x(new Date(s.date).getTime()).toFixed(1)},${y(s[key]).toFixed(1)}`).join(' ');
    const now = Date.now();
    return (
        <svg viewBox={`0 0 ${w} ${h}`} className="w-full" role="img" aria-label="Earned value S-curve">
            <line x1={pad} x2={w - pad} y1={h - pad} y2={h - pad} className="stroke-concrete" />
            <path d={pvPath} fill="none" strokeDasharray="6 4" strokeWidth={2} className="stroke-ink-soft" />
            {history.length > 1 && <path d={line('ev')} fill="none" strokeWidth={3} className="stroke-line" />}
            {history.length > 1 && <path d={line('ac')} fill="none" strokeWidth={2} className="stroke-brick" />}
            {now >= first && now <= last && (
                <>
                    <circle cx={x(now)} cy={y(ev)} r={5} className="fill-line" />
                    <circle cx={x(now)} cy={y(ac)} r={5} className="fill-brick" />
                </>
            )}
            <text x={pad} y={16} className="fill-ink-soft text-[10px]">{formatRand(max)}</text>
        </svg>
    );
}

export default function Performance({ project, evm, history, profitability: p }: Props) {
    return (
        <>
            <Head title={`Performance: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-8">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Performance</h1>
                    <p className="text-ink-soft">{project.name}. Earned value compares the work planned, the work done and what it cost (excl. VAT).</p>
                </header>

                <section className="grid gap-4">
                    <h2 className="text-lg font-bold">Earned value</h2>
                    {evm.bac === 0 ? <p className="text-ink-soft">Set up the budget and the programme to see earned value.</p> : (
                        <>
                            <div className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete md:grid-cols-4">
                                <Index label="Schedule performance (SPI)" value={evm.spi} help="Work done ÷ work planned by today. Below 1 means behind." />
                                <Index label="Cost performance (CPI)" value={evm.cpi} help="Value of work done ÷ what it cost. Below 1 means over budget." />
                                <div className="bg-surface p-4"><p className="text-xs text-ink-soft">Forecast final cost (EAC)</p><p className="mt-1 text-2xl font-bold tabular-nums">{evm.eac !== null ? formatRand(evm.eac) : '–'}</p><p className="mt-1 text-xs text-ink-soft">Budget {formatRand(evm.bac)}</p></div>
                                <div className="bg-surface p-4"><p className="text-xs text-ink-soft">Variance at completion</p><p className={cn('mt-1 text-2xl font-bold tabular-nums', (evm.vac ?? 0) < 0 && 'text-brick')}>{evm.vac !== null ? formatRand(evm.vac) : '–'}</p><p className="mt-1 text-xs text-ink-soft">Negative means an overrun</p></div>
                            </div>
                            <div className="grid gap-2 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                                <Curve curve={evm.curve} history={history} ev={evm.ev} ac={evm.ac} />
                                <p className="flex flex-wrap gap-4 text-sm">
                                    <span>Planned (dashed): <strong>{formatRand(evm.pv)}</strong> by today</span>
                                    <span className="text-line-deep">Earned: <strong>{formatRand(evm.ev)}</strong></span>
                                    <span className="text-brick">Actual cost: <strong>{formatRand(evm.ac)}</strong></span>
                                </p>
                                <p className="text-xs text-ink-soft">Budget is spread over activities by {evm.weighting}. Earned value uses the progress recorded on the programme; actual cost is approved supplier invoices. A reading is saved every Monday for the history lines.</p>
                            </div>
                        </>
                    )}
                </section>

                <section className="grid gap-4 border-t-2 border-ink pt-4">
                    <h2 className="text-lg font-bold">Profitability</h2>
                    {!p.hasBaseline ? <p className="text-ink-soft">Approve a feasibility baseline to compare profit.</p> : (
                        <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                            <table className="w-full text-sm [&_td]:px-4 [&_td]:py-2.5 [&_th]:px-4 [&_th]:py-2.5 [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                                <thead><tr><th className="text-left" /><th className="text-right">Approved feasibility</th><th className="text-right">Forecast now</th><th className="text-right">Change</th></tr></thead>
                                <tbody>
                                    {(['revenue', 'cost', 'profit'] as const).map((k) => {
                                        const change = p.forecast[k] - (p.baseline?.[k] ?? 0);
                                        const bad = k === 'cost' ? change > 0 : change < 0;
                                        return (
                                            <tr key={k}>
                                                <td className="font-medium capitalize">{k === 'cost' ? 'Total cost' : k}</td>
                                                <td className="text-right tabular-nums">{formatRand(p.baseline?.[k] ?? 0)}</td>
                                                <td className="text-right tabular-nums">{formatRand(p.forecast[k])}</td>
                                                <td className={cn('text-right tabular-nums', change !== 0 && (bad ? 'text-brick' : 'text-line-deep'))}>{change > 0 ? '+' : ''}{formatRand(change)}</td>
                                            </tr>
                                        );
                                    })}
                                    <tr><td className="font-medium">Margin on revenue</td><td className="text-right tabular-nums">{p.baseline?.margin ?? '–'}%</td><td className="text-right tabular-nums">{p.forecast.margin ?? '–'}%</td><td /></tr>
                                </tbody>
                            </table>
                        </div>
                    )}
                    <p className="text-sm text-ink-soft">Paid to date: {formatRand(p.costToDate)}. Forecast cost uses each cost code's revised budget, or what is already committed where that is higher. Revenue comes from the feasibility until sales are recorded.</p>
                    {p.overruns.length > 0 && (
                        <div className="rounded-[var(--radius-panel)] bg-brick-wash p-4 text-sm text-brick">
                            <p className="font-semibold">Cost codes already over budget</p>
                            <ul className="mt-1">{p.overruns.map((o) => <li key={o.code}>{o.code} {o.description}: {formatRand(o.over)} over</li>)}</ul>
                        </div>
                    )}
                </section>
            </div>
        </>
    );
}

Performance.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
