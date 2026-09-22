import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type FormEvent, type KeyboardEvent, useState } from 'react';
import AuthLayout from '@/layouts/auth-layout';

export default function Login({ status }: { status?: string | null }) {
    const form = useForm({ email: '', password: '', remember: false });
    const [showPassword, setShowPassword] = useState(false);
    const [capsLock, setCapsLock] = useState(false);

    function submit(event: FormEvent) {
        event.preventDefault();
        form.post('/login', { onFinish: () => form.reset('password') });
    }

    const watchCaps = (e: KeyboardEvent<HTMLInputElement>) => setCapsLock(e.getModifierState('CapsLock'));

    return (
        <AuthLayout title="Sign in" description="Use the email address your company administrator registered for you.">
            <Head title="Sign in" />
            {status && <p role="status" className="mb-5 rounded-[var(--radius-control)] bg-line-wash px-3 py-2 text-sm text-line-deep">{status}</p>}
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
                <div className="grid gap-1.5">
                    <Field
                        label="Password"
                        name="password"
                        type={showPassword ? 'text' : 'password'}
                        autoComplete="current-password"
                        value={form.data.password}
                        onChange={(e) => form.setData('password', e.target.value)}
                        onKeyUp={watchCaps}
                        onKeyDown={watchCaps}
                        error={form.errors.password}
                    />
                    <div className="flex items-center justify-between text-sm">
                        <span className="text-ink" aria-live="polite">{capsLock && 'Caps Lock is on'}</span>
                        <button type="button" className="font-medium text-ink-soft hover:text-ink" onClick={() => setShowPassword(!showPassword)} aria-pressed={showPassword}>
                            {showPassword ? 'Hide password' : 'Show password'}
                        </button>
                    </div>
                </div>
                <div className="flex items-center justify-between text-sm">
                    <label className="flex items-center gap-2">
                        <input type="checkbox" className="size-4 accent-line" checked={form.data.remember} onChange={(e) => form.setData('remember', e.target.checked)} />
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
