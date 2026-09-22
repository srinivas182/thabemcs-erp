import { Head, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/auth-layout';

export default function ResetPassword({ email, token }: { email: string; token: string }) {
    const form = useForm({ token, email, password: '', password_confirmation: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/reset-password', { onFinish: () => form.reset('password', 'password_confirmation') });
    }

    return (
        <AuthLayout title="Choose a new password">
            <Head title="Choose a new password" />
            <form onSubmit={submit} className="grid gap-5" noValidate>
                <Field label="Email address" name="email" type="email" value={form.data.email} readOnly error={form.errors.email} />
                <Field
                    label="New password"
                    name="password"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <Field
                    label="Confirm new password"
                    name="password_confirmation"
                    type="password"
                    autoComplete="new-password"
                    value={form.data.password_confirmation}
                    onChange={(e) => form.setData('password_confirmation', e.target.value)}
                />
                <Button type="submit" size="lg" disabled={form.processing}>
                    Save new password
                </Button>
            </form>
        </AuthLayout>
    );
}
