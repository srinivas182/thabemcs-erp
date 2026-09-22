import { z } from 'zod';

/** Weather options recorded on the daily site diary. */
export const WEATHER = ['clear', 'cloudy', 'rain', 'storm', 'wind', 'heat'] as const;

/**
 * A daily site diary entry captured on the site app (offline-first).
 * The same schema validates on the phone and describes the API payload.
 */
export const siteDiaryEntrySchema = z.object({
    clientId: z.uuid(),
    projectId: z.string().min(1, 'Choose a project'),
    date: z.iso.date(),
    weather: z.enum(WEATHER),
    workersOnSite: z.number().int().min(0).max(5000),
    workCompleted: z.string().trim().min(3, 'Describe the work completed today').max(4000),
    delays: z.string().trim().max(2000).optional(),
    capturedAt: z.iso.datetime(),
});

export type SiteDiaryEntry = z.infer<typeof siteDiaryEntrySchema>;
