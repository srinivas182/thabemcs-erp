import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { ChevronDown, Download, Folder, Lock, Upload } from 'lucide-react';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDateTime, selectClass, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };

interface Version {
    id: number;
    version: number;
    revision: string | null;
    name: string;
    size: number;
    by: string | null;
    at: string;
    notes: string | null;
}

interface Doc {
    id: string;
    title: string;
    folder: string;
    category: string;
    categoryLabel: string;
    drawingNumber: string | null;
    drawingDiscipline: string | null;
    restricted: boolean;
    versions: Version[];
}

interface Props {
    project: { id: string; name: string; code: string } | null;
    projects: Option[];
    folders: Record<string, number>;
    documents: Doc[];
    filters: { folder: string; category: string | null; q: string };
    categories: Option[];
    roles: Option[];
    storage: { used: number; limit: number | null } | null;
    accept: string;
    canUpload: boolean;
}

const size = (bytes: number) => (bytes > 1_048_576 ? `${(bytes / 1_048_576).toFixed(1)} MB` : `${Math.max(1, Math.round(bytes / 1024))} KB`);

export default function Documents(props: Props) {
    const { project, projects, folders, documents, filters, categories, storage, canUpload } = props;
    const [uploading, setUploading] = useState(false);
    const [q, setQ] = useState(filters.q);
    const base = { project: project?.id };
    const go = (params: Record<string, string | undefined | null>) => router.get('/documents', { ...base, ...filters, ...params }, { preserveState: true, preserveScroll: true });
    const drawings = filters.category === 'drawing';

    return (
        <>
            <Head title="Documents" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-end justify-between gap-4">
                    <div>
                        {project && <p className="text-sm text-ink-soft"><Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>}
                        <h1 className="text-2xl font-bold tracking-tight sm:text-3xl">{project ? `Documents: ${project.name}` : 'Company documents'}</h1>
                        {storage && <p className="text-sm text-ink-soft">Storage used: {storage.used} MB{storage.limit ? ` of ${storage.limit} MB` : ''}</p>}
                    </div>
                    <div className="flex items-center gap-2">
                        <select aria-label="Project" className={selectClass + ' h-10 w-56 text-sm'} value={project?.id ?? ''} onChange={(e) => router.get('/documents', { project: e.target.value || undefined })}>
                            <option value="">Company documents (no project)</option>
                            {projects.map((p) => (<option key={p.key} value={p.key}>{p.label}</option>))}
                        </select>
                        {canUpload && <Button onClick={() => setUploading(!uploading)}><Upload className="size-4" /> Upload</Button>}
                    </div>
                </header>

                {uploading && <UploadForm {...props} onDone={() => setUploading(false)} />}

                <div className="grid gap-6 lg:grid-cols-[220px_1fr]">
                    <nav aria-label="Folders" className="grid content-start gap-0.5">
                        <button onClick={() => go({ folder: '' })} className={cn('flex items-center gap-2 rounded-[var(--radius-control)] px-3 py-1.5 text-left text-sm', !filters.folder ? 'bg-line-wash font-semibold text-line-deep' : 'hover:bg-concrete-soft')}>
                            <Folder className="size-4" /> All folders
                        </button>
                        {Object.entries(folders).map(([name, count]) => (
                            <button key={name} onClick={() => go({ folder: name })} className={cn('flex items-center justify-between gap-2 rounded-[var(--radius-control)] px-3 py-1.5 text-left text-sm', filters.folder === name ? 'bg-line-wash font-semibold text-line-deep' : 'hover:bg-concrete-soft')}>
                                <span className="flex min-w-0 items-center gap-2"><Folder className="size-4 shrink-0" /><span className="truncate">{name}</span></span>
                                <span className="text-xs text-ink-soft tabular-nums">{count}</span>
                            </button>
                        ))}
                    </nav>

                    <div className="grid content-start gap-4">
                        <div className="flex flex-wrap items-center gap-3">
                            <form onSubmit={(e) => { e.preventDefault(); go({ q }); }} className="flex-1">
                                <input value={q} onChange={(e) => setQ(e.target.value)} placeholder="Search titles and drawing numbers" className="h-10 w-full rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm focus:border-line focus:outline-none" />
                            </form>
                            <select aria-label="Category" className={selectClass + ' h-10 w-52 text-sm'} value={filters.category ?? ''} onChange={(e) => go({ category: e.target.value || null })}>
                                <option value="">All types</option>
                                {categories.map((c) => (<option key={c.key} value={c.key}>{c.label}</option>))}
                            </select>
                            <button onClick={() => go({ category: drawings ? null : 'drawing' })} className={cn('h-10 rounded-[var(--radius-control)] border px-3 text-sm', drawings ? 'border-line bg-line text-white' : 'border-concrete bg-surface')}>
                                Drawing register
                            </button>
                        </div>

                        {documents.length === 0 ? (
                            <p className="text-ink-soft">No documents here yet.</p>
                        ) : (
                            <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface">
                                {documents.map((d) => (<DocumentRow key={d.id} doc={d} canUpload={canUpload} accept={props.accept} />))}
                            </ul>
                        )}
                    </div>
                </div>
            </div>
        </>
    );
}

