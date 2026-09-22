import { useNavigate } from '@tanstack/react-router';
import { siteDiaryEntrySchema, WEATHER } from '@thabekhulu/shared';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, useState } from 'react';
import { db } from '../lib/db';
import { flushOutbox } from '../lib/sync';

const WEATHER_LABELS: Record<(typeof WEATHER)[number], string> = {
    clear: 'Clear',
    cloudy: 'Cloudy',
    rain: 'Rain',
    storm: 'Storm',
    wind: 'Windy',
    heat: 'Very hot',
};

type Errors = Partial<Record<string, string>>;

function todayInSouthAfrica(): string {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());
}

export function DiaryNewPage() {
    const navigate = useNavigate();
    const [errors, setErrors] = useState<Errors>({});
    const [weather, setWeather] = useState<(typeof WEATHER)[number]>('clear');
    const [saving, setSaving] = useState(false);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        const data = new FormData(event.currentTarget);

        const result = siteDiaryEntrySchema.safeParse({
            clientId: crypto.randomUUID(),
            // Project selection is wired to the API in the Site Management sprint.
            projectId: String(data.get('projectId') ?? ''),
            date: String(data.get('date') ?? ''),
            weather,
            workersOnSite: Number(data.get('workersOnSite') ?? 0),
            workCompleted: String(data.get('workCompleted') ?? ''),
            delays: String(data.get('delays') ?? '') || undefined,
            capturedAt: new Date().toISOString(),
        });

        if (!result.success) {
            const next: Errors = {};
            for (const issue of result.error.issues) {
                const key = String(issue.path[0] ?? 'form');
                next[key] ??= issue.message;
            }
            setErrors(next);
            return;
        }

        setSaving(true);
        await db.outbox.add({
            id: result.data.clientId,
            kind: 'site_diary',
            payload: result.data,
            status: 'pending',
            attempts: 0,
            createdAt: result.data.capturedAt,
        });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    return (
        <form onSubmit={submit} className="grid gap-5" noValidate>
            <h1 className="text-2xl font-bold">Daily diary</h1>

            <Field label="Project code" name="projectId" placeholder="e.g. PRJ-0142" error={errors.projectId} />
            <Field label="Date" name="date" type="date" defaultValue={todayInSouthAfrica()} error={errors.date} />

            <fieldset>
                <legend className="text-sm font-medium">Weather</legend>
                <div className="mt-1.5 grid grid-cols-3 gap-2">
                    {WEATHER.map((w) => (
                        <button
                            key={w}
                            type="button"
                            aria-pressed={weather === w}
                            onClick={() => setWeather(w)}
                            className={cn(
                                'h-11 rounded-[var(--radius-control)] border text-sm font-medium',
                                weather === w ? 'border-line bg-line text-white' : 'border-concrete bg-surface',
                            )}
                        >
                            {WEATHER_LABELS[w]}
                        </button>
                    ))}
                </div>
            </fieldset>

            <Field label="Workers on site" name="workersOnSite" type="number" inputMode="numeric" min={0} defaultValue={0} error={errors.workersOnSite} />

            <div className="grid gap-1.5">
                <label htmlFor="workCompleted" className="text-sm font-medium">
                    Work completed today
                </label>
                <textarea
                    id="workCompleted"
                    name="workCompleted"
                    rows={4}
                    className="rounded-[var(--radius-control)] border border-concrete bg-surface p-3 text-base focus:border-line focus:outline-none"
                />
                {errors.workCompleted && <p className="text-sm text-brick">{errors.workCompleted}</p>}
            </div>

            <div className="grid gap-1.5">
                <label htmlFor="delays" className="text-sm font-medium">
                    Delays or problems <span className="font-normal text-ink-soft">(optional)</span>
                </label>
                <textarea
                    id="delays"
                    name="delays"
                    rows={3}
                    className="rounded-[var(--radius-control)] border border-concrete bg-surface p-3 text-base focus:border-line focus:outline-none"
                />
            </div>

            <Button type="submit" size="lg" disabled={saving}>
                {saving ? 'Saving…' : 'Save diary'}
            </Button>
        </form>
    );
}
