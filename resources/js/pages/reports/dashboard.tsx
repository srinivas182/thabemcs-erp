import { Head, Link } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatRand, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Totals { projects: number; budget: number; spent: number; paid: number; highRisks: number; openIncidents: number; pendingApprovals: number; owedToSuppliers: number; daysSinceLti: number | null }
interface ProjectRow { id: string; code: string; name: string; stage: string; stageNumber: number; status: string; budget: number; spent: number; used: number | null; paid: number; highRisks: number; openIncidents: number; openSnags: number }
interface Props { portfolio: { projects: ProjectRow[]; totals: Totals } | null; group: (Totals & { id: string; name: string })[] | null }

function Figure({ label, value, tone }: { label: string; value: ReactNode; tone?: 'bad' | 'warn' }) {
    return (
        <div className="bg-surface p-4">
            <p className="text-xs text-ink-soft">{label}</p>
            <p className={cn('mt-1 text-2xl font-bold tabular-nums', tone === 'bad' && 'text-brick')}>{value}</p>
        </div>
    );
}

function Used({ used }: { used: number | null }) {
    if (used === null) return <span className="text-ink-soft">No budget</span>;
    return (
        <span className="flex items-center gap-2">
            <span className="h-2 w-20 overflow-hidden rounded-full bg-concrete-soft"><span className={cn('block h-full', used >= 100 ? 'bg-brick' : used >= 80 ? 'bg-hivis' : 'bg-line')} style={{ width: `${Math.min(100, used)}%` }} /></span>
            <span className="tabular-nums text-xs">{used}%</span>
        </span>
    );
}

export default function Dashboard({ portfolio, group }: Props) {
    if (group) {
        return (
            <>
                <Head title="Group portfolio" />
                <div className="mx-auto grid max-w-6xl gap-6">
                    <h1 className="text-3xl font-bold tracking-tight [font-stretch:92%]">Group portfolio</h1>
                    <p className="text-ink-soft">All active companies. Open a company from Companies to see its projects.</p>
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Company</th><th className="text-right">Live projects</th><th className="text-right">Budget</th><th className="text-right">Committed</th><th className="text-right">Paid</th><th className="text-right">High risks</th><th className="text-right">Open incidents</th><th className="text-right">Days since LTI</th></tr></thead>
                            <tbody>
                                {group.map((c) => (
                                    <tr key={c.id}>
                                        <td className="font-semibold">{c.name}</td><td className="text-right tabular-nums">{c.projects}</td>
                                        <td className="text-right tabular-nums">{formatRand(c.budget)}</td><td className="text-right tabular-nums">{formatRand(c.spent)}</td>
                                        <td className="text-right tabular-nums">{formatRand(c.paid)}</td><td className={cn('text-right tabular-nums', c.highRisks > 0 && 'text-brick')}>{c.highRisks}</td>
                                        <td className={cn('text-right tabular-nums', c.openIncidents > 0 && 'text-brick')}>{c.openIncidents}</td><td className="text-right tabular-nums">{c.daysSinceLti ?? 'None'}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                </div>
            </>
        );
    }

    if (!portfolio) return null;
    const t = portfolio.totals;

    return (
        <>
            <Head title="Portfolio" />
            <div className="mx-auto grid max-w-7xl gap-6">
                <div className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight [font-stretch:92%]">Portfolio</h1>
                        <p className="text-ink-soft">Live projects at a glance. Amounts excl. VAT unless stated.</p>
                    </div>
                    <Link href="/reports" className="text-sm font-medium text-line hover:underline">All reports</Link>
                </div>

                <section className="grid grid-cols-2 gap-px overflow-hidden rounded-[var(--radius-panel)] border border-concrete bg-concrete md:grid-cols-4">
                    <Figure label="Live projects" value={t.projects} />
                    <Figure label="Revised budget" value={formatRand(t.budget)} />
                    <Figure label="Committed and spent" value={formatRand(t.spent)} />
                    <Figure label="Paid to suppliers" value={formatRand(t.paid)} />
                    <Figure label="Approved, not yet paid (incl. VAT)" value={formatRand(t.owedToSuppliers)} />
                    <Figure label="Waiting for approval" value={t.pendingApprovals} />
                    <Figure label="High or critical risks" value={t.highRisks} tone={t.highRisks ? 'bad' : undefined} />
                    <Figure label="Days since lost-time injury" value={t.daysSinceLti ?? 'None recorded'} />
                </section>

                {portfolio.projects.length === 0 ? <p className="text-ink-soft">No live projects.</p> : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Project</th><th>Stage</th><th className="text-right">Budget</th><th>Used</th><th className="text-right">Paid</th><th className="text-right">High risks</th><th className="text-right">Incidents</th><th className="text-right">Snags</th></tr></thead>
                            <tbody>
                                {portfolio.projects.map((p) => (
                                    <tr key={p.id} className="hover:bg-plaster">
                                        <td><Link href={`/projects/${p.id}`} className="font-semibold hover:underline">{p.name}</Link><p className="text-ink-soft">{p.code}{p.status !== 'active' && ', on hold'}</p></td>
                                        <td>
                                            <span className="flex gap-0.5" aria-label={`Stage ${p.stageNumber} of 7: ${p.stage}`}>
                                                {Array.from({ length: 7 }, (_, i) => <span key={i} className={cn('h-2 w-3 rounded-sm', i < p.stageNumber ? 'bg-line' : 'bg-concrete-soft')} />)}
                                            </span>
                                            <span className="text-xs text-ink-soft">{p.stage}</span>
                                        </td>
                                        <td className="text-right tabular-nums">{p.budget ? formatRand(p.budget) : ''}</td>
                                        <td><Used used={p.used} /></td>
                                        <td className="text-right tabular-nums">{formatRand(p.paid)}</td>
                                        <td className={cn('text-right tabular-nums', p.highRisks > 0 && 'font-semibold text-brick')}>{p.highRisks}</td>
                                        <td className={cn('text-right tabular-nums', p.openIncidents > 0 && 'font-semibold text-brick')}>{p.openIncidents}</td>
                                        <td className="text-right tabular-nums">{p.openSnags}</td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

Dashboard.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
