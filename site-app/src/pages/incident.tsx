import { useNavigate } from '@tanstack/react-router';
import { Button, Field } from '@thabekhulu/ui';
import { useState } from 'react';
import { enqueue } from '../lib/db';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page, TextArea } from './ui';

const TYPES = [
    { key: 'near_miss', label: 'Near miss (nobody hurt)' },
    { key: 'first_aid', label: 'First aid case' },
    { key: 'medical', label: 'Medical treatment needed' },
    { key: 'lost_time', label: 'Injured, cannot work' },
    { key: 'dangerous_occurrence', label: 'Dangerous occurrence' },
    { key: 'property_damage', label: 'Property or equipment damage' },
    { key: 'fatality', label: 'Fatality' },
];

function localDateTime(): string {
    const d = new Date();
    d.setMinutes(d.getMinutes() - d.getTimezoneOffset());
    return d.toISOString().slice(0, 16);
}

export function IncidentPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const [type, setType] = useState('near_miss');
    const [occurredAt, setOccurredAt] = useState(localDateTime());
    const [location, setLocation] = useState('');
    const [description, setDescription] = useState('');
    const [action, setAction] = useState('');
    const [person, setPerson] = useState('');
    const [error, setError] = useState<string | null>(null);

    async function submit() {
        if (!project) return;
        if (description.trim().length < 5) return setError('Describe what happened.');
        await enqueue('incident', `Incident: ${TYPES.find((t) => t.key === type)?.label}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, type, occurredAt: new Date(occurredAt).toISOString(),
            location: location || null, description, immediateAction: action || null, personInvolved: person || null,
        });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;

    return (
        <Page title="Report an incident">
            <p className="rounded-[var(--radius-control)] bg-brick-wash px-3 py-2 text-sm text-brick">If someone is seriously hurt, call for help first: 112 from a cellphone, or 10177 for an ambulance.</p>
            <div className="grid gap-1.5">
                <label htmlFor="type" className="text-sm font-medium">What happened</label>
                <select id="type" value={type} onChange={(e) => setType(e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base">
                    {TYPES.map((t) => (<option key={t.key} value={t.key}>{t.label}</option>))}
                </select>
            </div>
            <Field label="When" name="occurredAt" type="datetime-local" value={occurredAt} onChange={(e) => setOccurredAt(e.target.value)} />
            <Field label="Where on site" name="location" value={location} onChange={(e) => setLocation(e.target.value)} placeholder="e.g. Block B scaffold, level 2" />
            <TextArea label="Describe what happened" name="description" value={description} onChange={(e) => setDescription(e.target.value)} rows={4} />
            <TextArea label="What was done immediately" name="immediateAction" value={action} onChange={(e) => setAction(e.target.value)} optional />
            <Field label="Person involved (optional)" name="personInvolved" value={person} onChange={(e) => setPerson(e.target.value)} />
            {error && <p className="text-sm text-brick">{error}</p>}
            <Button size="lg" variant="danger" onClick={() => void submit()}>Report incident</Button>
        </Page>
    );
}
