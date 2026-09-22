import type { InputHTMLAttributes, ReactNode } from 'react';
import { cn } from './cn';

interface FieldProps extends InputHTMLAttributes<HTMLInputElement> {
    label: string;
    error?: string;
    hint?: ReactNode;
}

/** A labelled text input with inline error and hint text. */
export function Field({ label, error, hint, id, className, ...props }: FieldProps) {
    const inputId = id ?? props.name;
    const describedBy = error ? `${inputId}-error` : hint ? `${inputId}-hint` : undefined;

    return (
        <div className="grid gap-1.5">
            <label htmlFor={inputId} className="text-sm font-medium text-ink">
                {label}
            </label>
            <input
                id={inputId}
                aria-invalid={error ? true : undefined}
                aria-describedby={describedBy}
                className={cn(
                    'h-11 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base text-ink placeholder:text-ink-soft/70',
                    'focus:border-line focus:outline-none focus:ring-2 focus:ring-line/20',
                    error && 'border-brick focus:border-brick focus:ring-brick/20',
                    className,
                )}
                {...props}
            />
            {error ? (
                <p id={`${inputId}-error`} className="text-sm text-brick">
                    {error}
                </p>
            ) : hint ? (
                <p id={`${inputId}-hint`} className="text-sm text-ink-soft">
                    {hint}
                </p>
            ) : null}
        </div>
    );
}
