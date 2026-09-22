import { useNavigate } from '@tanstack/react-router';
import { Button, cn, Field } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { useState } from 'react';
import { type CachedForm, db, enqueue } from '../lib/db';
import { compressImage } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page, PhotoInput, TextArea } from './ui';

/** Checklists built in the web app's form builder, filled in here (offline). */
export function FormsPage() {
    const forms = useLiveQuery(() => db.forms.orderBy('name').toArray(), [], []);
    const [chosen, setChosen] = useState<CachedForm | null>(null);
    if (chosen) return <FillForm form={chosen} onBack={() => setChosen(null)} />;
    return (
        <Page title="Checklists">
            {forms.length === 0 ? <p className="text-ink-soft">No checklists yet. They are built in the web app under Forms, and download here when you have signal.</p> : (
                <ul className="grid gap-2">
                    {forms.map((f) => (
                        <li key={f.id}><button className="flex w-full items-center justify-between rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 text-left" onClick={() => setChosen(f)}>
                            <span><span className="block font-semibold">{f.name}</span><span className="block text-xs capitalize text-ink-soft">{f.kind}, {f.fields.length} questions</span></span>
                        </button></li>
                    ))}
                </ul>
            )}
        </Page>
    );
}

function FillForm({ form, onBack }: { form: CachedForm; onBack: () => void }) {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const [answers, setAnswers] = useState<Record<string, string>>({});
    const [photos, setPhotos] = useState<Record<string, File>>({});
    const [error, setError] = useState<string | null>(null);
    const set = (id: string, v: string) => setAnswers({ ...answers, [id]: v });

    async function submit() {
        if (!project) return;
        const missing = form.fields.find((f) => f.required && (f.type === 'photo' ? !photos[f.id] : !answers[f.id]));
        if (missing) return setError(`Answer "${missing.label}".`);
        const files: Record<string, Blob> = {};
        for (const [id, file] of Object.entries(photos)) files[`photo_${id}`] = await compressImage(file);
        const failed = form.fields.some((f) => f.type === 'passfail' && answers[f.id] === 'fail');
        await enqueue('form', `${form.name}${failed ? ' (failed)' : ''}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, formId: form.id, answers: JSON.stringify(answers),
        }, undefined, undefined, files);
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;
    const choice = (id: string, options: { key: string; label: string; bad?: boolean }[]) => (
        <div className="grid grid-cols-3 gap-2">
            {options.map((o) => (
                <button key={o.key} type="button" aria-pressed={answers[id] === o.key} onClick={() => set(id, o.key)}
                    className={cn('min-h-11 rounded-[var(--radius-control)] border text-sm font-medium', answers[id] === o.key ? (o.bad ? 'border-brick bg-brick text-white' : 'border-line bg-line text-white') : 'border-concrete bg-surface')}>{o.label}</button>
            ))}
        </div>
    );

    return (
        <Page title={form.name}>
            <button className="-mt-3 justify-self-start text-sm text-ink-soft underline" onClick={onBack}>All checklists</button>
            {form.fields.map((f, i) => (
                <div key={f.id} className="grid gap-1.5">
                    {f.type !== 'photo' && f.type !== 'text' && <p className="text-sm font-medium">{i + 1}. {f.label}{!f.required && <span className="font-normal text-ink-soft"> (optional)</span>}</p>}
                    {f.type === 'passfail' && choice(f.id, [{ key: 'pass', label: 'Pass' }, { key: 'fail', label: 'Fail', bad: true }, { key: 'na', label: 'N/A' }])}
                    {f.type === 'yesno' && choice(f.id, [{ key: 'yes', label: 'Yes' }, { key: 'no', label: 'No' }])}
                    {f.type === 'choice' && (
                        <select value={answers[f.id] ?? ''} onChange={(e) => set(f.id, e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete bg-surface px-3" aria-label={f.label}>
                            <option value="">Choose</option>{(f.options ?? []).map((o) => <option key={o} value={o}>{o}</option>)}
                        </select>
                    )}
                    {f.type === 'number' && <Field label="" aria-label={f.label} name={f.id} type="number" inputMode="decimal" value={answers[f.id] ?? ''} onChange={(e) => set(f.id, e.target.value)} />}
                    {f.type === 'date' && <Field label="" aria-label={f.label} name={f.id} type="date" value={answers[f.id] ?? ''} onChange={(e) => set(f.id, e.target.value)} />}
                    {f.type === 'text' && <TextArea label={`${i + 1}. ${f.label}`} name={f.id} value={answers[f.id] ?? ''} onChange={(e) => set(f.id, e.target.value)} optional={!f.required} />}
                    {f.type === 'photo' && <PhotoInput label={`${i + 1}. ${f.label}`} facing="environment" onChange={(file) => file && setPhotos({ ...photos, [f.id]: file })} preview={photos[f.id] ? URL.createObjectURL(photos[f.id]!) : null} />}
                </div>
            ))}
            {error && <p className="text-sm text-brick">{error}</p>}
            <Button size="lg" onClick={() => void submit()}>Submit checklist</Button>
        </Page>
    );
}
