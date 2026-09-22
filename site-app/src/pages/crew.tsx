import { useNavigate } from '@tanstack/react-router';
import { Button } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { useState } from 'react';
import { db, enqueue } from '../lib/db';
import { todayInSouthAfrica } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page } from './ui';

type Status = 'present' | 'absent' | 'sick' | 'leave';
const NEXT: Record<Status, Status> = { present: 'absent', absent: 'sick', sick: 'leave', leave: 'present' };
const LABEL: Record<Status, string> = { present: 'Present', absent: 'Absent', sick: 'Sick', leave: 'On leave' };

/** Daily crew register: the supervisor marks each worker, so workers do not need phones or logins. */
export function CrewPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const crew = useLiveQuery(() => (project ? db.employees.where('projectId').equals(project.id).sortBy('name') : []), [project?.id], []);
    const [status, setStatus] = useState<Record<string, Status>>({});
    const [timeIn, setTimeIn] = useState('07:00');
    const [timeOut, setTimeOut] = useState('16:30');

    async function submit() {
        if (!project || crew.length === 0) return;
        const date = todayInSouthAfrica();
        const entries = crew.map((e) => {
            const s = status[e.id] ?? 'present';
            return { clientId: crypto.randomUUID(), employeeId: e.id, status: s, timeIn: s === 'present' ? timeIn : null, timeOut: s === 'present' ? timeOut : null };
        });
        await enqueue('crew', `Crew register ${date}, ${project.name}`, { clientId: crypto.randomUUID(), projectId: project.id, date, entries });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;
    const present = crew.filter((e) => (status[e.id] ?? 'present') === 'present').length;

    return (
        <Page title="Crew register">
            {crew.length === 0 ? (
                <p className="text-ink-soft">Nobody is allocated to this project yet, or the list has not downloaded. Allocate workers in Workforce on the web app, then open this page with signal.</p>
            ) : (
                <>
                    <div className="grid grid-cols-2 gap-3">
                        <label className="grid gap-1 text-sm font-medium">Start<input type="time" value={timeIn} onChange={(e) => setTimeIn(e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete px-3" /></label>
                        <label className="grid gap-1 text-sm font-medium">Finish<input type="time" value={timeOut} onChange={(e) => setTimeOut(e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete px-3" /></label>
                    </div>
                    <p className="text-sm text-ink-soft">Everyone is marked present. Tap a name to change it.</p>
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {crew.map((e) => {
                            const s = status[e.id] ?? 'present';
                            return (
                                <li key={e.id}>
                                    <button type="button" className="flex w-full items-center justify-between gap-3 p-3 text-left" onClick={() => setStatus({ ...status, [e.id]: NEXT[s] })}>
                                        <span><span className="block font-medium">{e.name}</span><span className="block text-xs text-ink-soft">{e.number}{e.jobTitle && `, ${e.jobTitle}`}</span></span>
                                        <span className={s === 'present' ? 'rounded-full bg-line-wash px-2.5 py-1 text-xs font-semibold text-line-deep' : 'rounded-full bg-brick-wash px-2.5 py-1 text-xs font-semibold text-brick'}>{LABEL[s]}</span>
                                    </button>
                                </li>
                            );
                        })}
                    </ul>
                    <Button size="lg" onClick={() => void submit()}>Save register ({present} of {crew.length} present)</Button>
                </>
            )}
        </Page>
    );
}
