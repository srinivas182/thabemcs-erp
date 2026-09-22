import { cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';

/** Page heading with an optional action on the right. */
export function PageHeader({ title, description, action }: { title: string; description?: string; action?: ReactNode }) {
    return (
        <div className="flex flex-wrap items-end justify-between gap-4">
            <div>
                <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{title}</h1>
                {description && <p className="mt-1 max-w-2xl text-ink-soft">{description}</p>}
            </div>
            {action}
        </div>
    );
}

/** Usage against a limit, e.g. "12 of 50". Null limit = unlimited. */
export function Usage({ used, limit }: { used: number; limit: number | null }) {
    const ratio = limit ? used / limit : 0;
    return (
        <span className={cn('tabular-nums', ratio >= 1 ? 'font-semibold text-brick' : ratio >= 0.8 ? 'font-semibold text-ink' : 'text-ink')}>
            {used}
            <span className="text-ink-soft"> of {limit ?? 'unlimited'}</span>
        </span>
    );
}

export function StatusBadge({ active, activeLabel = 'Active', inactiveLabel = 'Suspended' }: { active: boolean; activeLabel?: string; inactiveLabel?: string }) {
    return (
        <span
            className={cn(
                'inline-flex rounded-full px-2 py-0.5 text-xs font-semibold',
                active ? 'bg-line-wash text-line-deep' : 'bg-brick-wash text-brick',
            )}
        >
            {active ? activeLabel : inactiveLabel}
        </span>
    );
}

export const tableClass = 'w-full text-left text-sm [&_th]:px-3 [&_th]:py-2.5 [&_th]:font-semibold [&_th]:text-ink-soft [&_td]:px-3 [&_td]:py-3 [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete';

export const selectClass =
    'h-11 w-full rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base text-ink focus:border-line focus:outline-none focus:ring-2 focus:ring-line/20';
