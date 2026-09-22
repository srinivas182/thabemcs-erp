import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/auth-layout';

export default function ForgotPassword({ status }: { status?: string | null }) {
    const form = useForm({ email: '' });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/forgot-password');
    }

    return (
        <AuthLayout title="Reset your password" description="We'll email you a link to choose a new password.">
            <Head title="Reset password" />
            {status && <p className="mb-5 rounded-[var(--radius-control)] bg-line-wash px-3 py-2 text-sm text-line-deep">{status}</p>}
            <form onSubmit={submit} className="grid gap-5" noValidate>
                <Field
                    label="Email address"
                    name="email"
                    type="email"
                    autoComplete="username"
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />
                <Button type="submit" size="lg" disabled={form.processing}>
                    Email reset link
                </Button>
                <Link href="/login" className="text-center text-sm font-medium text-line hover:underline">
                    Back to sign in
                </Link>
            </form>
        </AuthLayout>
    );
}
