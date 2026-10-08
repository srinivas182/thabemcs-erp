import { Head, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Props {
    settings: { twoFactorRequired: boolean };
    people: { total: number; withTwoFactor: number };
}

/** Settings that apply to the whole instance. Super Admins only. */
export default function PlatformSettings({ settings, people }: Props) {
    const [saving, setSaving] = useState(false);
    const required = settings.twoFactorRequired;
    const notReady = people.total - people.withTwoFactor;

    const set = (value: boolean) => {
        setSaving(true);
        router.put('/platform/settings', { two_factor_required: value }, { preserveScroll: true, onFinish: () => setSaving(false) });
    };

    return (
        <>
            <Head title="Platform settings" />
            <div className="mx-auto grid max-w-3xl gap-5">
                <PageHeader title="Platform settings" description="Settings that apply to every company on this instance." />

                <section className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <h2 className="text-lg font-bold">Two-factor authentication</h2>
                            <p className="mt-1 max-w-xl text-sm text-ink-soft">
                                When this is on, everybody who signs in must set up an authenticator app before they can do
                                anything else &mdash; you included. When it is off, nobody is asked, which suits a demonstration
                                or test instance.
                            </p>
                        </div>
                        <span className={cn('rounded-full px-3 py-1 text-sm font-medium',
                            required ? 'bg-line-wash text-line-deep' : 'bg-plaster text-ink-soft')}>
                            {required ? 'Required' : 'Optional'}
                        </span>
                    </div>

                    <dl className="flex flex-wrap gap-6 border-y border-concrete py-3 text-sm">
                        <div><dt className="text-ink-soft">People who can sign in</dt><dd className="text-xl font-semibold">{people.total}</dd></div>
                        <div><dt className="text-ink-soft">Already set up</dt><dd className="text-xl font-semibold">{people.withTwoFactor}</dd></div>
                        <div><dt className="text-ink-soft">Would be asked to set it up</dt><dd className="text-xl font-semibold">{notReady}</dd></div>
                    </dl>

                    {required ? (
                        <div className="grid gap-3">
                            <p className="rounded-[var(--radius-control)] bg-brick-wash p-3 text-sm text-brick">
                                Turning this off means accounts are protected by a password alone. Only do that on a test or
                                demonstration instance, never on one holding real client data.
                            </p>
                            <Button variant="ghost" className="justify-self-start text-brick" disabled={saving} onClick={() => set(false)}>
                                Make two-factor optional
                            </Button>
                        </div>
                    ) : (
                        <div className="grid gap-3">
                            {notReady > 0 && (
                                <p className="rounded-[var(--radius-control)] bg-hivis/10 p-3 text-sm">
                                    {notReady} {notReady === 1 ? 'person has' : 'people have'} not set this up yet. They will be
                                    asked to do it the next time they sign in, and cannot work until they have. Tell them before
                                    you switch it on.
                                </p>
                            )}
                            <Button className="justify-self-start" disabled={saving} onClick={() => set(true)}>
                                Require two-factor for everyone
                            </Button>
                        </div>
                    )}
                </section>

                <p className="text-sm text-ink-soft">
                    Before this instance holds real client data, two-factor should be required. A password on its own is one
                    leaked or reused password away from somebody else reading everything here.
                </p>
            </div>
        </>
    );
}

PlatformSettings.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
