import { Head, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type FormEvent, useState } from 'react';
import AuthLayout from '@/layouts/auth-layout';

export default function TwoFactorChallenge() {
    const [useRecovery, setUseRecovery] = useState(false);
    const form = useForm({ code: '', recovery_code: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/two-factor-challenge');
    }

    return (
        <AuthLayout
            title="Confirm it's you"
            description={useRecovery ? 'Enter one of your emergency recovery codes.' : 'Enter the 6-digit code from your authenticator app.'}
        >
            <Head title="Two-factor authentication" />
            <form onSubmit={submit} className="grid gap-5" noValidate>
                {useRecovery ? (
                    <Field
                        label="Recovery code"
                        name="recovery_code"
                        autoComplete="one-time-code"
                        value={form.data.recovery_code}
                        onChange={(e) => form.setData('recovery_code', e.target.value)}
                        error={form.errors.recovery_code}
                    />
                ) : (
                    <Field
                        label="Authentication code"
                        name="code"
                        inputMode="numeric"
                        autoComplete="one-time-code"
                        value={form.data.code}
                        onChange={(e) => form.setData('code', e.target.value)}
                        error={form.errors.code}
                    />
                )}
                <Button type="submit" size="lg" disabled={form.processing}>
                    Continue
                </Button>
                <button type="button" onClick={() => setUseRecovery(!useRecovery)} className="text-sm font-medium text-line hover:underline">
                    {useRecovery ? 'Use an authentication code' : 'Use a recovery code'}
                </button>
            </form>
        </AuthLayout>
    );
}
