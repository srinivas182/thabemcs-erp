import { useLiveQuery } from 'dexie-react-hooks';
import { api, getMe } from './api';
import { db, getSetting, setSetting, type CachedEmployee, type CachedForm, type CachedOrder, type CachedProject } from './db';

/** Refresh the signed-in user and the cached project and supplier lists (when online). */
export async function refreshSession(): Promise<boolean> {
    try {
        const me = await getMe();
        await setSetting('me', me);
        const [projects, suppliers, forms] = await Promise.all([
            api<{ data: CachedProject[] }>('/api/v1/site/projects'),
            api<{ data: { id: string; name: string }[] }>('/api/v1/site/suppliers'),
            api<{ data: CachedForm[] }>('/api/v1/site/forms').catch(() => ({ data: [] as CachedForm[] })),
        ]);
        await db.transaction('rw', [db.projects, db.suppliers, db.forms], async () => {
            await db.forms.clear();
            await db.forms.bulkAdd(forms.data);
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

/** Cache the crew list and open orders for a project so the register and receiving work offline. */
export async function refreshProjectCache(projectId: string): Promise<void> {
    if (!navigator.onLine) return;
    const q = `projectId=${encodeURIComponent(projectId)}`;
    const [employees, orders] = await Promise.allSettled([
        api<{ data: Omit<CachedEmployee, 'projectId'>[] }>(`/api/v1/site/employees?${q}`),
        api<{ data: Omit<CachedOrder, 'projectId'>[] }>(`/api/v1/site/orders?${q}`),
    ]);
    await db.transaction('rw', db.employees, db.orders, async () => {
        if (employees.status === 'fulfilled') {
            await db.employees.where('projectId').equals(projectId).delete();
            await db.employees.bulkAdd(employees.value.data.map((e) => ({ ...e, projectId })));
        }
        if (orders.status === 'fulfilled') {
            await db.orders.where('projectId').equals(projectId).delete();
            await db.orders.bulkAdd(orders.value.data.map((o) => ({ ...o, projectId })));
        }
    });
}
