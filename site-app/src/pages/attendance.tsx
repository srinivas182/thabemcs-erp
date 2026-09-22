import { useNavigate } from '@tanstack/react-router';
import { Button } from '@thabekhulu/ui';
import { useState } from 'react';
import { enqueue } from '../lib/db';
import { compressImage, distanceMetres, getPosition, nowIso } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Choice, Page, PhotoInput } from './ui';

export function AttendancePage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const [direction, setDirection] = useState<'in' | 'out'>('in');
    const [selfie, setSelfie] = useState<File | null>(null);
    const [status, setStatus] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);

    async function submit() {
        if (!project) return;
        setBusy(true);
        setStatus('Getting your location…');
        const position = await getPosition();
        let note = 'No location available; your sign-in will be marked for checking.';
        if (position && project.latitude !== null && project.longitude !== null) {
            const d = distanceMetres(position.coords.latitude, position.coords.longitude, project.latitude, project.longitude);
            note = d <= project.geofenceRadius ? 'You are on site.' : `You appear to be ${d.toLocaleString('en-ZA')} m from site; this will be flagged.`;
        }

        await enqueue('attendance', `Signed ${direction}, ${project.name}`, {
            clientId: crypto.randomUUID(),
            projectId: project.id,
            direction,
            capturedAt: nowIso(),
            latitude: position?.coords.latitude ?? null,
            longitude: position?.coords.longitude ?? null,
            accuracy: position ? Math.round(position.coords.accuracy) : null,
        }, selfie ? await compressImage(selfie, 800) : undefined, 'selfie');
        void flushOutbox();
        setStatus(note);
        setBusy(false);
        window.setTimeout(() => void navigate({ to: '/' }), 1800);
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;

    return (
        <Page title="Sign in or out">
            <Choice label="I am" value={direction} onChange={setDirection} columns={2} options={[{ key: 'in', label: 'Arriving' }, { key: 'out', label: 'Leaving' }]} />
            <PhotoInput label="Selfie" facing="user" onChange={setSelfie} preview={selfie ? URL.createObjectURL(selfie) : null} />
            <Button size="lg" onClick={() => void submit()} disabled={busy || !selfie}>{direction === 'in' ? 'Sign in' : 'Sign out'}</Button>
            {status && <p role="status" className="rounded-[var(--radius-control)] bg-line-wash px-3 py-2 text-sm text-line-deep">{status}</p>}
        </Page>
    );
}
