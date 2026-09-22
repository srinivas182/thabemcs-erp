import { usePage } from '@inertiajs/react';
import { HardHat, LifeBuoy, ShieldCheck } from 'lucide-react';
import type { ReactNode } from 'react';
import { BrandMark } from '@/components/brand-mark';
import type { SharedProps } from '@/types';

/**
 * Sign-in, password reset and two-factor screens. The left panel carries the brand and the development
 * cycle; the right panel keeps the form plain and fast on phones, with the authorised-use notice,
 * support contact and a link to the site app underneath.
 */
export default function AuthLayout({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    const { brand } = usePage<SharedProps>().props;
    const year = new Date().getFullYear();

    return (
        <div className="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
            <section className="relative hidden overflow-hidden bg-line-deep p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <BrandMark inverted />
                <div>
                    <p className="max-w-md text-4xl leading-[1.05] font-bold [font-stretch:88%]">{brand.tagline}</p>
                    <ol className="mt-10 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-white/75" aria-label="Development cycle">
                        {['Plan', 'Fund', 'Land', 'Approve', 'Build', 'Sell / rent', 'Close'].map((s, i, all) => (
                            <li key={s} className="flex items-center gap-3">
                                <span>{s}</span>
                                {i < all.length - 1 && <span aria-hidden className="h-px w-5 bg-white/40" />}
                            </li>
                        ))}
                    </ol>
                </div>
                <p className="text-xs text-white/60">{brand.name}, for {brand.owner}</p>
                {/* Faint roof-line motif, echoing the brand mark. */}
                <svg aria-hidden viewBox="0 0 400 200" className="pointer-events-none absolute -right-16 bottom-16 w-[28rem] opacity-[0.07]">
                    <path d="M10 190 200 30l190 160" fill="none" stroke="white" strokeWidth="18" strokeLinejoin="round" />
                </svg>
            </section>

            <section className="flex flex-col px-5 py-10 sm:px-8">
                <div className="mx-auto flex w-full max-w-sm flex-1 flex-col justify-center">
                    <BrandMark className="mb-10 lg:hidden" />
                    <h1 className="text-2xl font-bold">{title}</h1>
                    {description && <p className="mt-2 text-ink-soft">{description}</p>}
                    <div className="mt-8">{children}</div>

                    <a href="/site/" className="mt-8 flex items-center gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-3 text-sm hover:border-line">
                        <span className="grid size-9 shrink-0 place-items-center rounded-[var(--radius-control)] bg-hivis-wash text-ink"><HardHat className="size-4" /></span>
                        <span>
                            <span className="block font-semibold">Working on site?</span>
                            <span className="block text-ink-soft">Open the site app. It works offline and can be added to your phone's home screen.</span>
                        </span>
                    </a>
                </div>

                <footer className="mx-auto mt-10 grid w-full max-w-sm gap-3 text-xs text-ink-soft">
                    <p className="flex gap-2">
                        <ShieldCheck className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                        <span>
                            For authorised users of {brand.owner} only. Sign-ins and activity are recorded. Personal information is processed in line with POPIA
                            {brand.privacyUrl ? <>; see the <a href={brand.privacyUrl} className="underline hover:text-ink" target="_blank" rel="noreferrer">privacy notice</a>.</> : '.'}
                        </span>
                    </p>
                    {(brand.supportEmail || brand.supportPhone) && (
                        <p className="flex gap-2">
                            <LifeBuoy className="mt-0.5 size-3.5 shrink-0" aria-hidden />
                            <span>
                                Need help signing in? Ask your company administrator, or contact support
                                {brand.supportEmail && <> at <a href={`mailto:${brand.supportEmail}`} className="underline hover:text-ink">{brand.supportEmail}</a></>}
                                {brand.supportPhone && <>{brand.supportEmail ? ' or ' : ' on '}<a href={`tel:${brand.supportPhone.replace(/\s/g, '')}`} className="underline hover:text-ink">{brand.supportPhone}</a></>}
                                {brand.supportHours && ` (${brand.supportHours})`}.
                            </span>
                        </p>
                    )}
                    <p>© {year} {brand.owner}{brand.poweredBy && <>. Built by {brand.poweredBy}</>}.</p>
                </footer>
            </section>
        </div>
    );
}
