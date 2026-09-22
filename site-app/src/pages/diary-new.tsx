import { useNavigate } from '@tanstack/react-router';
import { siteDiaryEntrySchema, WEATHER } from '@thabekhulu/shared';
import { Button, Field } from '@thabekhulu/ui';
import { type FormEvent, useEffect, useState } from 'react';
import { enqueue } from '../lib/db';
import { nowIso, todayInSouthAfrica } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { fetchSiteWeather, type WeatherReading } from '../lib/weather';
import { Choice, Page, TextArea } from './ui';

const WEATHER_LABELS: Record<(typeof WEATHER)[number], string> = { clear: 'Clear', cloudy: 'Cloudy', rain: 'Rain', storm: 'Storm', wind: 'Windy', heat: 'Very hot' };

export function DiaryNewPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const [weather, setWeather] = useState<(typeof WEATHER)[number]>('clear');
    const [errors, setErrors] = useState<Record<string, string>>({});
    const [reading, setReading] = useState<WeatherReading | null>(null);

    // Suggest today's weather at the site when there is signal.
    useEffect(() => {
        if (!project) return;
        void fetchSiteWeather(project.latitude, project.longitude, todayInSouthAfrica()).then((r) => {
            if (r) { setReading(r); setWeather(r.weather); }
        });
    }, [project]);

    async function submit(event: FormEvent<HTMLFormElement>) {
        event.preventDefault();
        if (!project) return;
        const data = new FormData(event.currentTarget);
        const result = siteDiaryEntrySchema.safeParse({
            clientId: crypto.randomUUID(),
            projectId: project.id,
            date: String(data.get('date') ?? ''),
            weather,
            workersOnSite: Number(data.get('workersOnSite') ?? 0),
            workCompleted: String(data.get('workCompleted') ?? ''),
            delays: String(data.get('delays') ?? '') || undefined,
            capturedAt: nowIso(),
        });

        if (!result.success) {
            const next: Record<string, string> = {};
            for (const issue of result.error.issues) next[String(issue.path[0])] ??= issue.message;
            setErrors(next);
            return;
        }

        await enqueue('site_diary', `Diary ${result.data.date}, ${project.name}`, {
            ...result.data,
            temperatureMax: reading?.temperatureMax ?? null,
            rainMm: reading?.rainMm ?? null,
            weatherAuto: reading !== null && reading.weather === weather,
        });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;

    return (
        <Page title="Daily diary">
            <form onSubmit={submit} className="grid gap-5" noValidate>
                <p className="text-sm text-ink-soft">{project.name}. One diary per day: saving again for the same date replaces it.</p>
                <Field label="Date" name="date" type="date" defaultValue={todayInSouthAfrica()} error={errors.date} />
                <Choice label="Weather" value={weather} onChange={setWeather} options={WEATHER.map((w) => ({ key: w, label: WEATHER_LABELS[w] }))} />
                {reading && <p className="-mt-3 text-xs text-ink-soft">Filled in from today's forecast at the site{reading.temperatureMax !== null && `: up to ${Math.round(reading.temperatureMax)}°C`}{reading.rainMm ? `, ${reading.rainMm} mm rain` : ''}. Change it if it was different.</p>}
                <Field label="Workers on site" name="workersOnSite" type="number" inputMode="numeric" min={0} defaultValue={0} error={errors.workersOnSite} />
                <TextArea label="Work completed today" name="workCompleted" rows={4} error={errors.workCompleted} />
                <TextArea label="Delays or problems" name="delays" optional />
                <Button type="submit" size="lg">Save diary</Button>
            </form>
        </Page>
    );
}
