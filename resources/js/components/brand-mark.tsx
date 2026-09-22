import { usePage } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import type { SharedProps } from '@/types';

/**
 * The client's logo if one is configured (BRAND_LOGO), otherwise a simple roof-line mark and wordmark.
 */
export function BrandMark({ inverted = false, className }: { inverted?: boolean; className?: string }) {
    const { brand } = usePage<SharedProps>().props;

    if (brand.logo) {
        return <img src={brand.logo} alt={brand.name} className={cn('h-10 w-auto', className)} />;
    }

    return (
        <span className={cn('inline-flex items-center gap-2.5', className)}>
            <svg viewBox="0 0 32 32" className="size-8 shrink-0" aria-hidden>
                <rect width="32" height="32" rx="7" className={inverted ? 'fill-white/15' : 'fill-line'} />
                <path d="M7 15.5 16 8l9 7.5" fill="none" strokeWidth="2.4" strokeLinecap="round" strokeLinejoin="round" className="stroke-white" />
                <path d="M12 15.5h8M16 15.5V24" fill="none" strokeWidth="2.4" strokeLinecap="round" className={inverted ? 'stroke-hivis' : 'stroke-hivis'} />
            </svg>
            <span className="text-lg leading-none font-extrabold tracking-tight [font-stretch:115%]">{brand.shortName}</span>
        </span>
    );
}
