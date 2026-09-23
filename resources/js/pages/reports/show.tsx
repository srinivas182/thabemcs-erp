import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Download, Printer } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type Column = { key: string; label: string; type: 'text' | 'money' | 'number' | 'percent' | 'date' };
type Row = Record<string, string | number | null>;
interface Letterhead { company: string; registration: string | null; vat: string | null; address: string | null; contact: string | null; logoUrl: string | null; footer: string | null }
interface Preset { id: number; name: string; columns: string[]; isDefault: boolean }
interface Props {
    letterhead: Letterhead;
    presets: Preset[];
    presetId: number | null;
    report: { key: string; title: string; description: string; filters: ('project' | 'period' | 'as_at')[] };
    result: { title: string; subtitle: string; columns: Column[]; rows: Row[]; totals: Row | null; note: string | null };
    values: { project: string | null; from: string; to: string; as_at: string };
}

function cell(value: string | number | null | undefined, type: Column['type']): string {
    if (value === null || value === undefined || value === '') return '';
    if (type === 'money') return formatRand(Number(value));
    if (type === 'percent') return `${value}%`;
    if (type === 'number') return Number(value).toLocaleString('en-ZA');
    if (type === 'date') return formatDate(String(value));
    return String(value);
}

export default function ReportShow({ report, result, values, letterhead, presets, presetId }: Props) {
    const [f, setF] = useState(values);
    const query = new URLSearchParams(Object.entries(f).filter(([, v]) => v) as [string, string][]).toString();
    const input = 'h-10 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm';

    return (
        <>
            <Head title={result.title} />
            <div className="mx-auto grid max-w-7xl gap-5 print:max-w-none">
                <header className="hidden items-start justify-between gap-4 border-b-2 border-ink pb-3 print:flex">
                    <div>
                        <p className="text-lg font-bold">{letterhead.company}</p>
                        <p className="text-xs text-ink-soft">
                            {[letterhead.registration && `Reg ${letterhead.registration}`, letterhead.vat && `VAT ${letterhead.vat}`, letterhead.address, letterhead.contact].filter(Boolean).join(' · ')}
                        </p>
                    </div>
                    {letterhead.logoUrl && <img src={letterhead.logoUrl} alt="" className="max-h-12" />}
                </header>
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        {letterhead.footer && <p className="hidden border-t border-concrete pt-2 text-xs text-ink-soft print:block">{letterhead.footer}</p>}
                <p className="text-sm text-ink-soft print:hidden"><Link href="/reports" className="hover:underline">Reports</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">{result.title}</h1>
                        <p className="text-ink-soft">{result.subtitle}</p>
                    </div>
                    <div className="flex flex-wrap gap-2 print:hidden">
                        {report.key.startsWith('custom-') && <Button variant="ghost" asChild><a href={`/reports/designer/${report.key.slice(7)}`}>Edit design</a></Button>}
                        <Button variant="secondary" onClick={() => window.print()}><Printer className="size-4" /> Print or save as PDF</Button>
                        <Button variant="secondary" asChild><a href={`/reports/${report.key}/download/xlsx?${query}`}><Download className="size-4" /> Excel</a></Button>
                        <Button variant="ghost" asChild><a href={`/reports/${report.key}/download/csv?${query}`}>CSV</a></Button>
                    </div>
                </header>

                {presets.length > 0 && (
                    <div className="flex flex-wrap items-center gap-2 text-sm print:hidden">
                        <span className="text-ink-soft">Layout:</span>
                        <a href={`/reports/${report.key}?${query}`} className={presetId === null ? 'font-semibold' : 'underline'}>All columns</a>
                        {presets.map((p) => (
                            <span key={p.id} className="flex items-center gap-1">
                                <a href={`/reports/${report.key}?${query}&preset=${p.id}`} className={presetId === p.id ? 'font-semibold' : 'underline'}>{p.name}</a>
                                <button className="text-xs text-ink-soft hover:text-brick" aria-label={`Remove ${p.name}`}
                                    onClick={() => window.confirm(`Remove the "${p.name}" layout?`) && router.delete(`/report-presets/${p.id}`, { preserveScroll: true })}>×</button>
                            </span>
                        ))}
                    </div>
                )}

                <SaveLayout reportKey={report.key} columns={result.columns.map((c) => c.key)} />

                <form onSubmit={(e) => { e.preventDefault(); router.get(`/reports/${report.key}`, Object.fromEntries(Object.entries(f).filter(([, v]) => v)), { preserveState: true }); }} className="flex flex-wrap items-end gap-3 print:hidden">
                    {report.filters.includes('project') && (
                        <div className="w-72"><LookupField label="Project" name="project" type="projects" value={f.project ?? ''} placeholder="All projects" onChange={(v) => setF({ ...f, project: v || null })} /></div>
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

function SaveLayout({ reportKey, columns }: { reportKey: string; columns: string[] }) {
    const [open, setOpen] = useState(false);
    const form = useForm<{ name: string; columns: string[]; is_default: boolean }>({ name: '', columns, is_default: false });

    if (!open) {
        return <button className="justify-self-start text-sm text-ink-soft underline print:hidden" onClick={() => setOpen(true)}>Save these columns as a layout</button>;
    }

    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post(`/reports/${reportKey}/presets`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }}
            className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 print:hidden">
            <p className="font-semibold">Save this layout</p>
            <div className="flex flex-wrap gap-2">
                {columns.map((c) => (
                    <label key={c} className="flex items-center gap-1.5 rounded-full border border-concrete px-2.5 py-1 text-sm">
                        <input type="checkbox" className="accent-line" checked={form.data.columns.includes(c)}
                            onChange={(e) => form.setData('columns', e.target.checked ? [...form.data.columns, c] : form.data.columns.filter((x) => x !== c))} />
                        {c}
                    </label>
                ))}
            </div>
            <div className="flex flex-wrap items-end gap-3">
                <div className="w-56"><Field label="Name this layout" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} /></div>
                <label className="flex items-center gap-2 pb-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.is_default} onChange={(e) => form.setData('is_default', e.target.checked)} /> Use it by default</label>
                <Button type="submit" disabled={form.processing}>Save layout</Button>
                <button type="button" className="pb-2 text-sm text-ink-soft underline" onClick={() => setOpen(false)}>Cancel</button>
            </div>
        </form>
    );
}

ReportShow.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
