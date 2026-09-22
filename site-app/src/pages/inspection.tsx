import { useNavigate } from '@tanstack/react-router';
import { Button, Field } from '@thabekhulu/ui';
import { useState } from 'react';
import { enqueue } from '../lib/db';
import { todayInSouthAfrica } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Choice, Page, TextArea } from './ui';

export function InspectionPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const [kind, setKind] = useState<'quality' | 'safety'>('quality');
    const [title, setTitle] = useState('');
    const [location, setLocation] = useState('');
    const [result, setResult] = useState<'pass' | 'partial' | 'fail'>('pass');
    const [findings, setFindings] = useState('');
    const [error, setError] = useState<string | null>(null);

    async function submit() {
        if (!project) return;
        if (title.trim().length < 3) return setError('Say what was inspected.');
        if (result !== 'pass' && findings.trim().length < 3) return setError('Record what failed so it can be fixed.');
        await enqueue('inspection', `${kind === 'safety' ? 'Safety' : 'Quality'} inspection: ${title}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, kind, title, location: location || null, result, findings: findings || null, inspectedOn: todayInSouthAfrica(),
        });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;
    return (
        <Page title="Inspection">
            <Choice label="Type" value={kind} onChange={setKind} columns={2} options={[{ key: 'quality', label: 'Quality' }, { key: 'safety', label: 'Safety' }]} />
            <Field label="What was inspected" name="title" value={title} onChange={(e) => setTitle(e.target.value)} placeholder={kind === 'safety' ? 'e.g. Scaffold, Block B' : 'e.g. Reinforcement before pour, slab 2'} />
            <Field label="Where" name="location" value={location} onChange={(e) => setLocation(e.target.value)} />
            <Choice label="Result" value={result} onChange={setResult} options={[{ key: 'pass', label: 'Pass' }, { key: 'partial', label: 'Partly' }, { key: 'fail', label: 'Fail' }]} />
            {result !== 'pass' && <TextArea label="What needs fixing" name="findings" value={findings} onChange={(e) => setFindings(e.target.value)} />}
            {error && <p className="text-sm text-brick">{error}</p>}
            <Button size="lg" onClick={() => void submit()}>Save inspection</Button>
        </Page>
    );
}
