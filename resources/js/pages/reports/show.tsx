import { Head, Link, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import { Download, Printer } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Column = { key: string; label: string; type: 'text' | 'money' | 'number' | 'percent' | 'date' };
type Row = Record<string, string | number | null>;
interface Props {
    report: { key: string; title: string; description: string; filters: ('project' | 'period' | 'as_at')[] };
    result: { title: string; subtitle: string; columns: Column[]; rows: Row[]; totals: Row | null; note: string | null };
    values: { project: string | null; from: string; to: string; as_at: string };
    projects: { key: string; label: string }[];
}

function cell(value: string | number | null | undefined, type: Column['type']): string {
    if (value === null || value === undefined || value === '') return '';
    if (type === 'money') return formatRand(Number(value));
    if (type === 'percent') return `${value}%`;
    if (type === 'number') return Number(value).toLocaleString('en-ZA');
    if (type === 'date') return formatDate(String(value));
    return String(value);
}

export default function ReportShow({ report, result, values, projects }: Props) {
    const [f, setF] = useState(values);
    const query = new URLSearchParams(Object.entries(f).filter(([, v]) => v) as [string, string][]).toString();
    const input = 'h-10 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm';

    return (
        <>
            <Head title={result.title} />
            <div className="mx-auto grid max-w-7xl gap-5 print:max-w-none">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft print:hidden"><Link href="/reports" className="hover:underline">Reports</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{result.title}</h1>
                        <p className="text-ink-soft">{result.subtitle}</p>
                    </div>
                    <div className="flex flex-wrap gap-2 print:hidden">
                        <Button variant="secondary" onClick={() => window.print()}><Printer className="size-4" /> Print or save as PDF</Button>
                        <Button variant="secondary" asChild><a href={`/reports/${report.key}/download/xlsx?${query}`}><Download className="size-4" /> Excel</a></Button>
                        <Button variant="ghost" asChild><a href={`/reports/${report.key}/download/csv?${query}`}>CSV</a></Button>
                    </div>
                </header>

                <form onSubmit={(e) => { e.preventDefault(); router.get(`/reports/${report.key}`, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true }); }} className="flex flex-wrap items-end gap-3 print:hidden">
                    {report.filters.includes('project') && (
                        <label className="grid gap-1 text-sm font-medium">Project
                            <select className={input} value={f.project ?? ''} onChange={(e) => setF({ ...f, project: e.target.value || null })}>
                                <option value="">All projects</option>
                                {projects.map((p) => <option key={p.key} value={p.key}>{p.label}</option>)}
                            </select>
                        </label>
                    )}
                    {report.filters.includes('period') && (
                        <>
                            <label className="grid gap-1 text-sm font-medium">From<input type="date" className={input} value={f.from} onChange={(e) => setF({ ...f, from: e.target.value })} /></label>
                            <label className="grid gap-1 text-sm font-medium">To<input type="date" className={input} value={f.to} onChange={(e) => setF({ ...f, to: e.target.value })} /></label>
                        </>
                    )}
                    {report.filters.includes('as_at') && <label className="grid gap-1 text-sm font-medium">As at<input type="date" className={input} value={f.as_at} onChange={(e) => setF({ ...f, as_at: e.target.value })} /></label>}
                    {report.filters.length > 0 && <Button type="submit">Update</Button>}
                </form>

                {result.rows.length === 0 ? <p className="text-ink-soft">Nothing to report for these filters.</p> : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface print:overflow-visible print:border-0">
                        <table className="w-full text-left text-sm print:text-[10px] [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr>{result.columns.map((c) => <th key={c.key} className={cn(c.type !== 'text' && c.type !== 'date' && 'text-right')}>{c.label}</th>)}</tr></thead>
                            <tbody>
                                {result.rows.map((r, i) => (
                                    <tr key={i} className="break-inside-avoid">{result.columns.map((c) => <td key={c.key} className={cn(c.type !== 'text' && c.type !== 'date' && 'text-right tabular-nums')}>{cell(r[c.key], c.type)}</td>)}</tr>
                                ))}
                                {result.totals && (
                                    <tr className="font-semibold">{result.columns.map((c) => <td key={c.key} className={cn(c.type !== 'text' && c.type !== 'date' && 'text-right tabular-nums')}>{cell(result.totals?.[c.key], c.type)}</td>)}</tr>
                                )}
                            </tbody>
                        </table>
                    </div>
                )}
                {result.note && <p className="text-sm text-ink-soft">{result.note}</p>}
                <p className="hidden text-xs text-ink-soft print:block">Printed {new Date().toLocaleString('en-ZA')} from Thabekhulu Development Software.</p>
            </div>
        </>
    );
}

ReportShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
