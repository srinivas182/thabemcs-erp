import Dexie, { type EntityTable } from 'dexie';

/**
 * Everything captured on site is written to the phone first (IndexedDB), then sent by the
 * sync worker when there is signal. Projects and suppliers are cached so forms work offline.
 */
export type OutboxKind = 'site_diary' | 'attendance' | 'photo' | 'delivery' | 'incident' | 'crew' | 'receipt' | 'snag' | 'inspection' | 'instruction' | 'form';
export type OutboxStatus = 'pending' | 'syncing' | 'failed' | 'rejected';

export interface OutboxItem {
    id: string;
    kind: OutboxKind;
    label: string;
    payload: Record<string, unknown>;
    /** Photo or selfie, sent as multipart. */
    file?: Blob;
    fileField?: string;
    /** Extra photos keyed by form field name (form builder photo questions). */
    files?: Record<string, Blob>;
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

export interface CachedEmployee {
    id: string;
    projectId: string;
    number: string;
    name: string;
    jobTitle: string | null;
}

export interface CachedOrder {
    id: string;
    projectId: string;
    reference: string;
    supplier: string;
    lines: { id: number; description: string; unit: string; ordered: number; outstanding: number }[];
}

export interface CachedForm {
    id: string;
    name: string;
    kind: string;
    version: number;
    fields: { id: string; label: string; type: string; required: boolean; options?: string[] }[];
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
    employees: EntityTable<CachedEmployee, 'id'>;
    orders: EntityTable<CachedOrder, 'id'>;
    forms: EntityTable<CachedForm, 'id'>;
};

db.version(1).stores({ outbox: 'id, kind, status, createdAt' });
db.version(2).stores({
    outbox: 'id, kind, status, createdAt',
    projects: 'id, name',
    suppliers: 'id, name',
    settings: 'key',
});
db.version(3).stores({
    outbox: 'id, kind, status, createdAt',
    projects: 'id, name',
    suppliers: 'id, name',
    settings: 'key',
    employees: 'id, projectId, name',
    orders: 'id, projectId, reference',
});
db.version(4).stores({
    outbox: 'id, kind, status, createdAt',
    projects: 'id, name',
    suppliers: 'id, name',
    settings: 'key',
    employees: 'id, projectId, name',
    orders: 'id, projectId, reference',
    forms: 'id, name',
});

export async function getSetting<T>(key: string): Promise<T | undefined> {
    return (await db.settings.get(key))?.value as T | undefined;
}

export async function setSetting(key: string, value: unknown): Promise<void> {
    await db.settings.put({ key, value });
}

/** Queue a record for sending and try to send straight away. */
export async function enqueue(kind: OutboxKind, label: string, payload: Record<string, unknown>, file?: Blob, fileField?: string, files?: Record<string, Blob>): Promise<void> {
    await db.outbox.add({
        id: String(payload.clientId),
        kind,
        label,
        payload,
        file,
        fileField,
        files,
        status: 'pending',
        attempts: 0,
        createdAt: new Date().toISOString(),
    });
}
