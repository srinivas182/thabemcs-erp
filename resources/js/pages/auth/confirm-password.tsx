import { Head, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/auth-layout';

export default function ConfirmPassword() {
    const form = useForm({ password: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/user/confirm-password', { onFinish: () => form.reset('password') });
    }

    return (
        <AuthLayout title="Confirm your password" description="This is a security-sensitive action. Enter your password to continue.">
            <Head title="Confirm password" />
            <form onSubmit={submit} className="grid gap-5" noValidate>
                <Field
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    autoFocus
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <Button type="submit" size="lg" disabled={form.processing}>
                    Confirm
                </Button>
            </form>
        </AuthLayout>
    );
}
