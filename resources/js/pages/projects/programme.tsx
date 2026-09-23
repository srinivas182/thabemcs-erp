import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { Link2, Trash2, X } from 'lucide-react';
import { type FormEvent, type ReactNode, useMemo, useState } from 'react';
import { formatDate, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };
interface Activity {
    id: string; wbs: string | null; name: string; plannedStart: string; duration: number; actualStart: string | null; actualFinish: string | null;
    percent: number; owner: string | null; supplier: string | null; earlyStart: string; earlyFinish: string; float: number; critical: boolean; behind: boolean;
    predecessors: { id: number; activity: string; name: string; lag: number }[];
}
interface Props {
    project: { id: string; name: string; code: string };
    finish: string | null; plannedCompletion: string | null;
    activities: Activity[]; canManage: boolean;
}

const DAY = 86_400_000;
const ts = (d: string) => new Date(`${d}T00:00:00`).getTime();

/** Gantt chart: bars from early start to early finish, critical path in brick, progress shaded, today line. */
function Gantt({ activities }: { activities: Activity[] }) {
    const rowH = 30; const labelW = 230; const pxPerDay = 7;
    const start = Math.min(...activities.map((a) => ts(a.earlyStart)));
    const end = Math.max(...activities.map((a) => ts(a.earlyFinish))) + 7 * DAY;
    const days = Math.max(14, Math.round((end - start) / DAY));
    const width = labelW + days * pxPerDay;
    const height = 36 + activities.length * rowH;
    const x = (d: string) => labelW + ((ts(d) - start) / DAY) * pxPerDay;
    const rowOf = new Map(activities.map((a, i) => [a.id, i]));
    const today = new Date(); today.setHours(0, 0, 0, 0);
    const months: { label: string; x: number }[] = [];
    for (let d = new Date(start); d.getTime() <= end; d.setMonth(d.getMonth() + 1, 1)) {
        months.push({ label: d.toLocaleDateString('en-ZA', { month: 'short', year: '2-digit' }), x: labelW + ((Math.max(d.getTime(), start) - start) / DAY) * pxPerDay });
    }

    return (
        <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
            <svg width={width} height={height} className="block text-[11px]" role="img" aria-label="Programme Gantt chart">
                {months.map((m) => (
                    <g key={m.label + m.x}>
                        <line x1={m.x} x2={m.x} y1={0} y2={height} className="stroke-concrete-soft" />
                        <text x={m.x + 4} y={20} className="fill-ink-soft">{m.label}</text>
                    </g>
                ))}
                {today.getTime() >= start && today.getTime() <= end && (
                    <line x1={labelW + ((today.getTime() - start) / DAY) * pxPerDay} x2={labelW + ((today.getTime() - start) / DAY) * pxPerDay} y1={28} y2={height} className="stroke-hivis" strokeWidth={2} />
                )}
                {activities.map((a, i) => {
                    const y = 36 + i * rowH;
                    const x1 = x(a.earlyStart);
                    const x2 = x(a.earlyFinish) + pxPerDay;
                    return (
                        <g key={a.id}>
                            <text x={8} y={y + 18} className={cn('fill-ink', a.critical && 'font-semibold')}>{(a.wbs ? `${a.wbs} ` : '') + (a.name.length > 30 ? `${a.name.slice(0, 29)}…` : a.name)}</text>
                            {a.predecessors.map((p) => {
                                const r = rowOf.get(p.activity);
                                const pred = r === undefined ? undefined : activities[r];
                                if (r === undefined || pred === undefined) return null;
                                const px = x(pred.earlyFinish) + pxPerDay; const py = 36 + r * rowH + 15;
                                return <path key={p.id} d={`M${px},${py} H${Math.max(px + 4, x1 - 4)} V${y + 15} H${x1}`} fill="none" className="stroke-ink-soft" strokeWidth={1} markerEnd="url(#arrow)" />;
                            })}
                            {a.duration === 0 ? (
                                <path d={`M${x1},${y + 7} l8,8 l-8,8 l-8,-8 z`} className={a.critical ? 'fill-brick' : 'fill-ink'} />
                            ) : (
                                <>
                                    <rect x={x1} y={y + 7} width={Math.max(3, x2 - x1)} height={16} rx={3} className={a.critical ? 'fill-brick-wash stroke-brick' : 'fill-line-wash stroke-line'} />
                                    <rect x={x1} y={y + 7} width={Math.max(0, (x2 - x1) * (a.percent / 100))} height={16} rx={3} className={a.critical ? 'fill-brick' : 'fill-line'} />
                                </>
                            )}
                            {a.behind && <text x={x2 + 6} y={y + 19} className="fill-brick font-semibold">behind</text>}
                        </g>
                    );
                })}
                <defs><marker id="arrow" viewBox="0 0 6 6" refX="5" refY="3" markerWidth="6" markerHeight="6" orient="auto"><path d="M0,0 L6,3 L0,6 z" className="fill-ink-soft" /></marker></defs>
            </svg>
        </div>
    );
}

