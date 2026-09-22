import { Link } from '@tanstack/react-router';
import { cn } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { AlertTriangle, Camera, ClipboardCheck, ClipboardList, FileSignature, ListChecks, PackageCheck, Truck, UserCheck, Users, Wrench } from 'lucide-react';
import { type ComponentType, useEffect } from 'react';
import { db, setSetting } from '../lib/db';
import { refreshProjectCache, useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';

const ACTIONS: { to: '/diary/new' | '/attendance' | '/photo' | '/delivery' | '/incident' | '/crew' | '/receive' | '/snag' | '/inspection' | '/instruction' | '/forms'; label: string; description: string; icon: ComponentType<{ className?: string }>; tone?: string }[] = [
    { to: '/attendance', label: 'Sign in or out', description: 'Site attendance with location and selfie', icon: UserCheck },
    { to: '/diary/new', label: 'Daily diary', description: 'Weather, workers, work done, delays', icon: ClipboardList },
    { to: '/photo', label: 'Progress photo', description: 'Geotagged photo of the works', icon: Camera },
    { to: '/crew', label: 'Crew register', description: 'Mark who is on site today', icon: Users },
    { to: '/receive', label: 'Goods received', description: 'Against a purchase order: scan its QR code', icon: PackageCheck },
    { to: '/delivery', label: 'Delivery without an order', description: 'Materials, delivery note, condition', icon: Truck },
    { to: '/inspection', label: 'Inspection', description: 'Quality or safety, pass or fail', icon: ClipboardCheck },
    { to: '/forms', label: 'Checklists', description: 'Your company\'s own forms, e.g. pre-pour or plant pre-use', icon: ListChecks },
    { to: '/snag', label: 'Snag', description: 'Defect with photo, for a contractor to fix', icon: Wrench },
    { to: '/instruction', label: 'Site instruction', description: 'Written instruction to a contractor', icon: FileSignature },
    { to: '/incident', label: 'Report an incident', description: 'Injury, near miss or damage', icon: AlertTriangle, tone: 'text-brick bg-brick-wash' },
];

export function HomePage() {
    const project = useCurrentProject();
    const projects = useLiveQuery(() => db.projects.orderBy('name').toArray(), [], []);
    const problems = useLiveQuery(() => db.outbox.where('status').anyOf('failed', 'rejected').toArray(), [], []);

    // Keep the crew list and open orders for this project on the phone.
    useEffect(() => {
        if (project) void refreshProjectCache(project.id);
    }, [project?.id]);

    return (
        <div className="grid gap-5">
            <div className="grid gap-1.5">
                <label htmlFor="project" className="text-sm font-medium">Project</label>
                <select id="project" value={project?.id ?? ''} onChange={(e) => void setSetting('projectId', e.target.value)}
                    className="h-12 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base">
                    <option value="" disabled>Choose the project you are on</option>
                    {projects.map((p) => (<option key={p.id} value={p.id}>{p.name} ({p.code})</option>))}
                </select>
            </div>

            {project ? (
                <ul className="grid gap-2.5">
                    {ACTIONS.map(({ to, label, description, icon: Icon, tone }) => (
                        <li key={to}>
                            <Link to={to} className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 active:bg-concrete-soft">
                                <span className={cn('grid size-11 shrink-0 place-items-center rounded-[var(--radius-control)]', tone ?? 'bg-line-wash text-line-deep')}><Icon className="size-5" /></span>
                                <span className="min-w-0">
                                    <span className="block font-semibold">{label}</span>
                                    <span className="block text-sm text-ink-soft">{description}</span>
                                </span>
                            </Link>
                        </li>
                    ))}
                </ul>
            ) : (
                <p className="text-ink-soft">Choose a project to start recording.</p>
            )}

            {problems.length > 0 && (
                <section className="grid gap-2 rounded-[var(--radius-panel)] border border-hivis bg-hivis-wash p-3">
                    <p className="font-semibold">Not sent yet</p>
                    <ul className="grid gap-2 text-sm">
                        {problems.map((p) => (
                            <li key={p.id} className="flex items-start justify-between gap-2">
                                <span>{p.label}<span className="block text-ink-soft">{p.lastError}</span></span>
                                {p.status === 'rejected' ? (
                                    <button className="shrink-0 text-brick underline" onClick={() => window.confirm('Delete this record from the phone?') && void db.outbox.delete(p.id)}>Delete</button>
                                ) : (
                                    <button className="shrink-0 underline" onClick={() => void flushOutbox()}>Retry</button>
                                )}
                            </li>
                        ))}
                    </ul>
                </section>
            )}
        </div>
    );
}
