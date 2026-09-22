import type { ReactNode } from 'react';

/**
 * Sign-in screens. The left panel carries the development cycle as a quiet
 * signature of the product; the form stays plain and fast on phones.
 */
export default function AuthLayout({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <div className="grid min-h-dvh lg:grid-cols-[1.1fr_1fr]">
            <section className="relative hidden overflow-hidden bg-line-deep p-12 text-white lg:flex lg:flex-col lg:justify-between">
                <p className="text-lg font-extrabold tracking-tight [font-stretch:115%]">Thabekhulu</p>
                <div>
                    <p className="max-w-md text-4xl leading-[1.05] font-bold [font-stretch:88%]">
                        Plan, fund, approve, build, sell and close every development from one place.
                    </p>
                    <ol className="mt-10 flex flex-wrap items-center gap-x-3 gap-y-2 text-sm text-white/75" aria-label="Development cycle">
                        {['Plan', 'Fund', 'Land', 'Approve', 'Build', 'Sell / rent', 'Close'].map((s, i, all) => (
                            <li key={s} className="flex items-center gap-3">
                                <span>{s}</span>
                                {i < all.length - 1 && <span aria-hidden className="h-px w-5 bg-white/40" />}
                            </li>
                        ))}
                    </ol>
                </div>
                <p className="text-xs text-white/60">For Steve Maqueens and Thabekhulu Development Group</p>
            </section>

            <section className="flex items-center justify-center px-5 py-12">
                <div className="w-full max-w-sm">
                    <p className="mb-8 text-lg font-extrabold tracking-tight [font-stretch:115%] lg:hidden">Thabekhulu</p>
                    <h1 className="text-2xl font-bold">{title}</h1>
                    {description && <p className="mt-2 text-ink-soft">{description}</p>}
                    <div className="mt-8">{children}</div>
                </div>
            </section>
        </div>
    );
}
