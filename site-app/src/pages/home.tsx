import { Link } from '@tanstack/react-router';
import { AlertTriangle, Camera, ClipboardList, Truck, Users } from 'lucide-react';
import type { ComponentType } from 'react';

interface Action {
    label: string;
    description: string;
    icon: ComponentType<{ className?: string }>;
    to?: '/diary/new';
}

// One tap for the things site teams record most. Actions without a route arrive in later sprints.
const ACTIONS: Action[] = [
    { label: 'Daily diary', description: 'Weather, workers on site, work done, delays', icon: ClipboardList, to: '/diary/new' },
    { label: 'Photo', description: 'Progress photo with location', icon: Camera },
    { label: 'Attendance', description: 'Sign workers in and out', icon: Users },
    { label: 'Delivery', description: 'Receive materials against a PO', icon: Truck },
    { label: 'Incident', description: 'Report a safety incident or near miss', icon: AlertTriangle },
];

export function HomePage() {
    return (
        <div className="grid gap-5">
            <div>
                <h1 className="text-2xl font-bold">What are you recording?</h1>
                <p className="mt-1 text-ink-soft">Everything saves on this phone first and sends when you have signal.</p>
            </div>

            <ul className="grid gap-2.5">
                {ACTIONS.map(({ label, description, icon: Icon, to }) => {
                    const body = (
                        <>
                            <span className="grid size-11 shrink-0 place-items-center rounded-[var(--radius-control)] bg-line-wash text-line-deep">
                                <Icon className="size-5" />
                            </span>
                            <span className="min-w-0">
                                <span className="block font-semibold">{label}</span>
                                <span className="block text-sm text-ink-soft">{to ? description : 'Coming in a later release'}</span>
                            </span>
                        </>
                    );

                    return (
                        <li key={label}>
                            {to ? (
                                <Link
                                    to={to}
                                    className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 active:bg-concrete-soft"
                                >
                                    {body}
                                </Link>
                            ) : (
                                <div aria-disabled="true" className="flex items-center gap-3 rounded-[var(--radius-panel)] border border-dashed border-concrete p-3 opacity-60">
                                    {body}
                                </div>
                            )}
                        </li>
                    );
                })}
            </ul>
        </div>
    );
}