export default function Programme({ project, finish, plannedCompletion, activities, canManage }: Props) {
    const [selected, setSelected] = useState<string | null>(null);
    const add = useForm({ wbs: '', name: '', planned_start: '', duration_days: '5', owner: '', supplier: '' });
    const current = activities.find((a) => a.id === selected) ?? null;
    const critical = activities.filter((a) => a.critical).length;
    const late = plannedCompletion && finish && finish > plannedCompletion;
    const options = useMemo(() => activities.map((a) => ({ key: a.id, label: `${a.wbs ? `${a.wbs} ` : ''}${a.name}` })), [activities]);

    function submit(e: FormEvent) {
        e.preventDefault();
        add.transform((d) => ({ ...d, owner: d.owner || null, supplier: d.supplier || null, wbs: d.wbs || null }));
        add.post(`/projects/${project.id}/programme`, { preserveScroll: true, onSuccess: () => add.reset('wbs', 'name') });
    }

    return (
        <>
            <Head title={`Programme: ${project.name}`} />
            <div className="mx-auto grid max-w-7xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Programme</h1>
                    <p className="text-ink-soft">
                        {project.name}. Working days, Monday to Friday, excluding public holidays.
                        {finish && <> Forecast completion <strong className={cn(late && 'text-brick')}>{formatDate(finish)}</strong>{plannedCompletion && <> against planned {formatDate(plannedCompletion)}</>}.</>}
                        {critical > 0 && <> {critical} activities on the critical path (in red).</>}
                    </p>
                </header>

                {activities.length > 0 && <Gantt activities={activities} />}

                {activities.length > 0 && (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className="w-full min-w-[860px] text-sm [&_td]:px-3 [&_td]:py-2 [&_th]:px-3 [&_th]:py-2 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                            <thead><tr><th>Activity</th><th>Start</th><th>Finish</th><th className="text-right">Days</th><th className="text-right">Float</th><th>Progress</th><th>Responsible</th></tr></thead>
                            <tbody>
                                {activities.map((a) => (
                                    <tr key={a.id} className={cn('cursor-pointer hover:bg-plaster', selected === a.id && 'bg-line-wash')} onClick={() => setSelected(a.id === selected ? null : a.id)}>
                                        <td className={cn(a.critical && 'font-semibold text-brick')}>{a.wbs && <span className="mr-1 text-ink-soft">{a.wbs}</span>}{a.name}{a.duration === 0 && <span className="ml-1 text-xs text-ink-soft">(milestone)</span>}</td>
                                        <td>{formatDate(a.earlyStart)}</td><td>{formatDate(a.earlyFinish)}</td>
                                        <td className="text-right tabular-nums">{a.duration}</td><td className="text-right tabular-nums">{a.float}</td>
                                        <td><span className={cn('tabular-nums', a.behind && 'font-semibold text-brick')}>{a.percent}%{a.behind && ' behind'}</span></td>
                                        <td>{[a.owner, a.supplier].filter(Boolean).join(', ')}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}

                {current && canManage && <ActivityPanel key={current.id} activity={current} options={options.filter((o) => o.key !== current.id)} onClose={() => setSelected(null)} />}

                {canManage && (
                    <form onSubmit={submit} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                        <p className="font-semibold">Add an activity</p>
                        <div className="grid gap-3 sm:grid-cols-[100px_1fr_170px_110px]">
                            <Field label="WBS" name="wbs" value={add.data.wbs} onChange={(e) => add.setData('wbs', e.target.value)} placeholder="1.2" />
                            <Field label="Activity" name="name" value={add.data.name} onChange={(e) => add.setData('name', e.target.value)} error={add.errors.name} placeholder="e.g. Block C first-floor slab" />
                            <Field label="Earliest start" name="planned_start" type="date" value={add.data.planned_start} onChange={(e) => add.setData('planned_start', e.target.value)} error={add.errors.planned_start} />
                            <Field label="Working days" name="duration_days" type="number" min={0} value={add.data.duration_days} onChange={(e) => add.setData('duration_days', e.target.value)} hint="0 = milestone" />
                        </div>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <LookupField label="Responsible person" name="owner" value={add.data.owner} onChange={(v) => add.setData('owner', v)} type="people" placeholder="None" />
                            <LookupField label="Contractor" name="supplier" value={add.data.supplier} onChange={(v) => add.setData('supplier', v)} type="suppliers" params={{ types: 'contractor,subcontractor' }} placeholder="None" />
                        </div>
                        <div><Button type="submit" disabled={add.processing}>Add activity</Button></div>
                        <p className="text-xs text-ink-soft">Click an activity in the table to link it to the activities it follows, record progress or remove it.</p>
                    </form>
                )}
            </div>
        </>
    );
}

function ActivityPanel({ activity: a, options, onClose }: { activity: Activity; options: Option[]; onClose: () => void }) {
    const link = useForm({ predecessor: '', lag_days: '0' });
    const [percent, setPercent] = useState(String(a.percent));
    return (
        <section className="grid gap-4 rounded-[var(--radius-panel)] border-2 border-line bg-surface p-4">
            <div className="flex items-start justify-between gap-3">
                <p className="font-semibold">{a.name}</p>
                <button onClick={onClose} aria-label="Close" className="text-ink-soft hover:text-ink"><X className="size-4" /></button>
            </div>
            <div className="grid gap-4 md:grid-cols-2">
                <div className="grid content-start gap-2">
                    <p className="text-sm font-medium">Follows</p>
                    {a.predecessors.length === 0 && <p className="text-sm text-ink-soft">Nothing; it starts on its earliest start date.</p>}
                    {a.predecessors.map((p) => (
                        <p key={p.id} className="flex items-center justify-between text-sm">
                            <span><Link2 className="mr-1 inline size-3.5" />{p.name}{p.lag !== 0 && ` (+${p.lag} days)`}</span>
                            <button onClick={() => router.delete(`/programme-links/${p.id}`, { preserveScroll: true })} className="text-brick hover:underline">Remove</button>
                        </p>
                    ))}
                    <form onSubmit={(e) => { e.preventDefault(); link.post(`/programme-activities/${a.id}/links`, { preserveScroll: true, onSuccess: () => link.reset() }); }} className="grid items-end gap-2 sm:grid-cols-[1fr_90px_auto]">
                        <SelectField label="Add: starts after" name="predecessor" value={link.data.predecessor} onChange={(v) => link.setData('predecessor', v)} options={options} placeholder="Choose" />
                        <Field label="Lag days" name="lag_days" type="number" value={link.data.lag_days} onChange={(e) => link.setData('lag_days', e.target.value)} />
                        <Button type="submit" size="sm" disabled={!link.data.predecessor || link.processing}>Link</Button>
                    </form>
                </div>
                <div className="grid content-start gap-2">
                    <p className="text-sm font-medium">Progress</p>
                    <div className="flex items-center gap-3">
                        <input type="range" min={0} max={100} step={5} value={percent} onChange={(e) => setPercent(e.target.value)} className="flex-1 accent-line" aria-label="Percent complete" />
                        <span className="w-12 text-right tabular-nums">{percent}%</span>
                        <Button size="sm" onClick={() => router.patch(`/programme-activities/${a.id}`, { percent_complete: Number(percent) }, { preserveScroll: true })}>Save</Button>
                    </div>
                    <p className="text-xs text-ink-soft">{a.actualStart ? `Started ${formatDate(a.actualStart)}` : 'Not started'}{a.actualFinish && `, finished ${formatDate(a.actualFinish)}`}. Start and finish dates are recorded automatically.</p>
                    <Button variant="ghost" size="sm" className="justify-self-start text-brick" onClick={() => window.confirm(`Remove ${a.name}?`) && router.delete(`/programme-activities/${a.id}`, { preserveScroll: true, onSuccess: onClose })}><Trash2 className="size-4" /> Remove activity</Button>
                </div>
            </div>
        </section>
    );
}

Programme.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
