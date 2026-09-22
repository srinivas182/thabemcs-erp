import { Head, Link } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Row { id: string; name: string; kind: string; questions: number; active: boolean; version: number; submissions: number }

export default function Forms({ templates }: { templates: Row[] }) {
    return (
        <>
            <Head title="Forms" />
            <div className="mx-auto grid max-w-5xl gap-6">
                <PageHeader title="Forms" description="Build your own checklists (pre-pour, scaffold, handover, plant pre-use...). Site teams fill them in on the site app, offline." action={<Button asChild><Link href="/forms/new">Build a form</Link></Button>} />
                {templates.length === 0 ? <p className="text-ink-soft">No forms yet.</p> : (
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        {templates.map((t) => (
                            <li key={t.id} className={cn('flex flex-wrap items-center justify-between gap-3 p-3', !t.active && 'opacity-60')}>
                                <span><Link href={`/forms/${t.id}/edit`} className="font-semibold hover:underline">{t.name}</Link><span className="block text-sm text-ink-soft capitalize">{t.kind}, {t.questions} questions, version {t.version}{!t.active && ', switched off'}</span></span>
                                <span className="text-sm text-ink-soft">{t.submissions} completed</span>
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

Forms.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
