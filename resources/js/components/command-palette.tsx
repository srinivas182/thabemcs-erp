import { router } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import { Building2, FolderKanban, Search, User } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';

interface Result {
    type: 'project' | 'person' | 'company';
    title: string;
    subtitle: string;
    url: string | null;
}

const ICONS = { project: FolderKanban, person: User, company: Building2 };

/** Ctrl+K search across the records the user is allowed to see. */
export default function CommandPalette({ onClose }: { onClose: () => void }) {
    const [term, setTerm] = useState('');
    const [results, setResults] = useState<Result[]>([]);
    const [active, setActive] = useState(0);
    const [loading, setLoading] = useState(false);
    const input = useRef<HTMLInputElement>(null);

    useEffect(() => input.current?.focus(), []);

    useEffect(() => {
        if (term.trim().length < 2) {
            setResults([]);
            return;
        }
        const controller = new AbortController();
        const timer = window.setTimeout(async () => {
            setLoading(true);
            try {
                const response = await fetch(`/search?q=${encodeURIComponent(term)}`, {
                    headers: { Accept: 'application/json' },
                    signal: controller.signal,
                });
                const body = (await response.json()) as { results: Result[] };
                setResults(body.results);
                setActive(0);
            } catch {
                /* aborted or offline: keep the previous results */
            } finally {
                setLoading(false);
            }
        }, 200);
        return () => {
            controller.abort();
            window.clearTimeout(timer);
        };
    }, [term]);

    function open(result: Result | undefined) {
        if (!result?.url) return;
        onClose();
        router.visit(result.url);
    }

    function onKeyDown(e: React.KeyboardEvent) {
        if (e.key === 'Escape') onClose();
        if (e.key === 'ArrowDown') setActive((i) => Math.min(i + 1, results.length - 1));
        if (e.key === 'ArrowUp') setActive((i) => Math.max(i - 1, 0));
        if (e.key === 'Enter') open(results[active]);
    }

    return (
        <div className="fixed inset-0 z-50 flex items-start justify-center px-4 pt-[12vh]" role="dialog" aria-modal="true" aria-label="Search">
            <button className="absolute inset-0 bg-ink/40" onClick={onClose} aria-label="Close search" />
            <div className="relative w-full max-w-xl overflow-hidden rounded-[var(--radius-panel)] bg-surface shadow-2xl">
                <div className="flex items-center gap-3 border-b border-concrete px-4">
                    <Search className="size-5 text-ink-soft" aria-hidden />
                    <input
                        ref={input}
                        value={term}
                        onChange={(e) => setTerm(e.target.value)}
                        onKeyDown={onKeyDown}
                        placeholder="Search projects, people and companies"
                        className="h-14 flex-1 bg-transparent text-base outline-none"
                        role="combobox"
                        aria-expanded={results.length > 0}
                        aria-controls="search-results"
                    />
                </div>
                <ul id="search-results" role="listbox" className="max-h-[50vh] overflow-y-auto p-2">
                    {results.map((r, i) => {
                        const Icon = ICONS[r.type];
                        return (
                            <li
                                key={`${r.type}-${r.title}-${i}`}
                                role="option"
                                aria-selected={i === active}
                                onMouseEnter={() => setActive(i)}
                                onClick={() => open(r)}
                                className={cn(
                                    'flex items-center gap-3 rounded-[var(--radius-control)] px-3 py-2.5',
                                    i === active && 'bg-line-wash',
                                    r.url ? 'cursor-pointer' : 'cursor-default',
                                )}
                            >
                                <Icon className="size-4 shrink-0 text-ink-soft" />
                                <span className="min-w-0">
                                    <span className="block truncate font-medium">{r.title}</span>
                                    <span className="block truncate text-sm text-ink-soft">{r.subtitle}</span>
                                </span>
                            </li>
                        );
                    })}
                </ul>
                {term.trim().length >= 2 && !loading && results.length === 0 && (
                    <p className="px-5 pb-5 text-sm text-ink-soft">No matches for "{term}".</p>
                )}
                {term.trim().length < 2 && <p className="px-5 pb-5 text-sm text-ink-soft">Type at least two letters.</p>}
            </div>
        </div>
    );
}
