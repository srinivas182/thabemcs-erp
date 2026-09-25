import { Link, router, usePage } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import { HelpDrawer } from '@/components/help-drawer';
import { Globe, Bell, Building2, CalendarCheck, ClipboardList, Handshake, KeyRound, Gauge, History, Inbox, ListTree, LogOut, Map as MapIcon, Menu, Plug, Search, ShieldCheck, Users, X } from 'lucide-react';
import CommandPalette from '@/components/command-palette';
import { type ReactNode, useEffect, useState } from 'react';
import { MODULE_ROUTES, NAV_GROUPS } from '@/components/navigation';
import type { SharedProps } from '@/types';

export default function AppLayout({ children }: { children: ReactNode }) {
    const { auth, company, flash, app, can, notifications } = usePage<SharedProps>().props;
    const [searching, setSearching] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            if ((e.ctrlKey || e.metaKey) && e.key.toLowerCase() === 'k') {
                e.preventDefault();
                setSearching(true);
            }
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);
    const url = usePage().url;
    const [open, setOpen] = useState(false);
    const enabled = new Map(company?.modules.map((m) => [m.key, m.label]) ?? []);

    const sidebar = (
        <nav aria-label="Main" className="flex h-full flex-col gap-6 px-4 py-5">
            <div>
                <p className="text-[15px] font-extrabold tracking-tight [font-stretch:112%]">Thabekhulu</p>
                <p className="text-sm text-ink-soft">{company?.name ?? 'Platform administration'}</p>
            </div>

            <div className="grid gap-0.5">
                <NavLink href="/" active={url === '/'} icon={<CalendarCheck className="size-4" aria-hidden />}>
                    My day
                </NavLink>
                {can.viewPortfolio && (
                    <NavLink href="/dashboard/portfolio" active={url.startsWith('/dashboard/portfolio')} icon={<Gauge className="size-4" aria-hidden />}>
                        {company ? 'Portfolio' : 'Group portfolio'}
                    </NavLink>
                )}
                {can.viewPortfolio && (
                    <NavLink href="/dashboard/map" active={url.startsWith('/dashboard/map')} icon={<MapIcon className="size-4" aria-hidden />}>
                        Command centre
                    </NavLink>
                )}
                {company && can.viewSales && (
                    <NavLink href="/sales/agreements" active={url.startsWith('/sales')} icon={<Handshake className="size-4" aria-hidden />}>
                        Sales
                    </NavLink>
                )}
                {company && can.viewRentals && (
                    <NavLink href="/rentals" active={url.startsWith('/rentals')} icon={<KeyRound className="size-4" aria-hidden />}>
                        Rentals
                    </NavLink>
                )}
                {company && can.manageContent && (
                    <NavLink href="/website/pages" active={url.startsWith('/website')} icon={<Globe className="size-4" aria-hidden />}>
                        Website
                    </NavLink>
                )}
                {company && can.manageForms && (
                    <NavLink href="/forms" active={url.startsWith('/forms')} icon={<ClipboardList className="size-4" aria-hidden />}>
                        Forms
                    </NavLink>
                )}
                {company && can.manageIntegrations && (
                    <NavLink href="/settings/integrations" active={url.startsWith('/settings/integrations')} icon={<Plug className="size-4" aria-hidden />}>
                        Integrations
                    </NavLink>
                )}
                {company && can.manageMasterData && (
                    <NavLink href="/settings/master-data" active={url.startsWith('/settings/master-data')} icon={<ListTree className="size-4" aria-hidden />}>
                        Master data
                    </NavLink>
                )}
                {company && can.managePopia && (
                    <NavLink href="/settings/popia" active={url.startsWith('/settings/popia')} icon={<ShieldCheck className="size-4" aria-hidden />}>
                        POPIA
                    </NavLink>
                )}
                {company && (
                    <NavLink href="/inbox" active={url.startsWith('/inbox')} icon={<Inbox className="size-4" aria-hidden />}>
                        Approvals
                    </NavLink>
                )}
                {can.manageCompanies && (
                    <NavLink href="/platform/companies" active={url.startsWith('/platform')} icon={<Building2 className="size-4" aria-hidden />}>
                        Companies
                    </NavLink>
                )}
                {can.viewAuditLog && (
                    <NavLink href="/settings/activity" active={url.startsWith('/settings/activity')} icon={<History className="size-4" aria-hidden />}>
                        Audit log
                    </NavLink>
                )}
                {can.manageUsers && (
                    <NavLink href="/settings/users" active={url.startsWith('/settings/users')} icon={<Users className="size-4" aria-hidden />}>
                        People
                    </NavLink>
                )}
            </div>

            {NAV_GROUPS.map((group) => {
                const items = group.modules.filter((key) => enabled.has(key));
                if (items.length === 0) return null;

                return (
                    <div key={group.title}>
                        <p className="mb-1.5 px-3 text-xs font-semibold text-ink-soft">{group.title}</p>
                        {/* Module screens are delivered sprint by sprint; items become links as they ship. */}
                        <ul className="grid gap-0.5">
                            {items.map((key) => (
                                <li key={key}>
                                    {MODULE_ROUTES[key] ? (
                                        <NavLink href={MODULE_ROUTES[key]} active={url.startsWith(MODULE_ROUTES[key])} icon={null}>
                                            {enabled.get(key)}
                                        </NavLink>
                                    ) : (
                                    <span
                                        aria-disabled="true"
                                        title="Being built in an upcoming sprint"
                                        className="block cursor-default rounded-[var(--radius-control)] px-3 py-1.5 text-sm text-ink-soft/70"
                                    >
                                        {enabled.get(key)}
                                    </span>
                                    )}
                                </li>
                            ))}
                        </ul>
                    </div>
                );
            })}
        </nav>
    );

    return (
        <>
            <a href="#main" className="sr-only rounded-[var(--radius-control)] bg-ink px-4 py-2 text-white focus:not-sr-only focus:absolute focus:top-2 focus:left-2 focus:z-50">Skip to the main content</a>
                    <div className="min-h-dvh lg:grid lg:grid-cols-[248px_1fr]">
            <aside className="print:hidden hidden border-r border-concrete bg-surface lg:block">{sidebar}</aside>

            {open && (
                <div className="fixed inset-0 z-40 lg:hidden" role="dialog" aria-modal="true" aria-label="Menu">
                    <button className="absolute inset-0 bg-ink/30" onClick={() => setOpen(false)} aria-label="Close menu" />
                    <aside className="print:hidden absolute inset-y-0 left-0 w-72 overflow-y-auto bg-surface shadow-xl">{sidebar}</aside>
                </div>
            )}

            <div className="flex min-w-0 flex-col">
                <header className="print:hidden flex h-14 items-center justify-between gap-3 border-b border-concrete bg-surface px-4 lg:px-8">
                    <button className="rounded p-1.5 lg:hidden" onClick={() => setOpen(!open)} aria-label="Open menu">
                        {open ? <X className="size-5" /> : <Menu className="size-5" />}
                    </button>
                    <button
                        onClick={() => setSearching(true)}
                        className="flex h-9 w-full max-w-sm items-center gap-2 rounded-[var(--radius-control)] border border-concrete bg-plaster px-3 text-sm text-ink-soft hover:border-ink-soft/40"
                    >
                        <Search className="size-4" aria-hidden />
                        <span className="flex-1 text-left">Search projects, people…</span>
                        <kbd className="hidden rounded border border-concrete px-1.5 text-xs sm:inline">Ctrl K</kbd>
                    </button>
                    <div className="flex items-center gap-2">
                        <Link
                            href="/notifications"
                            className="relative rounded-[var(--radius-control)] p-2 text-ink-soft hover:bg-concrete-soft hover:text-ink"
                            aria-label={notifications.unread ? `Notifications, ${notifications.unread} unread` : 'Notifications'}
                        >
                            <Bell className="size-5" />
                            {notifications.unread > 0 && (
                                <span className="absolute top-0.5 right-0.5 grid min-w-4 place-items-center rounded-full bg-hivis px-1 text-[10px] font-bold text-ink tabular-nums">
                                    {notifications.unread > 9 ? '9+' : notifications.unread}
                                </span>
                            )}
                        </Link>
                        <Link href="/settings/profile" className="hidden rounded-[var(--radius-control)] px-2 py-1 text-right leading-tight hover:bg-concrete-soft sm:block" title={`Your profile. Times shown in ${app.timezone === 'Africa/Johannesburg' ? 'SAST' : app.timezone}`}>
                            <p className="text-sm font-semibold">{auth.user?.name}</p>
                            <p className="text-xs text-ink-soft">{auth.user?.jobTitle ?? (auth.user?.isSuperAdmin ? 'Super Admin' : '')}</p>
                        </Link>
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

                {auth.user?.isSuperAdmin && company && (
                    <div className="flex items-center justify-between gap-3 border-b border-hivis/40 bg-hivis-wash px-4 py-2 text-sm lg:px-8">
                        <span>
                            You are working in <strong>{company.name}</strong> as Super Admin.
                        </span>
                        <button className="font-semibold underline" onClick={() => router.delete('/platform/acting-company')}>
                            Back to platform view
                        </button>
                    </div>
                )}

                {(flash.success || flash.error) && (
                    <div
                        role="status" aria-live="polite"
                        className={cn(
                            'border-b px-4 py-2.5 text-sm lg:px-8',
                            flash.error ? 'border-brick/30 bg-brick-wash text-brick' : 'border-line/20 bg-line-wash text-line-deep',
                        )}
                    >
                        {flash.error ?? flash.success}
                    </div>
                )}

                <main id="main" tabIndex={-1} className="flex-1 px-4 py-6 lg:px-8 lg:py-8">{children}</main>
                {searching && <CommandPalette onClose={() => setSearching(false)} />}
            </div>
            </div>
            <HelpDrawer />
        </>
    );
}

function NavLink({ href, active, icon, children }: { href: string; active: boolean; icon: ReactNode; children: ReactNode }) {
    return (
        <Link
            href={href}
            aria-current={active ? 'page' : undefined}
            className={cn(
                'flex items-center gap-2 rounded-[var(--radius-control)] px-3 py-2 text-sm',
                active ? 'bg-line-wash font-semibold text-line-deep' : 'text-ink hover:bg-concrete-soft',
            )}
        >
            {icon}
            {children}
        </Link>
    );
}
