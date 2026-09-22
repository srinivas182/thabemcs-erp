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

/** Previous / next links for a Laravel paginator. */
export function Pager({ prev, next, page, last }: { prev: string | null; next: string | null; page: number; last: number }) {
    if (last <= 1) return null;
    const link = 'rounded-[var(--radius-control)] border border-concrete bg-surface px-3 py-1.5 text-sm hover:bg-concrete-soft';
    return (
        <nav className="flex items-center justify-between text-sm" aria-label="Pages">
            {prev ? <a className={link} href={prev}>Previous</a> : <span />}
            <span className="text-ink-soft">
                Page {page} of {last}
            </span>
            {next ? <a className={link} href={next}>Next</a> : <span />}
        </nav>
    );
}

export function formatDateTime(iso: string | null): string {
    if (!iso) return '';
    return new Intl.DateTimeFormat('en-ZA', { dateStyle: 'medium', timeStyle: 'short', timeZone: 'Africa/Johannesburg' }).format(new Date(iso));
}

const zar = new Intl.NumberFormat('en-ZA', { style: 'currency', currency: 'ZAR', maximumFractionDigits: 0 });

/** Rand amount, e.g. "R 12 500 000". */
export function formatRand(value: string | number | null): string {
    if (value === null || value === '') return '';
    return zar.format(Number(value));
}

export function formatDate(date: string | null): string {
    if (!date) return '';
    return new Intl.DateTimeFormat('en-ZA', { day: 'numeric', month: 'short', year: 'numeric' }).format(new Date(date));
}

/** Labelled native select, matching Field styling. */
export function SelectField({
    label,
    name,
    value,
    onChange,
    options,
    error,
    placeholder,
}: {
    label: string;
    name: string;
    value: string | number | null;
    onChange: (value: string) => void;
    options: { key: string | number; label: string }[];
    error?: string;
    placeholder?: string;
}) {
    return (
        <div className="grid gap-1.5">
            <label htmlFor={name} className="text-sm font-medium">
                {label}
            </label>
            <select id={name} name={name} className={selectClass} value={value ?? ''} onChange={(e) => onChange(e.target.value)} aria-invalid={error ? true : undefined}>
                {placeholder !== undefined && <option value="">{placeholder}</option>}
                {options.map((o) => (
                    <option key={o.key} value={o.key}>
                        {o.label}
                    </option>
                ))}
            </select>
            {error && <p className="text-sm text-brick">{error}</p>}
        </div>
    );
}
