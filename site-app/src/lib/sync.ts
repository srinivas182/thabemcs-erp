import { api, ApiError } from './api';
import { db, type OutboxItem } from './db';

const ENDPOINTS: Record<OutboxItem['kind'], string> = {
    site_diary: '/api/v1/site/diary-entries',
    attendance: '/api/v1/site/attendance',
    photo: '/api/v1/site/photos',
    delivery: '/api/v1/site/deliveries',
    incident: '/api/v1/site/incidents',
    crew: '/api/v1/site/crew-attendance',
    receipt: '/api/v1/site/receipts',
    snag: '/api/v1/site/snags',
    inspection: '/api/v1/site/inspections',
    instruction: '/api/v1/site/instructions',
};

let running = false;

function body(item: OutboxItem): BodyInit {
    if (!item.file) return JSON.stringify(item.payload);

    const form = new FormData();
    for (const [key, value] of Object.entries(item.payload)) {
        if (value !== null && value !== undefined) form.append(key, String(value));
    }
    form.append(item.fileField ?? 'file', item.file, `${item.id}.jpg`);
    return form;
}

/**
 * Send queued items oldest first. Records carry a clientId, so retrying after a dropped connection is safe.
 * Validation errors (422) are marked "rejected" and kept for the user to see; they are not retried.
 */
export async function flushOutbox(): Promise<{ sent: number; failed: number }> {
    if (running || !navigator.onLine) return { sent: 0, failed: 0 };
    running = true;
    let sent = 0;
    let failed = 0;

    try {
        const items = await db.outbox.where('status').anyOf('pending', 'failed').sortBy('createdAt');

        for (const item of items) {
            await db.outbox.update(item.id, { status: 'syncing' });
            try {
                await api(ENDPOINTS[item.kind], { method: 'POST', body: body(item) });
                await db.outbox.delete(item.id);
                sent++;
            } catch (error) {
                const rejected = error instanceof ApiError && error.status === 422;
                const message = error instanceof ApiError ? Object.values(error.errors)[0]?.[0] ?? error.message : 'No connection';
                await db.outbox.update(item.id, { status: rejected ? 'rejected' : 'failed', attempts: item.attempts + 1, lastError: message });
                failed++;
                if (error instanceof ApiError && error.status === 401) break; // signed out: stop until they sign in again
            }
        }
    } finally {
        running = false;
    }

    return { sent, failed };
}

/** Sync whenever the phone comes back online, and every two minutes while the app is open. */
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
