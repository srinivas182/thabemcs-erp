import { Head, router } from '@inertiajs/react';
import { Button, cn } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { formatDateTime, PageHeader, Pager, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Row {
    id: string; form: string; name: string | null; email: string | null; phone: string | null;
    answers: Record<string, string | number | boolean>; page: string | null; status: string;
    becameBuyer: boolean; becameTenant: boolean; at: string;
}

export default function Submissions({ submissions, filters }: {
    submissions: { data: Row[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    filters: { status: string };
}) {
    return (
        <>
            <Head title="Website enquiries" />
            <div className="mx-auto grid max-w-4xl gap-5">
                <PageHeader title="Website enquiries" description="What people sent through the forms on the website. Enquiries also appear as buyers or tenants, so the sales team works them like any other lead." />
                <div className="w-52">
                    <SelectField label="Show" name="status" value={filters.status} onChange={(v) => router.get('/website/enquiries', { status: v })}
                        options={[{ key: 'new', label: 'New' }, { key: 'actioned', label: 'Dealt with' }, { key: 'spam', label: 'Spam' }, { key: 'all', label: 'All' }]} />
                </div>

                <ul className="grid gap-2">
                    {submissions.data.map((s) => (
                        <li key={s.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-3', s.status === 'new' ? 'border-ink' : 'border-concrete')}>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{s.name ?? 'Someone'} <span className="font-normal text-ink-soft">via {s.form}</span></p>
                                    <p className="text-sm text-ink-soft">{[s.email, s.phone, s.page].filter(Boolean).join(' · ')} · {formatDateTime(s.at)}</p>
                                    <dl className="mt-1 grid gap-0.5 text-sm sm:grid-cols-2">
                                        {Object.entries(s.answers).map(([k, v]) => (
                                            <div key={k} className="flex gap-2"><dt className="text-ink-soft">{k.replace(/_/g, ' ')}:</dt><dd>{String(v)}</dd></div>
                                        ))}
                                    </dl>
                                    {(s.becameBuyer || s.becameTenant) && (
                                        <p className="mt-1 text-xs text-line-deep">Added as a {s.becameBuyer ? 'buyer' : 'tenant'} record.</p>
                                    )}
                                </div>
                                {s.status === 'new' && (
                                    <span className="flex gap-2">
                                        <Button size="sm" onClick={() => router.patch(`/website/enquiries/${s.id}`, { status: 'actioned' }, { preserveScroll: true })}>Dealt with</Button>
                                        <Button size="sm" variant="ghost" className="text-brick" onClick={() => router.patch(`/website/enquiries/${s.id}`, { status: 'spam' }, { preserveScroll: true })}>Spam</Button>
                                    </span>
                                )}
                            </div>
                        </li>
                    ))}
                    {submissions.data.length === 0 && <li className="text-ink-soft">Nothing here.</li>}
                </ul>
                <Pager prev={submissions.prev_page_url} next={submissions.next_page_url} page={submissions.current_page} last={submissions.last_page} />
            </div>
        </>
    );
}

Submissions.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
