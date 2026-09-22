import { Link, Outlet } from '@tanstack/react-router';
import { cn } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { CloudOff, RefreshCw } from 'lucide-react';
import { useRegisterSW } from 'virtual:pwa-register/react';
import { db } from '../lib/db';
import { flushOutbox } from '../lib/sync';
import { useOnline } from '../lib/use-online';

export function RootLayout() {
    const online = useOnline();
    const waiting = useLiveQuery(() => db.outbox.count(), [], 0);
    const {
        needRefresh: [needRefresh],
        updateServiceWorker,
    } = useRegisterSW();

    return (
        <div className="mx-auto flex min-h-dvh max-w-xl flex-col pb-[env(safe-area-inset-bottom)]">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-concrete bg-surface/95 px-4 py-3 backdrop-blur">
                <Link to="/" className="text-base font-extrabold tracking-tight [font-stretch:115%]">
                    Thabekhulu Site
                </Link>
                <button
                    type="button"
                    onClick={() => void flushOutbox()}
                    disabled={!online || waiting === 0}
                    className={cn(
                        'flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium',
                        online ? 'bg-line-wash text-line-deep' : 'bg-hivis-wash text-ink',
                    )}
                >
                    {online ? <RefreshCw className="size-3.5" aria-hidden /> : <CloudOff className="size-3.5" aria-hidden />}
                    {online ? (waiting ? `Send ${waiting} now` : 'All sent') : waiting ? `Offline, ${waiting} saved` : 'Offline'}
                </button>
            </header>

            {needRefresh && (
                <div role="status" className="flex items-center justify-between gap-3 bg-line-deep px-4 py-2.5 text-sm text-white">
                    A new version is ready.
                    <button className="font-semibold underline" onClick={() => void updateServiceWorker(true)}>
                        Update now
                    </button>
                </div>
            )}

            <main className="flex-1 px-4 py-5">
                <Outlet />
            </main>
        </div>
    );
}
