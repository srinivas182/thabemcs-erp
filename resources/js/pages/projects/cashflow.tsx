import { Head, Link } from '@inertiajs/react';
import type { ReactNode } from 'react';
import { formatRand, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Month { month: string; forecast: number; actual: number; forecastCumulative: number; actualCumulative: number }
interface Props { project: { id: string; name: string; code: string }; start: string | null; months: Month[]; baseline: string | null }

const label = (ym: string) => new Date(`${ym}-01T00:00:00`).toLocaleDateString('en-ZA', { month: 'short', year: '2-digit' });

/** Cumulative S-curve: forecast (dashed) against actual spend. */
function SCurve({ months }: { months: Month[] }) {
    const w = 720; const h = 220; const pad = 36;
    const max = Math.max(1, ...months.map((m) => Math.max(m.forecastCumulative, m.actualCumulative)));
    const x = (i: number) => pad + (i * (w - pad * 2)) / Math.max(1, months.length - 1);
    const y = (v: number) => h - pad - (v / max) * (h - pad * 2);
    const path = (key: 'forecastCumulative' | 'actualCumulative', upto = months.length) => months.slice(0, upto).map((m, i) => `${i ? 'L' : 'M'}${x(i).toFixed(1)},${y(m[key]).toFixed(1)}`).join(' ');
    const lastActual = months.reduce((n, m, i) => (m.actual > 0 ? i + 1 : n), 0);
    return (
        <svg viewBox={`0 0 ${w} ${h}`} className="w-full" role="img" aria-label="Cumulative forecast and actual spend">
            <line x1={pad} x2={w - pad} y1={h - pad} y2={h - pad} className="stroke-concrete" />
            <path d={path('forecastCumulative')} fill="none" strokeDasharray="6 4" strokeWidth={2} className="stroke-ink-soft" />
            {lastActual > 0 && <path d={path('actualCumulative', lastActual)} fill="none" strokeWidth={3} className="stroke-line" />}
            {months.map((m, i) => (i % Math.ceil(months.length / 8) === 0 ? <text key={m.month} x={x(i)} y={h - 12} textAnchor="middle" className="fill-ink-soft text-[10px]">{label(m.month)}</text> : null))}
            <text x={pad} y={16} className="fill-ink-soft text-[10px]">{formatRand(max)}</text>
        </svg>
    );
}

export default function CashFlow({ project, start, months, baseline }: Props) {
    const last = months.at(-1);
    return (
        <>
            <Head title={`Cash flow: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Cash flow</h1>
                    <p className="text-ink-soft">{baseline ? `Forecast from the approved feasibility "${baseline}", starting ${start}.` : 'No approved feasibility, so there is no forecast yet.'} Actual is supplier invoices paid, excl. VAT.</p>
                </header>
                {months.length === 0 ? <p className="text-ink-soft">Nothing to show yet.</p> : (
                    <>
                        <div className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <SCurve months={months} />
                            <p className="mt-2 flex gap-4 text-sm"><span><span className="mr-1 inline-block h-0.5 w-5 bg-line align-middle" /> Actual</span><span><span className="mr-1 inline-block h-0.5 w-5 border-t-2 border-dashed border-ink-soft align-middle" /> Forecast</span></p>
                        </div>
                        {last && <p>To date: <strong>{formatRand(last.actualCumulative)}</strong> paid against a forecast of <strong>{formatRand(months.filter((m) => m.month <= new Date().toISOString().slice(0, 7)).at(-1)?.forecastCumulative ?? 0)}</strong> by this month.</p>}
                        <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                            <table className={tableClass}>
                                <thead><tr><th>Month</th><th className="text-right">Forecast</th><th className="text-right">Actual</th><th className="text-right">Forecast to date</th><th className="text-right">Actual to date</th></tr></thead>
                                <tbody>{months.map((m) => (<tr key={m.month}><td>{label(m.month)}</td><td className="text-right tabular-nums">{formatRand(m.forecast)}</td><td className="text-right tabular-nums">{m.actual ? formatRand(m.actual) : ''}</td><td className="text-right tabular-nums">{formatRand(m.forecastCumulative)}</td><td className="text-right tabular-nums">{formatRand(m.actualCumulative)}</td></tr>))}</tbody>
                            </table>
                        </div>
                    </>
                )}
            </div>
        </>
    );
}

CashFlow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
