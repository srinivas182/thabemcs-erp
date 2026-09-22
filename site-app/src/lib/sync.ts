import { db, type OutboxItem } from './db';

const API_URL = import.meta.env.VITE_API_URL ?? '/api/v1';

const ENDPOINTS: Record<OutboxItem['kind'], string> = {
    site_diary: '/site/diary-entries',
};

/**
 * Push pending items to the server, oldest first.
 * Items are idempotent (clientId), so a retry after a dropped connection is safe.
 */
export async function flushOutbox(): Promise<{ sent: number; failed: number }> {
    if (!navigator.onLine) return { sent: 0, failed: 0 };

    const items = await db.outbox.where('status').anyOf('pending', 'failed').sortBy('createdAt');
    let sent = 0;
    let failed = 0;

    for (const item of items) {
        await db.outbox.update(item.id, { status: 'syncing' });

        try {
            const response = await fetch(`${API_URL}${ENDPOINTS[item.kind]}`, {
                method: 'POST',
                credentials: 'include',
                headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
                body: JSON.stringify(item.payload),
            });

            if (!response.ok) throw new Error(`Server responded ${response.status}`);

            await db.outbox.delete(item.id);
            sent++;
        } catch (error) {
            await db.outbox.update(item.id, {
                status: 'failed',
                attempts: item.attempts + 1,
                lastError: error instanceof Error ? error.message : 'Unknown error',
            });
            failed++;
        }
    }

    return { sent, failed };
}

/** Sync whenever the device comes back online, and every two minutes while open. */
export function startBackgroundSync(): () => void {
    const run = () => void flushOutbox();
    window.addEventListener('online', run);
    const timer = window.setInterval(run, 120_000);
    run();

    return () => {
        window.removeEventListener('online', run);
        window.clearInterval(timer);
    };
}
