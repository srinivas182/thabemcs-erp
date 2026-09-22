import { useNavigate } from '@tanstack/react-router';
import { Button, Field } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { useState } from 'react';
import { db, enqueue } from '../lib/db';
import { compressImage } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page, PhotoInput } from './ui';

export function SnagPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const suppliers = useLiveQuery(() => db.suppliers.orderBy('name').toArray(), [], []);
    const [description, setDescription] = useState('');
    const [location, setLocation] = useState('');
    const [supplierId, setSupplierId] = useState('');
    const [dueOn, setDueOn] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [error, setError] = useState<string | null>(null);

    async function submit() {
        if (!project) return;
        if (description.trim().length < 3) return setError('Describe the defect.');
        await enqueue('snag', `Snag: ${description}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, description, location: location || null, supplierId: supplierId || null, dueOn: dueOn || null,
        }, photo ? await compressImage(photo) : undefined, 'photo');
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;
    return (
        <Page title="Record a snag">
            <PhotoInput label="Photo" facing="environment" onChange={setPhoto} preview={photo ? URL.createObjectURL(photo) : null} />
            <Field label="Defect" name="description" value={description} onChange={(e) => setDescription(e.target.value)} placeholder="e.g. Cracked tile at shower entrance" />
            <Field label="Where" name="location" value={location} onChange={(e) => setLocation(e.target.value)} placeholder="e.g. Unit 12, main bathroom" />
            <div className="grid gap-1.5">
                <label htmlFor="supplier" className="text-sm font-medium">Contractor to fix it</label>
                <select id="supplier" value={supplierId} onChange={(e) => setSupplierId(e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base">
                    <option value="">Not decided</option>
                    {suppliers.map((s) => <option key={s.id} value={s.id}>{s.name}</option>)}
                </select>
            </div>
            <Field label="Fix by" name="dueOn" type="date" value={dueOn} onChange={(e) => setDueOn(e.target.value)} />
            {error && <p className="text-sm text-brick">{error}</p>}
            <Button size="lg" onClick={() => void submit()}>Save snag</Button>
        </Page>
    );
}