function DocumentRow({ doc, canUpload, accept }: { doc: Doc; canUpload: boolean; accept: string }) {
    const [open, setOpen] = useState(false);
    const latest = doc.versions[0];
    const form = useForm<{ file: File | null; revision: string; notes: string }>({ file: null, revision: '', notes: '' });

    function upload(e: FormEvent) {
        e.preventDefault();
        form.post(`/documents/${doc.id}/versions`, { preserveScroll: true, forceFormData: true, onSuccess: () => form.reset() });
    }

    return (
        <li className="p-3">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <button onClick={() => setOpen(!open)} className="flex min-w-0 flex-1 items-start gap-2 text-left" aria-expanded={open}>
                    <ChevronDown className={cn('mt-1 size-4 shrink-0 transition-transform', !open && '-rotate-90')} />
                    <span className="min-w-0">
                        <span className="flex items-center gap-1.5 font-medium">
                            {doc.drawingNumber && <span className="tabular-nums">{doc.drawingNumber}</span>}
                            {doc.title}
                            {doc.restricted && <Lock className="size-3.5 text-ink-soft" aria-label="Restricted" />}
                        </span>
                        <span className="block text-sm text-ink-soft">
                            {[doc.folder, doc.categoryLabel, doc.drawingDiscipline, latest && `v${latest.version}${latest.revision ? ` (Rev ${latest.revision})` : ''}`, latest && formatDateTime(latest.at)].filter(Boolean).join(', ')}
                        </span>
                    </span>
                </button>
                {latest && (
                    <a href={`/documents/${doc.id}/versions/${latest.id}/download`} className="inline-flex items-center gap-1 text-sm font-medium text-line hover:underline">
                        <Download className="size-4" /> Download
                    </a>
                )}
            </div>

            {open && (
                <div className="mt-3 grid gap-3 pl-6">
                    <ul className="grid gap-1 text-sm">
                        {doc.versions.map((v) => (
                            <li key={v.id} className="flex flex-wrap justify-between gap-2">
                                <span>
                                    <span className="font-medium">v{v.version}{v.revision && ` Rev ${v.revision}`}</span>, {v.name}, {size(v.size)}, {v.by}, {formatDateTime(v.at)}
                                    {v.notes && <span className="text-ink-soft">: {v.notes}</span>}
                                </span>
                                <a href={`/documents/${doc.id}/versions/${v.id}/download`} className="text-line hover:underline">Download</a>
                            </li>
                        ))}
                    </ul>
                    {canUpload && (
                        <form onSubmit={upload} className="flex flex-wrap items-end gap-3">
                            <input type="file" accept={accept} onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm" aria-label="New version file" />
                            {doc.category === 'drawing' && <input value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)} placeholder="Revision" className="h-9 w-24 rounded-[var(--radius-control)] border border-concrete px-2 text-sm" aria-label="Revision" />}
                            <input value={form.data.notes} onChange={(e) => form.setData('notes', e.target.value)} placeholder="What changed?" className="h-9 flex-1 rounded-[var(--radius-control)] border border-concrete px-2 text-sm" aria-label="Notes" />
                            <Button size="sm" type="submit" disabled={!form.data.file || form.processing}>{form.progress ? `${form.progress.percentage}%` : 'Upload new version'}</Button>
                            {form.errors.file && <p className="w-full text-sm text-brick">{form.errors.file}</p>}
                        </form>
                    )}
                </div>
            )}
        </li>
    );
}

