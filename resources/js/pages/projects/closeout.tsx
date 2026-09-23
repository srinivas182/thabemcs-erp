import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Check } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Item { id: number; key: string; label: string; group: string; groupLabel: string; required: boolean; completed: string | null; notes: string | null }
interface Money { revenue: number; cost: number; profit: number; margin: number | null }
interface Props {
    project: { id: string; name: string; code: string; status: string };
    items: Item[];
    readiness: { done: number; required: number; outstanding: string[] };
    finalAccount: {
        baseline: Money | null; final: Money; revenueSource: string; costToDate: number;
        funding: Record<string, number | null>; distributed: number; undistributed: number;
    };
    canManage: boolean;
    canClose: boolean;
}

export default function Closeout({ project, items, readiness, finalAccount, canManage, canClose }: Props) {
    const groups = [...new Set(items.map((i) => i.group))];
    const done = items.filter((i) => i.completed).length;

    return (
        <>
            <Head title={`Close-out: ${project.name}`} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Close-out</h1>
                    <p className="text-ink-soft">
                        {project.name}. {done} of {items.length} items done
                        {readiness.outstanding.length > 0 ? `, ${readiness.outstanding.length} required items outstanding.` : '. Everything required is in place.'}
                    </p>
                    {project.status === 'completed' && <p className="mt-2 rounded-[var(--radius-control)] bg-line-wash px-3 py-2 text-sm text-line-deep">This project is closed out and marked complete.</p>}
                    {canClose && project.status !== 'completed' && (
                        <Button className="mt-3" disabled={readiness.outstanding.length > 0}
                            onClick={() => window.confirm(`Mark ${project.name} complete? It moves out of the live portfolio.`) && router.post(`/projects/${project.id}/closeout/close`, {}, { preserveScroll: true })}>
                            Close the project out
                        </Button>
                    )}
                </header>

                <section className="grid gap-4">
                    <h2 className="text-lg font-bold">Final account</h2>
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full text-sm [&_td]:px-4 [&_td]:py-2.5 [&_th]:px-4 [&_th]:py-2.5 [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr><th className="text-left" /><th className="text-right">Approved feasibility</th><th className="text-right">Final</th><th className="text-right">Difference</th></tr></thead>
                            <tbody>
                                {(['revenue', 'cost', 'profit'] as const).map((k) => {
                                    const base = finalAccount.baseline?.[k] ?? 0;
                                    const change = finalAccount.final[k] - base;
                                    const bad = k === 'cost' ? change > 0 : change < 0;
                                    return (
                                        <tr key={k}>
                                            <td className="font-medium capitalize">{k === 'cost' ? 'Total cost' : k}</td>
                                            <td className="text-right tabular-nums">{formatRand(base)}</td>
                                            <td className="text-right tabular-nums">{formatRand(finalAccount.final[k])}</td>
                                            <td className={cn('text-right tabular-nums', change !== 0 && (bad ? 'text-brick' : 'text-line-deep'))}>{change > 0 ? '+' : ''}{formatRand(change)}</td>
                                        </tr>
                                    );
                                })}
                                <tr><td className="font-medium">Margin</td><td className="text-right tabular-nums">{finalAccount.baseline?.margin ?? '—'}%</td><td className="text-right tabular-nums">{finalAccount.final.margin ?? '—'}%</td><td /></tr>
                            </tbody>
                        </table>
                    </div>
                    <p className="text-sm text-ink-soft">
                        Revenue from {finalAccount.revenueSource === 'sales' ? 'recorded sales' : 'the approved feasibility'}. Paid to date {formatRand(finalAccount.costToDate)}.
                        Distributed to investors {formatRand(finalAccount.distributed)}; {formatRand(finalAccount.undistributed)} of profit not yet distributed.{' '}
                        <Link href={`/projects/${project.id}/distributions`} className="underline">Investor returns</Link>
                    </p>
                </section>

                {groups.map((group) => (
                    <section key={group} className="grid gap-2">
                        <h2 className="text-lg font-bold">{items.find((i) => i.group === group)?.groupLabel}</h2>
                        <ul className="grid gap-2">
                            {items.filter((i) => i.group === group).map((item) => <Row key={item.id} item={item} canManage={canManage} />)}
                        </ul>
                    </section>
                ))}
            </div>
        </>
    );
}

function Row({ item, canManage }: { item: Item; canManage: boolean }) {
    const today = new Date().toLocaleDateString('en-CA');
    const [open, setOpen] = useState(false);
    const form = useForm({ completed_on: today, notes: '' });
    return (
        <li className="flex flex-wrap items-center justify-between gap-2 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 text-sm">
            <span className="flex items-center gap-2">
                <span className={cn('flex size-5 items-center justify-center rounded-full', item.completed ? 'bg-line text-white' : 'border border-concrete')}>{item.completed && <Check className="size-3.5" />}</span>
                <span>
                    {item.label}{!item.required && <span className="text-ink-soft"> (if it applies)</span>}
                    {item.completed && <span className="block text-xs text-ink-soft">Done {formatDate(item.completed)}{item.notes ? ` — ${item.notes}` : ''}</span>}
                </span>
            </span>
            {canManage && (item.completed ? (
                <button className="text-xs text-ink-soft underline" onClick={() => router.post(`/closeout-items/${item.id}/reopen`, {}, { preserveScroll: true })}>Reopen</button>
            ) : open ? (
                <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, notes: d.notes || null })); form.post(`/closeout-items/${item.id}/complete`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="flex items-end gap-2">
                    <Field label="" aria-label="Completed on" name={`d${item.id}`} type="date" value={form.data.completed_on} onChange={(e) => form.setData('completed_on', e.target.value)} />
                    <Field label="" aria-label="Notes" name={`n${item.id}`} value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="Reference" />
                    <Button size="sm" type="submit" disabled={form.processing}>Save</Button>
                </form>
            ) : <Button size="sm" variant="ghost" onClick={() => setOpen(true)}>Mark done</Button>)}
        </li>
    );
}

Closeout.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
