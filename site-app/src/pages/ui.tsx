import { cn } from '@thabekhulu/ui';
import type { ReactNode, TextareaHTMLAttributes } from 'react';

export function TextArea({ label, error, optional, ...props }: TextareaHTMLAttributes<HTMLTextAreaElement> & { label: string; error?: string; optional?: boolean }) {
    return (
        <div className="grid gap-1.5">
            <label htmlFor={props.name} className="text-sm font-medium">
                {label} {optional && <span className="font-normal text-ink-soft">(optional)</span>}
            </label>
            <textarea id={props.name} rows={3} className={cn('rounded-[var(--radius-control)] border border-concrete bg-surface p-3 text-base focus:border-line focus:outline-none', error && 'border-brick')} {...props} />
            {error && <p className="text-sm text-brick">{error}</p>}
        </div>
    );
}

export function Choice<T extends string>({ label, value, options, onChange, columns = 3 }: { label: string; value: T; options: { key: T; label: string }[]; onChange: (v: T) => void; columns?: 2 | 3 }) {
    return (
        <fieldset>
            <legend className="text-sm font-medium">{label}</legend>
            <div className={cn('mt-1.5 grid gap-2', columns === 2 ? 'grid-cols-2' : 'grid-cols-3')}>
                {options.map((o) => (
                    <button key={o.key} type="button" aria-pressed={value === o.key} onClick={() => onChange(o.key)}
                        className={cn('min-h-11 rounded-[var(--radius-control)] border px-2 text-sm font-medium', value === o.key ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>
                        {o.label}
                    </button>
                ))}
            </div>
        </fieldset>
    );
}

export function PhotoInput({ label, facing, onChange, preview }: { label: string; facing: 'user' | 'environment'; onChange: (file: File | null) => void; preview: string | null }) {
    return (
        <div className="grid gap-1.5">
            <span className="text-sm font-medium">{label}</span>
            <label className="grid min-h-32 cursor-pointer place-items-center overflow-hidden rounded-[var(--radius-panel)] border-2 border-dashed border-concrete bg-surface text-sm text-ink-soft">
                {preview ? <img src={preview} alt="" className="max-h-64 w-full object-cover" /> : 'Tap to take a photo'}
                <input type="file" accept="image/*" capture={facing} className="sr-only" onChange={(e) => onChange(e.target.files?.[0] ?? null)} />
            </label>
        </div>
    );
}

export function Page({ title, children }: { title: string; children: ReactNode }) {
    return (
        <div className="grid gap-5">
            <h1 className="text-2xl font-bold">{title}</h1>
            {children}
        </div>
    );
}
