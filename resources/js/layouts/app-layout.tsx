import { Link, router, usePage } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import { CalendarCheck, LogOut, Menu, X } from 'lucide-react';
import { type ReactNode, useState } from 'react';
import { NAV_GROUPS } from '@/components/navigation';
import type { SharedProps } from '@/types';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, company, flash, app } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);
    const enabled = new Map(company?.modules.map((m) => [m.key, m.label]) ?? []);

    const sidebar = (
        <nav aria-label="Main" className="flex h-full flex-col gap-6 px-4 py-5">
            <div>
                <p className="text-[15px] font-extrabold tracking-tight [font-stretch:112%]">Thabekhulu</p>
                <p className="text-sm text-ink-soft">{company?.name ?? 'Platform administration'}</p>
            </div>

            <Link
                href="/"
                className="flex items-center gap-2 rounded-[var(--radius-control)] bg-line-wash px-3 py-2 text-sm font-semibold text-line-deep"
            >
                <CalendarCheck className="size-4" aria-hidden /> My day
            </Link>

            {NAV_GROUPS.map((group) => {
                const items = group.modules.filter((key) => enabled.has(key));
                if (items.length === 0) return null;

                return (
                    <div key={group.title}>
                        <p className="mb-1.5 px-3 text-xs font-semibold text-ink-soft">{group.title}</p>
                        <ul className="grid gap-0.5">
                            {items.map((key) => (
                                <li key={key}>
                                    {/* Module screens are delivered sprint by sprint; items become links as they ship. */}
                                    <span
                                        aria-disabled="true"
                                        title="Being built in an upcoming sprint"
                                        className="block cursor-default rounded-[var(--radius-control)] px-3 py-1.5 text-sm text-ink-soft/70"
                                    >
                                        {enabled.get(key)}
                                    </span>
                                </li>
                            ))}
                        </ul>
                    </div>
                );
            })}
        </nav>
    );

    return (
        <div className="min-h-dvh lg:grid lg:grid-cols-[248px_1fr]">
            <aside className="hidden border-r border-concrete bg-surface lg:block">{sidebar}</aside>

            {open && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
                    <button className="absolute inset-0 bg-ink/30" onClick={() => setOpen(false)} aria-label="Close menu" />
                    <aside className="absolute inset-y-0 left-0 w-72 overflow-y-auto bg-surface shadow-xl">{sidebar}</aside>
                </div>
            )}

            <div className="flex min-w-0 flex-col">
                <header className="flex h-14 items-center justify-between gap-3 border-b border-concrete bg-surface px-4 lg:px-8">
                    <button className="rounded p-1.5 lg:hidden" onClick={() => setOpen(!open)} aria-label="Open menu">
                        {open ? <X className="size-5" /> : <Menu className="size-5" />}
                    </button>
                    <p className="hidden text-sm text-ink-soft sm:block">
                        Times shown in {app.timezone === 'Africa/Johannesburg' ? 'SAST' : app.timezone}
                    </p>
                    <div className="flex items-center gap-3">
                        <div className="text-right leading-tight">
                            <p className="text-sm font-semibold">{auth.user?.name}</p>
                            <p className="text-xs text-ink-soft">{auth.user?.jobTitle ?? (auth.user?.isSuperAdmin ? 'Super Admin' : '')}</p>
                        </div>
                        <button
                            onClick={() => router.post('/logout')}
                            className="rounded-[var(--radius-control)] p-2 text-ink-soft hover:bg-concrete-soft hover:text-ink"
                            aria-label="Sign out"
                            title="Sign out"
                        >
                            <LogOut className="size-4" />
                        </button>
                    </div>
                </header>

                {(flash.success || flash.error) && (
                    <div
                        role="status"
                        className={cn(
                            'border-b px-4 py-2.5 text-sm lg:px-8',
                            flash.error ? 'border-brick/30 bg-brick-wash text-brick' : 'border-line/20 bg-line-wash text-line-deep',
                        )}
                    >
                        {flash.error ?? flash.success}
                    </div>
                )}

                <main className="flex-1 px-4 py-6 lg:px-8 lg:py-8">{children}</main>
            </div>
        </div>
    );
}
