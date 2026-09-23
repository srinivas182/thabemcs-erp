import { cn } from '@thabekhulu/ui';
import { ChevronDown, X } from 'lucide-react';
import { useEffect, useId, useRef, useState } from 'react';

export type LookupType = 'projects' | 'suppliers' | 'people' | 'employees' | 'investors' | 'buyers' | 'tenants' | 'units' | 'funding-sources' | 'budget-lines';
type Option = { key: string; label: string };

async function fetchOptions(type: LookupType, params: Record<string, string | undefined>): Promise<Option[]> {
    const query = new URLSearchParams(Object.entries(params).filter(([, v]) => v !== undefined && v !== '') as [string, string][]);
    const response = await fetch(`/lookup/${type}?${query.toString()}`, { headers: { Accept: 'application/json' }, credentials: 'same-origin' });
    if (!response.ok) return [];
    return ((await response.json()) as { data: Option[] }).data;
}

/**
 * Search-as-you-type dropdown for company-wide lists (projects, suppliers, people, employees, investors,
 * cost codes of a project). Fetches at most 20 matches; resolves the label of a saved value on its own.
 */
export function LookupField({
    label, name, type, value, onChange, params, placeholder = 'Search', error, initialLabel, exclude,
}: {
    label: string;
    name: string;
    type: LookupType;
    value: string | number | null;
    onChange: (value: string, label: string) => void;
    /** Extra filters, e.g. { status: 'active' }, { types: 'contractor,subcontractor' }, { project: ulid } */
    params?: Record<string, string | undefined>;
    placeholder?: string;
    error?: string;
    initialLabel?: string | null;
    /** Keys to hide from results (e.g. already chosen). */
    exclude?: string[];
}) {
    const id = useId();
    const [text, setText] = useState('');
    const [selectedLabel, setSelectedLabel] = useState<string | null>(initialLabel ?? null);
    const [options, setOptions] = useState<Option[]>([]);
    const [open, setOpen] = useState(false);
    const [active, setActive] = useState(0);
    const timer = useRef<number | undefined>(undefined);
    const paramKey = JSON.stringify(params ?? {});

    // Resolve the label for a value that was saved earlier.
    useEffect(() => {
        const v = value === null || value === undefined ? '' : String(value);
        if (v === '') { setSelectedLabel(null); return; }
        if (selectedLabel) return;
        void fetchOptions(type, { ...params, key: v }).then((r) => setSelectedLabel(r[0]?.label ?? null));
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [value, type, paramKey]);

    useEffect(() => {
        if (!open) return;
        window.clearTimeout(timer.current);
        timer.current = window.setTimeout(() => {
            void fetchOptions(type, { ...params, q: text }).then((r) => { setOptions(r.filter((o) => !exclude?.includes(o.key))); setActive(0); });
        }, 200);
        return () => window.clearTimeout(timer.current);
        // eslint-disable-next-line react-hooks/exhaustive-deps
    }, [text, open, type, paramKey]);

    function choose(o: Option | null) {
        setSelectedLabel(o?.label ?? null);
        onChange(o?.key ?? '', o?.label ?? '');
        setText('');
        setOpen(false);
    }

    const hasValue = value !== null && value !== undefined && String(value) !== '';
    return (
        <div className="relative grid gap-1.5">
            {label && <label htmlFor={id} className="text-sm font-medium">{label}</label>}
            <div className={cn('flex h-11 items-center rounded-[var(--radius-control)] border bg-surface pr-2', error ? 'border-brick' : 'border-concrete', 'focus-within:ring-2 focus-within:ring-line/40')}>
                <input
                    id={id} name={name} role="combobox" aria-expanded={open} aria-controls={`${id}-list`} aria-autocomplete="list" aria-invalid={error ? true : undefined} autoComplete="off"
                    className="h-full min-w-0 flex-1 bg-transparent px-3 outline-none placeholder:text-ink-soft"
                    placeholder={hasValue ? (selectedLabel ?? '…') : placeholder}
                    value={open ? text : (hasValue ? (selectedLabel ?? '') : '')}
                    onFocus={() => setOpen(true)}
                    onBlur={() => window.setTimeout(() => setOpen(false), 150)}
                    onChange={(e) => { setText(e.target.value); setOpen(true); }}
                    onKeyDown={(e) => {
                        if (e.key === 'ArrowDown') { e.preventDefault(); setActive((a) => Math.min(a + 1, options.length - 1)); }
                        else if (e.key === 'ArrowUp') { e.preventDefault(); setActive((a) => Math.max(a - 1, 0)); }
                        else if (e.key === 'Enter' && open && options[active]) { e.preventDefault(); choose(options[active]!); }
                        else if (e.key === 'Escape') setOpen(false);
                    }}
                />
                {hasValue ? (
                    <button type="button" aria-label={`Clear ${label}`} className="text-ink-soft hover:text-ink" onMouseDown={(e) => e.preventDefault()} onClick={() => choose(null)}><X className="size-4" /></button>
                ) : <ChevronDown className="size-4 text-ink-soft" aria-hidden />}
            </div>
            {open && (
                <ul id={`${id}-list`} role="listbox" className="absolute top-full z-30 mt-1 max-h-64 w-full overflow-y-auto rounded-[var(--radius-control)] border border-concrete bg-surface py-1 shadow-lg">
                    {options.length === 0 && <li className="px-3 py-2 text-sm text-ink-soft">{text ? 'No matches' : 'Type to search'}</li>}
                    {options.map((o, i) => (
                        <li key={o.key} role="option" aria-selected={i === active} onMouseDown={(e) => e.preventDefault()} onClick={() => choose(o)} onMouseEnter={() => setActive(i)}
                            className={cn('cursor-pointer px-3 py-2 text-sm', i === active && 'bg-line-wash')}>{o.label}</li>
                    ))}
                </ul>
            )}
            {error && <p className="text-sm text-brick">{error}</p>}
        </div>
    );
}

/** Several choices from a lookup, shown as removable chips (e.g. report recipients, RFQ suppliers). */
export function LookupMulti({
    label, name, type, value, onChange, params, placeholder = 'Search to add', error, labels: initialLabels,
}: {
    label: string;
    name: string;
    type: LookupType;
    value: string[];
    onChange: (value: string[]) => void;
    params?: Record<string, string | undefined>;
    placeholder?: string;
    error?: string;
    labels?: Record<string, string>;
}) {
    const [labels, setLabels] = useState<Record<string, string>>(initialLabels ?? {});
    return (
        <div className="grid gap-2">
            <LookupField label={label} name={name} type={type} value="" params={params} placeholder={placeholder} error={error} exclude={value}
                onChange={(key, l) => { if (key && !value.includes(key)) { setLabels({ ...labels, [key]: l }); onChange([...value, key]); } }} />
            {value.length > 0 && (
                <ul className="flex flex-wrap gap-2">
                    {value.map((key) => (
                        <li key={key} className="flex items-center gap-1 rounded-full bg-line-wash px-3 py-1 text-sm text-line-deep">
                            {labels[key] ?? key}
                            <button type="button" aria-label={`Remove ${labels[key] ?? key}`} onClick={() => onChange(value.filter((k) => k !== key))}><X className="size-3.5" /></button>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}
