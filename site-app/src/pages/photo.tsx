import { useNavigate } from '@tanstack/react-router';
import { Button, Field } from '@thabekhulu/ui';
import { useState } from 'react';
import { enqueue } from '../lib/db';
import { compressImage, getPosition, nowIso } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page, PhotoInput } from './ui';

export function PhotoPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const [photo, setPhoto] = useState<File | null>(null);
    const [caption, setCaption] = useState('');
    const [busy, setBusy] = useState(false);

    async function submit() {
        if (!project || !photo) return;
        setBusy(true);
        const position = await getPosition(8000);
        await enqueue('photo', `Photo${caption ? `: ${caption}` : ''}, ${project.name}`, {
            clientId: crypto.randomUUID(),
            projectId: project.id,
            capturedAt: nowIso(),
            caption: caption || null,
            latitude: position?.coords.latitude ?? null,
            longitude: position?.coords.longitude ?? null,
        }, await compressImage(photo), 'file');
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;

    return (
        <Page title="Progress photo">
            <PhotoInput label="Photo" facing="environment" onChange={setPhoto} preview={photo ? URL.createObjectURL(photo) : null} />
            <Field label="What does it show?" name="caption" value={caption} onChange={(e) => setCaption(e.target.value)} placeholder="e.g. Block C first-floor slab poured" />
            <Button size="lg" onClick={() => void submit()} disabled={busy || !photo}>Save photo</Button>
        </Page>
    );
}
