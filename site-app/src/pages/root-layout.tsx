import { Link, Outlet } from '@tanstack/react-router';
import { Button, cn, Field } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { CloudOff, RefreshCw } from 'lucide-react';
import { type FormEvent, useEffect, useState } from 'react';
import { useRegisterSW } from 'virtual:pwa-register/react';
import { ApiError, login, twoFactor } from '../lib/api';
import { db, getSetting } from '../lib/db';
import { refreshSession } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { useOnline } from '../lib/use-online';

export function RootLayout() {
    const online = useOnline();
    const waiting = useLiveQuery(() => db.outbox.where('status').anyOf('pending', 'failed', 'syncing').count(), [], 0);
    const me = useLiveQuery(() => getSetting<{ name: string }>('me'), [], undefined);
    const [checked, setChecked] = useState(false);
    const [signedIn, setSignedIn] = useState(false);
    const { needRefresh: [needRefresh], updateServiceWorker } = useRegisterSW();

    useEffect(() => {
        void (async () => {
            // Online: confirm the session with the server. Offline: trust the last signed-in user on this phone.
            const ok = navigator.onLine ? await refreshSession() : Boolean(await getSetting('me'));
            setSignedIn(ok);
            setChecked(true);
            if (ok) void flushOutbox();
        })();
    }, []);

    if (!checked) return <div className="grid min-h-dvh place-items-center text-ink-soft">Loading…</div>;
    if (!signedIn) return <SignIn onDone={() => setSignedIn(true)} />;

    return (
        <div className="mx-auto flex min-h-dvh max-w-xl flex-col pb-[env(safe-area-inset-bottom)]">
            <header className="sticky top-0 z-10 flex items-center justify-between border-b border-concrete bg-surface/95 px-4 py-3 backdrop-blur">
                <Link to="/" className="text-base font-extrabold tracking-tight [font-stretch:115%]">Thabekhulu Site</Link>
                <button type="button" onClick={() => void flushOutbox()} disabled={!online || waiting === 0}
                    className={cn('flex items-center gap-1.5 rounded-full px-3 py-1 text-sm font-medium', online ? 'bg-line-wash text-line-deep' : 'bg-hivis-wash text-ink')}>
                    {online ? <RefreshCw className="size-3.5" aria-hidden /> : <CloudOff className="size-3.5" aria-hidden />}
                    {online ? (waiting ? `Send ${waiting} now` : 'All sent') : waiting ? `Offline, ${waiting} saved` : 'Offline'}
                </button>
            </header>
            {needRefresh && (
                <div role="status" className="flex items-center justify-between gap-3 bg-line-deep px-4 py-2.5 text-sm text-white">
                    A new version is ready.
                    <button className="font-semibold underline" onClick={() => void updateServiceWorker(true)}>Update now</button>
                </div>
            )}
            <main className="flex-1 px-4 py-5"><Outlet /></main>
            {me && <footer className="px-4 pb-4 text-center text-xs text-ink-soft">Signed in as {me.name}</footer>}
        </div>
    );
}

function SignIn({ onDone }: { onDone: () => void }) {
    const [email, setEmail] = useState('');
    const [password, setPassword] = useState('');
    const [code, setCode] = useState('');
    const [step, setStep] = useState<'password' | 'code'>('password');
    const [error, setError] = useState<string | null>(null);
    const [busy, setBusy] = useState(false);
    const online = useOnline();

    async function submit(e: FormEvent) {
        e.preventDefault();
        setBusy(true);
        setError(null);
        try {
            if (step === 'password') {
                if ((await login(email, password)) === 'two-factor') {
                    setStep('code');
                    return;
                }
            } else {
                await twoFactor(code);
            }
            await refreshSession();
            onDone();
        } catch (err) {
            setError(err instanceof ApiError ? Object.values(err.errors)[0]?.[0] ?? err.message : 'Could not reach the server.');
        } finally {
            setBusy(false);
        }
    }

    return (
        <div className="mx-auto grid min-h-dvh max-w-sm content-center gap-6 px-5">
            <div>
                <p className="text-lg font-extrabold tracking-tight [font-stretch:115%]">Thabekhulu Site</p>
                <h1 className="mt-4 text-2xl font-bold">{step === 'password' ? 'Sign in' : 'Enter your code'}</h1>
                <p className="mt-1 text-ink-soft">{step === 'password' ? 'Use the same email and password as the web app. You only need signal to sign in.' : 'Enter the 6-digit code from your authenticator app.'}</p>
            </div>
            <form onSubmit={submit} className="grid gap-4" noValidate>
                {step === 'password' ? (
                    <>
                        <Field label="Email address" name="email" type="email" autoComplete="username" value={email} onChange={(e) => setEmail(e.target.value)} />
                        <Field label="Password" name="password" type="password" autoComplete="current-password" value={password} onChange={(e) => setPassword(e.target.value)} />
                    </>
                ) : (
                    <Field label="Code" name="code" inputMode="numeric" autoComplete="one-time-code" value={code} onChange={(e) => setCode(e.target.value)} />
                )}
                {error && <p className="text-sm text-brick">{error}</p>}
                {!online && <p className="text-sm text-ink-soft">You're offline. Connect to sign in.</p>}
                <Button type="submit" size="lg" disabled={busy || !online}>{busy ? 'Signing in…' : 'Continue'}</Button>
            </form>
            <p className="text-xs text-ink-soft">For authorised users only. Sign-ins and activity are recorded. Forgot your password? Reset it on the web app, or ask your company administrator.</p>
        </div>
    );
}
