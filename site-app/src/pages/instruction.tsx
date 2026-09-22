import { useNavigate } from '@tanstack/react-router';
import { Button, Field } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { useState } from 'react';
import { db, enqueue } from '../lib/db';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page, TextArea } from './ui';

export function InstructionPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const suppliers = useLiveQuery(() => db.suppliers.orderBy('name').toArray(), [], []);
    const [supplierId, setSupplierId] = useState('');
    const [subject, setSubject] = useState('');
    const [text, setText] = useState('');
    const [cost, setCost] = useState(false);
    const [time, setTime] = useState(false);
    const [error, setError] = useState<string | null>(null);

    async function submit() {
        if (!project) return;
        if (subject.trim().length < 3 || text.trim().length < 3) return setError('Give a subject and the instruction.');
        await enqueue('instruction', `Site instruction: ${subject}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, supplierId: supplierId || null, subject, instruction: text, costImplication: cost, timeImplication: time,
        });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;
    return (
        <Page title="Site instruction">
            <div className="grid gap-1.5">
                <label htmlFor="supplier" className="text-sm font-medium">To</label>
                <select id="supplier" value={supplierId} onChange={(e) => setSupplierId(e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base">
                    <option value="">Choose contractor</option>
                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
            </div>
            <Field label="Subject" name="subject" value={subject} onChange={(e) => setSubject(e.target.value)} />
            <TextArea label="Instruction" name="instruction" value={text} onChange={(e) => setText(e.target.value)} rows={5} />
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-line" checked={cost} onChange={(e) => setCost(e.target.checked)} /> May change the cost</label>
            <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-5 accent-line" checked={time} onChange={(e) => setTime(e.target.checked)} /> May change the programme</label>
            {error && <p className="text-sm text-brick">{error}</p>}
            <Button size="lg" onClick={() => void submit()}>Issue instruction</Button>
            <p className="text-xs text-ink-soft">It gets its number (SI-...) when it reaches the office.</p>
        </Page>
    );
}
