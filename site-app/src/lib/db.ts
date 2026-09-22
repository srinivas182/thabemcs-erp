import type { SiteDiaryEntry } from '@thabekhulu/shared';
import Dexie, { type EntityTable } from 'dexie';

/**
 * Everything captured on site is written to the device first (IndexedDB),
 * then pushed to the server by the sync worker when there is a connection.
 */
export type OutboxStatus = 'pending' | 'syncing' | 'failed';

export interface OutboxItem {
    id: string;
    kind: 'site_diary';
    payload: SiteDiaryEntry;
    status: OutboxStatus;
    attempts: number;
    lastError?: string;
    createdAt: string;
}

export const db = new Dexie('thabekhulu-site') as Dexie & {
    outbox: EntityTable<OutboxItem, 'id'>;
};

db.version(1).stores({
    outbox: 'id, kind, status, createdAt',
});
