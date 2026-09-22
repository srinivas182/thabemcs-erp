import { Head, router, useForm, usePage } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { PageHeader, selectClass, StatusBadge, tableClass, Usage } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { SharedProps } from '@/types';

interface UserRow {
    id: string;
    name: string;
    email: string;
    jobTitle: string | null;
    role: string | null;
    isActive: boolean;
    lastLoginAt: string | null;
}

interface Props {
    users: UserRow[];
    roles: { key: string; label: string }[];
    quota: { used: number; limit: number | null };
}

function lastSeen(iso: string | null): string {
    if (!iso) return 'Not signed in yet';
    return new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Johannesburg' }).format(new Date(iso));
}

export default function UsersIndex({ users, roles, quota }: Props) {
    const { auth } = usePage<SharedProps>().props;
    const [inviting, setInviting] = useState(false);
    const full = quota.limit !== null && quota.used >= quota.limit;
    const form = useForm({ name: '', email: '', job_title: '', phone: '', role: 'project-manager' });
    const roleLabel = new Map(roles.map((r) => [r.key, r.label]));

    function invite(event: FormEvent) {
        event.preventDefault();
        form.post('/settings/users', {
            preserveScroll: true,
            onSuccess: () => {
                form.reset();
                setInviting(false);
            },
        });
    }

    function update(user: UserRow, data: Record<string, string | boolean>) {
        router.patch(`/settings/users/${user.id}`, data, { preserveScroll: true });
    }

    const bind = (key: 'name' | 'email' | 'job_title' | 'phone') => ({
        name: key,
        value: form.data[key],
        onChange: (e: { target: { value: string } }) => form.setData(key, e.target.value),
        error: form.errors[key],
    });

    return (
        <>
            <Head title="People" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title="People"
                    description="Invite colleagues and contractors, and choose what each person can do."
                    action={
                        <div className="flex items-center gap-4">
                            <span className="text-sm">
                                Users: <Usage {...quota} />
                            </span>
                            <Button onClick={() => setInviting(!inviting)} disabled={full} title={full ? 'User limit reached. Ask the Super Admin to raise it.' : undefined}>
                                Invite person
                            </Button>
                        </div>
                    }
                />

                {inviting && (
                    <form onSubmit={invite} className="grid gap-5 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <p className="font-bold">Invite a person</p>
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Full name" {...bind('name')} />
                            <Field label="Email address" type="email" {...bind('email')} />
                            <Field label="Job title" {...bind('job_title')} placeholder="e.g. Site Manager, Durban North" />
                            <Field label="Mobile number" type="tel" {...bind('phone')} placeholder="082 123 4567" />
                        </div>
                        <div className="grid gap-1.5 sm:max-w-sm">
                            <label htmlFor="role" className="text-sm font-medium">
                                Role
                            </label>
                            <select id="role" className={selectClass} value={form.data.role} onChange={(e) => form.setData('role', e.target.value)}>
                                {roles.map((r) => (
                                    <option key={r.key} value={r.key}>
                                        {r.label}
                                    </option>
                                ))}
                            </select>
                            {form.errors.role && <p className="text-sm text-brick">{form.errors.role}</p>}
                        </div>
                        <div className="flex gap-3">
                            <Button type="submit" disabled={form.processing}>
                                Send invitation
                            </Button>
                            <Button type="button" variant="secondary" onClick={() => setInviting(false)}>
                                Cancel
                            </Button>
                        </div>
                    </form>
                )}

                <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                    <table className={tableClass}>
                        <thead>
                            <tr>
                                <th>Name</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Last signed in</th>
                                <th className="text-right">Access</th>
                            </tr>
                        </thead>
                        <tbody>
                            {users.map((u) => {
                                const isMe = u.email === auth.user?.email;
                                return (
                                    <tr key={u.id} className={u.isActive ? '' : 'opacity-60'}>
                                        <td>
                                            <p className="font-semibold">
                                                {u.name} {isMe && <span className="font-normal text-ink-soft">(you)</span>}
                                            </p>
                                            <p className="text-ink-soft">{u.jobTitle ? `${u.jobTitle}, ${u.email}` : u.email}</p>
                                        </td>
                                        <td className="min-w-52">
                                            <select
                                                aria-label={`Role for ${u.name}`}
                                                className={selectClass + ' h-9 text-sm'}
                                                value={u.role ?? ''}
                                                disabled={isMe}
                                                onChange={(e) => update(u, { role: e.target.value })}
                                            >
                                                {u.role === null && <option value="">No role</option>}
                                                {roles.map((r) => (
                                                    <option key={r.key} value={r.key}>
                                                        {roleLabel.get(r.key)}
                                                    </option>
                                                ))}
                                            </select>
                                        </td>
                                        <td>
                                            <StatusBadge active={u.isActive} inactiveLabel="Deactivated" />
                                        </td>
                                        <td className="text-ink-soft">{lastSeen(u.lastLoginAt)}</td>
                                        <td className="text-right">
                                            {!isMe && (
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className={u.isActive ? 'text-brick' : ''}
                                                    onClick={() => update(u, { is_active: !u.isActive })}
                                                >
                                                    {u.isActive ? 'Deactivate' : 'Reactivate'}
                                                </Button>
                                            )}
                                        </td>
                                    </tr>
                                );
                            })}
                        </tbody>
                    </table>
                </div>
            </div>
        </>
    );
}

UsersIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
