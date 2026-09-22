import { Head, Link } from '@inertiajs/react';
import { cn } from '@thabekhulu/ui';
import { Fragment, type ReactNode, useState } from 'react';
import { formatDateTime } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Question = { id: string; label: string; type: string };
interface Submission { id: string; form: string; kind: string; at: string; by: string; passed: boolean | null; fields: Question[]; answers: Record<string, unknown> }

function answer(v: unknown, type: string): ReactNode {
    if (v === undefined || v === null || v === '') return <span className="text-ink-soft">Not answered</span>;
    if (type === 'photo' && typeof v === 'object' && v !== null && 'photo' in v) return <a href={`/site-photos/${(v as { photo: number }).photo}`} target="_blank" rel="noreferrer" className="text-line hover:underline">View photo</a>;
    if (type === 'passfail') return <span className={cn('font-semibold', v === 'fail' ? 'text-brick' : v === 'pass' ? 'text-line-deep' : 'text-ink-soft')}>{v === 'na' ? 'N/A' : String(v).toUpperCase()}</span>;
    return String(v);
}

export default function ProjectForms({ project, submissions }: { project: { id: string; name: string; code: string }; submissions: Submission[] }) {
    const [open, setOpen] = useState<string | null>(null);
    return (
        <>
            <Head title={`Forms: ${project.name}`} />
            <div className="mx-auto grid max-w-5xl gap-6">
                <header>
                    <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                    <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Completed forms</h1>
                    <p className="text-ink-soft">{project.name}. Checklists filled in on the site app.</p>
                </header>
                {submissions.length === 0 ? <p className="text-ink-soft">No forms completed yet.</p> : (
                    <ul className="grid gap-2">
                        {submissions.map((s) => (
                            <li key={s.id} className={cn('rounded-[var(--radius-panel)] border bg-surface', s.passed === false ? 'border-brick' : 'border-concrete')}>
                                <button className="flex w-full flex-wrap items-center justify-between gap-2 p-3 text-left" onClick={() => setOpen(open === s.id ? null : s.id)} aria-expanded={open === s.id}>
                                    <span><span className="font-semibold">{s.form}</span><span className="block text-sm text-ink-soft">{formatDateTime(s.at)}, {s.by}</span></span>
                                    <span className={cn('text-sm font-semibold', s.passed === false ? 'text-brick' : s.passed ? 'text-line-deep' : 'text-ink-soft')}>{s.passed === false ? 'Failed' : s.passed ? 'Passed' : 'Completed'}</span>
                                </button>
                                {open === s.id && (
                                    <dl className="grid gap-1 border-t border-concrete p-3 text-sm sm:grid-cols-[1fr_auto]">
                                        {s.fields.map((q) => (<Fragment key={q.id}><dt className="text-ink-soft">{q.label}</dt><dd className="sm:text-right">{answer(s.answers[q.id], q.type)}</dd></Fragment>))}
                                    </dl>
                                )}
                            </li>
                        ))}
                    </ul>
                )}
            </div>
        </>
    );
}

ProjectForms.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
