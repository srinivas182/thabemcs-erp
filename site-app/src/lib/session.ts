import { useLiveQuery } from 'dexie-react-hooks';
import { api, getMe } from './api';
import { db, getSetting, setSetting, type CachedProject } from './db';

/** Refresh the signed-in user and the cached project and supplier lists (when online). */
export async function refreshSession(): Promise<boolean> {
    try {
        const me = await getMe();
        await setSetting('me', me);
        const [projects, suppliers] = await Promise.all([
            api<{ data: CachedProject[] }>('/api/v1/site/projects'),
            api<{ data: { id: string; name: string }[] }>('/api/v1/site/suppliers'),
        ]);
        await db.transaction('rw', db.projects, db.suppliers, async () => {
            await db.projects.clear();
            await db.projects.bulkAdd(projects.data);
            await db.suppliers.clear();
            await db.suppliers.bulkAdd(suppliers.data);
        });
        return true;
    } catch {
        return false;
    }
}

export function useCurrentProject(): CachedProject | undefined | null {
    return useLiveQuery(async () => {
        const id = await getSetting<string>('projectId');
        return id ? ((await db.projects.get(id)) ?? null) : null;
    }, []);
}
