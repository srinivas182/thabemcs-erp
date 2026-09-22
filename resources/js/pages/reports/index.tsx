import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button } from '@thabekhulu/ui';
import { FileSpreadsheet } from 'lucide-react';
import type { FormEvent, ReactNode } from 'react';
import { formatDateTime, PageHeader, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };
interface Props {
    reports: { key: string; title: string; description: string }[];
    schedules: { id: number; report: string | null; frequency: string; day: number; format: string; recipients: string[]; lastSent: string | null; by: string }[];
    people: Option[];
    canSchedule: boolean;
}
const WEEKDAYS = ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'];

export default function Reports({ reports, schedules, people, canSchedule }: Props) {
    const form = useForm<{ report: string; frequency: string; day: string; format: string; recipients: string[] }>({ report: reports[0]?.key ?? '', frequency: 'weekly', day: '1', format: 'xlsx', recipients: [] });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/reports/schedules', { preserveScroll: true, onSuccess: () => form.reset('recipients') });
    }
    return (
        <>
            <Head title="Reports" />
            <div className="mx-auto grid max-w-5xl gap-8">
                <PageHeader title="Reports" description="Standard reports you can view, print to PDF, download for Excel, or have emailed on a schedule." />
                <ul className="grid gap-3 sm:grid-cols-2">
                    {reports.map((r) => (
                        <li key={r.key}>
                            <Link href={`/reports/${r.key}`} className="flex h-full gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4 hover:border-line">
                                <FileSpreadsheet className="mt-0.5 size-5 shrink-0 text-line" />
                                <span><span className="block font-semibold">{r.title}</span><span className="block text-sm text-ink-soft">{r.description}</span></span>
                            </Link>
                        </li>
                    ))}
                </ul>

                <section className="grid gap-3 border-t-2 border-ink pt-4">
                    <h2 className="text-lg font-bold">Scheduled emails</h2>
                    {schedules.length === 0 ? <p className="text-sm text-ink-soft">No reports are scheduled.</p> : (
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {schedules.map((s) => (
                                <li key={s.id} className="flex flex-wrap items-center justify-between gap-2 p-3">
                                    <span>
                                        <span className="font-semibold">{s.report}</span>, {s.frequency === 'weekly' ? `every ${WEEKDAYS[s.day - 1]}` : `monthly on day ${s.day}`}, {s.format.toUpperCase()} to {s.recipients.join(', ')}
                                        <span className="block text-ink-soft">Set up by {s.by}{s.lastSent && `, last sent ${formatDateTime(s.lastSent)}`}</span>
                                    </span>
                                    {canSchedule && <button className="text-brick hover:underline" onClick={() => router.delete(`/reports/schedules/${s.id}`, { preserveScroll: true })}>Stop</button>}
                                </li>
                            ))}
                        </ul>
                    )}
                    {canSchedule && (
                        <form onSubmit={submit} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <div className="grid gap-3 sm:grid-cols-4">
                                <SelectField label="Report" name="report" value={form.data.report} onChange={(v) => form.setData('report', v)} options={reports.map((r) => ({ key: r.key, label: r.title }))} />
                                <SelectField label="How often" name="frequency" value={form.data.frequency} onChange={(v) => form.setData((d) => ({ ...d, frequency: v, day: '1' }))} options={[{ key: 'weekly', label: 'Weekly' }, { key: 'monthly', label: 'Monthly' }]} />
                                <SelectField label={form.data.frequency === 'weekly' ? 'On' : 'Day of month'} name="day" value={form.data.day} onChange={(v) => form.setData('day', v)}
                                    options={form.data.frequency === 'weekly' ? WEEKDAYS.map((d, i) => ({ key: String(i + 1), label: d })) : Array.from({ length: 28 }, (_, i) => ({ key: String(i + 1), label: String(i + 1) }))} />
                                <SelectField label="Format" name="format" value={form.data.format} onChange={(v) => form.setData('format', v)} options={[{ key: 'xlsx', label: 'Excel' }, { key: 'csv', label: 'CSV' }]} />
                            </div>
                            <fieldset>
                                <legend className="text-sm font-medium">Send to</legend>
                                <div className="mt-1.5 flex flex-wrap gap-2">
                                    {people.map((p) => (
                                        <label key={p.key} className="flex items-center gap-1.5 rounded-full border border-concrete px-2.5 py-1 text-sm">
                                            <input type="checkbox" className="accent-line" checked={form.data.recipients.includes(p.key)} onChange={(e) => form.setData('recipients', e.target.checked ? [...form.data.recipients, p.key] : form.data.recipients.filter((x) => x !== p.key))} />
                                            {p.label}
                                        </label>
                                    ))}
                                </div>
                                {form.errors.recipients && <p className="mt-1 text-sm text-brick">{form.errors.recipients}</p>}
                            </fieldset>
                            <p className="text-xs text-ink-soft">Sent at 06:00. Weekly reports cover the previous Monday to Sunday; monthly reports cover the previous month. People who are not allowed to see a report do not receive it.</p>
                            <div><Button type="submit" disabled={form.processing}>Schedule</Button></div>
                        </form>
                    )}
                </section>
            </div>
        </>
    );
}

Reports.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
