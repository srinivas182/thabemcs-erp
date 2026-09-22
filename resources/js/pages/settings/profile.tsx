import { Head, router, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useEffect, useState } from 'react';
import { PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Props {
    profile: { name: string; email: string; phone: string | null };
    twoFactor: { enabled: boolean; confirmed: boolean };
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <section className="grid gap-5 border-t-2 border-ink pt-4">
            <div>
                <h2 className="font-bold">{title}</h2>
                {description && <p className="mt-0.5 text-sm text-ink-soft">{description}</p>}
            </div>
            {children}
        </section>
    );
}

async function getJson<T>(url: string): Promise<T> {
    const response = await fetch(url, { headers: { Accept: 'application/json' } });
    return (await response.json()) as T;
}

export default function Profile({ profile, twoFactor }: Props) {
    const details = useForm({ name: profile.name, email: profile.email, phone: profile.phone ?? '' });
    const password = useForm({ current_password: '', password: '', password_confirmation: '' });
    const confirm = useForm({ code: '' });
    const [qr, setQr] = useState<string | null>(null);
    const [codes, setCodes] = useState<string[] | null>(null);

    // While 2FA is switched on but not yet confirmed, show the QR code to scan.
    useEffect(() => {
        if (twoFactor.enabled && !twoFactor.confirmed) {
            void getJson<{ svg: string }>('/user/two-factor-qr-code').then((r) => setQr(r.svg));
        }
    }, [twoFactor.enabled, twoFactor.confirmed]);

    function saveDetails(e: FormEvent) {
        e.preventDefault();
        details.put('/user/profile-information', { preserveScroll: true });
    }

    function savePassword(e: FormEvent) {
        e.preventDefault();
        password.put('/user/password', { preserveScroll: true, onSuccess: () => password.reset() });
    }

    function confirmTwoFactor(e: FormEvent) {
        e.preventDefault();
        confirm.post('/user/confirmed-two-factor-authentication', {
            preserveScroll: true,
            onSuccess: async () => {
                setQr(null);
                setCodes(await getJson<string[]>('/user/two-factor-recovery-codes'));
            },
        });
    }

    return (
        <>
            <Head title="Your profile" />
            <div className="mx-auto grid max-w-2xl gap-10">
                <PageHeader title="Your profile" />

                <Section title="Your details">
                    <form onSubmit={saveDetails} className="grid gap-5" noValidate>
                        <Field label="Full name" name="name" value={details.data.name} onChange={(e) => details.setData('name', e.target.value)} error={details.errors.name} />
                        <Field label="Email address" name="email" type="email" value={details.data.email} onChange={(e) => details.setData('email', e.target.value)} error={details.errors.email} />
                        <Field label="Mobile number" name="phone" type="tel" value={details.data.phone} onChange={(e) => details.setData('phone', e.target.value)} error={details.errors.phone} />
                        <div>
                            <Button type="submit" disabled={details.processing}>
                                {details.recentlySuccessful ? 'Saved' : 'Save details'}
                            </Button>
                        </div>
                    </form>
                </Section>

                <Section title="Password" description="Use at least 12 characters with upper and lower case letters, a number and a symbol.">
                    <form onSubmit={savePassword} className="grid gap-5" noValidate>
                        <Field label="Current password" name="current_password" type="password" autoComplete="current-password" value={password.data.current_password} onChange={(e) => password.setData('current_password', e.target.value)} error={password.errors.current_password} />
                        <Field label="New password" name="password" type="password" autoComplete="new-password" value={password.data.password} onChange={(e) => password.setData('password', e.target.value)} error={password.errors.password} />
                        <Field label="Confirm new password" name="password_confirmation" type="password" autoComplete="new-password" value={password.data.password_confirmation} onChange={(e) => password.setData('password_confirmation', e.target.value)} />
                        <div>
                            <Button type="submit" disabled={password.processing}>
                                {password.recentlySuccessful ? 'Password changed' : 'Change password'}
                            </Button>
                        </div>
                    </form>
                </Section>

                <Section
                    title="Two-factor authentication"
                    description="Adds a 6-digit code from an authenticator app (Google Authenticator, Microsoft Authenticator) when you sign in."
                >
                    {!twoFactor.enabled && (
                        <div>
                            <Button onClick={() => router.post('/user/two-factor-authentication', {}, { preserveScroll: true })}>Turn on two-factor authentication</Button>
                        </div>
                    )}

                    {twoFactor.enabled && !twoFactor.confirmed && (
                        <form onSubmit={confirmTwoFactor} className="grid gap-5" noValidate>
                            <p className="text-sm">Scan this code with your authenticator app, then enter the 6-digit code it shows.</p>
                            {qr && <div className="w-fit rounded-[var(--radius-control)] border border-concrete bg-white p-3" dangerouslySetInnerHTML={{ __html: qr }} />}
                            <Field label="Code from your app" name="code" inputMode="numeric" autoComplete="one-time-code" value={confirm.data.code} onChange={(e) => confirm.setData('code', e.target.value)} error={confirm.errors.code} />
                            <div>
                                <Button type="submit" disabled={confirm.processing}>
                                    Confirm and turn on
                                </Button>
                            </div>
                        </form>
                    )}

                    {codes && (
                        <div className="rounded-[var(--radius-control)] border border-hivis/50 bg-hivis-wash p-4">
                            <p className="font-semibold">Save your recovery codes</p>
                            <p className="mt-1 text-sm">Each code works once if you lose your phone. Store them somewhere safe; they won't be shown again.</p>
                            <ul className="mt-3 grid grid-cols-2 gap-1 font-mono text-sm">
                                {codes.map((c) => (
                                    <li key={c}>{c}</li>
                                ))}
                            </ul>
                        </div>
                    )}

                    {twoFactor.confirmed && !codes && (
                        <div className="flex flex-wrap items-center gap-3">
                            <span className="rounded-full bg-line-wash px-3 py-1 text-sm font-semibold text-line-deep">Two-factor authentication is on</span>
                            <Button variant="secondary" size="sm" onClick={async () => setCodes(await getJson<string[]>('/user/two-factor-recovery-codes'))}>
                                Show recovery codes
                            </Button>
                            <Button
                                variant="ghost"
                                size="sm"
                                className="text-brick"
                                onClick={() => window.confirm('Turn off two-factor authentication?') && router.delete('/user/two-factor-authentication', { preserveScroll: true })}
                            >
                                Turn off
                            </Button>
                        </div>
                    )}
                </Section>
            </div>
        </>
    );
}

Profile.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
