import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent } from 'react';
import AuthLayout from '@/layouts/auth-layout';

export default function Login() {
    const form = useForm({ email: '', password: '', remember: false });

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    }

    return (
        <AuthLayout title="Sign in" description="Use the email address your company administrator registered for you.">
            <Head title="Sign in" />
            <form onSubmit={submit} className="grid gap-5" noValidate>
                <Field
                    label="Email address"
                    name="email"
                    type="email"
                    autoComplete="username"
                    autoFocus
                    value={form.data.email}
                    onChange={(e) => form.setData('email', e.target.value)}
                    error={form.errors.email}
                />
                <Field
                    label="Password"
                    name="password"
                    type="password"
                    autoComplete="current-password"
                    value={form.data.password}
                    onChange={(e) => form.setData('password', e.target.value)}
                    error={form.errors.password}
                />
                <div className="flex items-center justify-between text-sm">
                    <label className="flex items-center gap-2">
                        <input
                            type="checkbox"
                            className="size-4 accent-line"
                            checked={form.data.remember}
                            onChange={(e) => form.setData('remember', e.target.checked)}
                        />
                        Keep me signed in
                    </label>
                    <Link href="/forgot-password" className="font-medium text-line hover:underline">
                        Forgot password?
                    </Link>
                </div>
                <Button type="submit" size="lg" disabled={form.processing}>
                    {form.processing ? 'Signing in…' : 'Sign in'}
                </Button>
            </form>
        </AuthLayout>
    );
}
