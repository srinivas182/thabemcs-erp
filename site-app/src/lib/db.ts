import Dexie, { type EntityTable } from 'dexie';

/**
 * Everything captured on site is written to the phone first (IndexedDB), then sent by the
 * sync worker when there is signal. Projects and suppliers are cached so forms work offline.
 */
export type OutboxKind = 'site_diary' | 'attendance' | 'photo' | 'delivery' | 'incident';
export type OutboxStatus = 'pending' | 'syncing' | 'failed' | 'rejected';

export interface OutboxItem {
    id: string;
    kind: OutboxKind;
    label: string;
    payload: Record<string, unknown>;
    /** Photo or selfie, sent as multipart. */
    file?: Blob;
    fileField?: string;
    status: OutboxStatus;
    attempts: number;
    lastError?: string;
    createdAt: string;
}

export interface CachedProject {
    id: string;
    code: string;
    name: string;
    town: string | null;
    latitude: number | null;
    longitude: number | null;
    geofenceRadius: number;
}

export interface CachedSupplier {
    id: string;
    name: string;
}

export interface Setting {
    key: string;
    value: unknown;
}

export const db = new Dexie('thabekhulu-site') as Dexie & {
    outbox: EntityTable<OutboxItem, 'id'>;
    projects: EntityTable<CachedProject, 'id'>;
    suppliers: EntityTable<CachedSupplier, 'id'>;
    settings: EntityTable<Setting, 'key'>;
};

db.version(1).stores({ outbox: 'id, kind, status, createdAt' });
db.version(2).stores({
    outbox: 'id, kind, status, createdAt',
    projects: 'id, name',
    suppliers: 'id, name',
    settings: 'key',
});

export async function getSetting<T>(key: string): Promise<T | undefined> {
    return (await db.settings.get(key))?.value as T | undefined;
}

export async function setSetting(key: string, value: unknown): Promise<void> {
    await db.settings.put({ key, value });
}

/** Queue a record for sending and try to send straight away. */
export async function enqueue(kind: OutboxKind, label: string, payload: Record<string, unknown>, file?: Blob, fileField?: string): Promise<void> {
    await db.outbox.add({
        id: String(payload.clientId),
        kind,
        label,
        payload,
        file,
        fileField,
        status: 'pending',
        attempts: 0,
        createdAt: new Date().toISOString(),
    });
}
