import { Head, Link } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import L from 'leaflet';
import 'leaflet/dist/leaflet.css';
import { type ReactNode, useEffect, useRef, useState } from 'react';
import { formatDate, formatRand } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Point {
    id: string; code: string; name: string; company: string | null; town: string | null; stage: string; lat: number | null; lng: number | null;
    budget: number; spent: number; used: number | null; highRisks: number; openIncidents: number; openSnags: number; behind: number; late: boolean; forecastFinish: string | null; health: 'red' | 'amber' | 'green';
}

const COLOUR = { red: '#B4412C', amber: '#E3A008', green: '#0E6B63' } as const;
const LABEL = { red: 'Needs attention', amber: 'Watch', green: 'On track' } as const;

/** Command centre: every live project on a map, coloured by health, with the reasons alongside. */
export default function CommandCentre({ projects, group }: { projects: Point[]; group: boolean }) {
    const el = useRef<HTMLDivElement>(null);
    const [selected, setSelected] = useState<string | null>(null);
    const placed = projects.filter((p) => p.lat !== null && p.lng !== null);

    useEffect(() => {
        if (!el.current) return;
        const map = L.map(el.current, { scrollWheelZoom: false }).setView([-29, 25.5], 5); // South Africa
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', { maxZoom: 17, attribution: '&copy; OpenStreetMap contributors' }).addTo(map);
        const bounds: [number, number][] = [];
        for (const p of placed) {
            const marker = L.circleMarker([p.lat!, p.lng!], { radius: 9, color: '#fff', weight: 2, fillColor: COLOUR[p.health], fillOpacity: 0.95 }).addTo(map);
            marker.bindTooltip(`${p.code} ${p.name}`);
            marker.on('click', () => setSelected(p.id));
            bounds.push([p.lat!, p.lng!]);
        }
        if (bounds.length > 1) map.fitBounds(bounds, { padding: [40, 40], maxZoom: 11 });
        else if (bounds.length === 1) map.setView(bounds[0]!, 11);
        return () => { map.remove(); };
    }, []);

    const reasons = (p: Point) => [
        p.used !== null && p.used >= 100 && 'over budget', p.used !== null && p.used >= 80 && p.used < 100 && `${p.used}% of budget used`,
        p.openIncidents > 0 && `${p.openIncidents} open incident${p.openIncidents > 1 ? 's' : ''}`, p.late && `finish forecast ${formatDate(p.forecastFinish)}`,
        p.behind > 0 && `${p.behind} activit${p.behind > 1 ? 'ies' : 'y'} behind`, p.highRisks > 0 && `${p.highRisks} high risk${p.highRisks > 1 ? 's' : ''}`,
    ].filter(Boolean).join(', ');
    const order = { red: 0, amber: 1, green: 2 };

    return (
        <>
            <Head title="Command centre" />
            <div className="mx-auto grid max-w-7xl gap-5">
                <header className="flex flex-wrap items-end justify-between gap-3">
                    <div>
                        <h1 className="text-3xl font-bold tracking-tight [font-stretch:92%]">Command centre</h1>
                        <p className="text-ink-soft">{group ? 'All companies' : 'All live projects'}: {projects.filter((p) => p.health === 'red').length} need attention, {projects.filter((p) => p.health === 'amber').length} to watch, {projects.filter((p) => p.health === 'green').length} on track.</p>
                    </div>
                    <p className="flex gap-4 text-sm">{(['red', 'amber', 'green'] as const).map((h) => <span key={h} className="flex items-center gap-1.5"><span className="size-3 rounded-full" style={{ background: COLOUR[h] }} />{LABEL[h]}</span>)}</p>
                </header>
                <div className="grid gap-5 lg:grid-cols-[1.5fr_1fr]">
                    <div ref={el} className="h-[520px] overflow-hidden rounded-[var(--radius-panel)] border border-concrete" role="region" aria-label="Map of projects" />
                    <ul className="grid max-h-[520px] content-start gap-2 overflow-y-auto">
                        {[...projects].sort((a, b) => order[a.health] - order[b.health]).map((p) => (
                            <li key={p.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-3 text-sm', selected === p.id ? 'border-ink' : 'border-concrete')}>
                                <div className="flex items-start justify-between gap-2">
                                    <Link href={`/projects/${p.id}`} className="font-semibold hover:underline">{p.name}</Link>
                                    <span className="size-3 shrink-0 rounded-full" style={{ background: COLOUR[p.health] }} aria-label={LABEL[p.health]} />
                                </div>
                                <p className="text-ink-soft">{[p.company, p.code, p.town, p.stage].filter(Boolean).join(', ')}</p>
                                <p className="mt-1">{reasons(p) || 'No issues flagged.'}</p>
                                {p.budget > 0 && <p className="text-ink-soft">Budget {formatRand(p.budget)}, committed {formatRand(p.spent)}</p>}
                                {(p.lat === null || p.lng === null) && <p className="text-xs text-ink-soft">No site location set, so it is not on the map.</p>}
                            </li>
                        ))}
                    </ul>
                </div>
                <p className="text-xs text-ink-soft">Red: over budget, an open incident, or forecast to finish late. Amber: 80% or more of the budget used, high risks, or activities behind. Map data © OpenStreetMap contributors.</p>
            </div>
        </>
    );
}

CommandCentre.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