function UploadForm({ project, filters, categories, roles, accept, onDone }: Props & { onDone: () => void }) {
    const form = useForm<{ file: File | null; project: string | null; folder: string; title: string; category: string; drawing_number: string; drawing_discipline: string; revision: string; restricted_to_roles: string[] }>({
        file: null, project: project?.id ?? null, folder: filters.folder || 'General', title: '', category: filters.category ?? 'other',
        drawing_number: '', drawing_discipline: '', revision: '', restricted_to_roles: [],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.post('/documents', { preserveScroll: true, forceFormData: true, onSuccess: onDone });
    }

    return (
        <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
            <div className="grid gap-1.5">
                <label htmlFor="upload-file" className="text-sm font-medium">File (up to 50 MB)</label>
                <input id="upload-file" type="file" accept={accept} onChange={(e) => form.setData('file', e.target.files?.[0] ?? null)} className="text-sm" />
                {form.errors.file && <p className="text-sm text-brick">{form.errors.file}</p>}
            </div>
            <div className="grid gap-4 sm:grid-cols-3">
                <Field label="Title" name="title" value={form.data.title} onChange={(e) => form.setData('title', e.target.value)} placeholder="Defaults to the file name" />
                <Field label="Folder" name="folder" value={form.data.folder} onChange={(e) => form.setData('folder', e.target.value)} error={form.errors.folder} hint="Use / for sub-folders, e.g. Drawings/Architectural" />
                <SelectField label="Type" name="category" value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} />
            </div>
            {form.data.category === 'drawing' && (
                <div className="grid gap-4 sm:grid-cols-3">
                    <Field label="Drawing number" name="drawing_number" value={form.data.drawing_number} onChange={(e) => form.setData('drawing_number', e.target.value)} error={form.errors.drawing_number} />
                    <Field label="Discipline" name="drawing_discipline" value={form.data.drawing_discipline} onChange={(e) => form.setData('drawing_discipline', e.target.value)} placeholder="e.g. Architectural, Structural" />
                    <Field label="Revision" name="revision" value={form.data.revision} onChange={(e) => form.setData('revision', e.target.value)} placeholder="e.g. A" />
                </div>
            )}
            <fieldset>
                <legend className="text-sm font-medium">Restrict to roles <span className="font-normal text-ink-soft">(optional; Company Admins and Directors always have access)</span></legend>
                <div className="mt-1.5 flex flex-wrap gap-2">
                    {roles.filter((r) => !['company-admin', 'director'].includes(r.key)).map((r) => (
                        <label key={r.key} className="flex items-center gap-1.5 rounded-full border border-concrete px-2.5 py-1 text-sm">
                            <input type="checkbox" className="accent-line" checked={form.data.restricted_to_roles.includes(r.key)}
                                onChange={(e) => form.setData('restricted_to_roles', e.target.checked ? [...form.data.restricted_to_roles, r.key] : form.data.restricted_to_roles.filter((x) => x !== r.key))} />
                            {r.label}
                        </label>
                    ))}
                </div>
            </fieldset>
            <div className="flex gap-3">
                <Button type="submit" disabled={!form.data.file || form.processing}>{form.progress ? `Uploading ${form.progress.percentage}%` : 'Upload'}</Button>
                <Button type="button" variant="secondary" onClick={onDone}>Cancel</Button>
            </div>
        </form>
    );
}

Documents.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
