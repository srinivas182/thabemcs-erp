import { usePage } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import { HelpCircle, X } from 'lucide-react';
import { useEffect, useState } from 'react';
import type { SharedProps } from '@/types';

/**
 * Help for the page you are on. Opens with the button, or Shift+? anywhere, and closes with Escape.
 */
export function HelpDrawer() {
    const { help } = usePage<SharedProps>().props;
    const [open, setOpen] = useState(false);

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => {
            const typing = ['INPUT', 'TEXTAREA', 'SELECT'].includes((e.target as HTMLElement)?.tagName);
            if (e.key === '?' && !typing) setOpen(true);
            if (e.key === 'Escape') setOpen(false);
        };
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, []);

    if (!help) return null;

    return (
        <>
            <button type="button" onClick={() => setOpen(true)} aria-label="Help for this page"
                className="fixed right-4 bottom-4 z-30 flex size-11 items-center justify-center rounded-full border border-concrete bg-surface shadow-lg hover:border-line print:hidden">
                <HelpCircle className="size-5" aria-hidden />
            </button>

            {open && (
                <div className="fixed inset-0 z-40 flex justify-end print:hidden" role="dialog" aria-modal="true" aria-labelledby="help-title">
                    <button type="button" className="flex-1 bg-ink/30" aria-label="Close help" onClick={() => setOpen(false)} />
                    <div className={cn('grid w-full max-w-sm content-start gap-4 overflow-y-auto border-l border-concrete bg-surface p-5')}>
                        <div className="flex items-start justify-between gap-3">
                            <h2 id="help-title" className="text-xl font-bold">{help.title}</h2>
                            <button type="button" onClick={() => setOpen(false)} aria-label="Close help"><X className="size-5" /></button>
                        </div>
                        <p className="text-sm">{help.body}</p>
                        {help.steps.length > 0 && (
                            <ul className="grid list-disc gap-2 pl-4 text-sm text-ink-soft">
                                {help.steps.map((step, i) => <li key={i}>{step}</li>)}
                            </ul>
                        )}
                        <p className="text-xs text-ink-soft">Press <kbd className="rounded border border-concrete px-1">?</kbd> anywhere to open this. Still stuck? Ask your Company Admin, or use the support details on the sign-in page.</p>
                    </div>
                </div>
            )}
        </>
    );
}
