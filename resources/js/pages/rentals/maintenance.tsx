import { Head, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDate, formatRand, PageHeader, Pager, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Row {
    id: string; reference: string; unit: string; project: string; tenant: string | null; category: string; description: string;
    priority: string; status: string; supplier: string | null; cost: number | null; recover: boolean;
    reported: string; completed: string | null; byTenant: boolean; resolution: string | null;
}
interface Props {
    requests: { data: Row[]; prev_page_url: string | null; next_page_url: string | null; current_page: number; last_page: number };
    filters: { status: string };
    categories: { key: string; label: string }[];
    canManage: boolean;
}

const STATUSES = [
    { key: 'open', label: 'Open' }, { key: 'new', label: 'New' }, { key: 'assigned', label: 'Assigned' },
    { key: 'in_progress', label: 'In progress' }, { key: 'completed', label: 'Completed' }, { key: 'all', label: 'All' },
];

export default function Maintenance({ requests, filters, categories, canManage }: Props) {
    const [adding, setAdding] = useState(false);
    return (
        <>
            <Head title="Maintenance" />
            <div className="mx-auto grid max-w-5xl gap-5">
                <PageHeader title="Maintenance" description="Repairs at rented units, raised here or by tenants through their own link. Urgent requests come first."
                    action={canManage ? <Button onClick={() => setAdding(!adding)}>Log a request</Button> : undefined} />

                <div className="w-52"><SelectField label="Status" name="status" value={filters.status} onChange={(v) => router.get('/rentals/maintenance', { status: v })} options={STATUSES} /></div>

                {adding && canManage && <LogRequest categories={categories} onDone={() => setAdding(false)} />}

                <ul className="grid gap-2">
                    {requests.data.map((r) => (
                        <li key={r.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-3', r.priority === 'urgent' && r.status !== 'completed' ? 'border-brick' : 'border-concrete')}>
                            <div className="flex flex-wrap items-start justify-between gap-2">
                                <div>
                                    <p className="font-semibold">{r.reference}: {r.project} {r.unit}
                                        {r.priority === 'urgent' && <span className="ml-2 rounded-full bg-brick px-2 py-0.5 text-xs text-white">Urgent</span>}
                                        {r.byTenant && <span className="ml-2 text-xs text-ink-soft">reported by the tenant</span>}
                                    </p>
                                    <p className="text-sm">{categories.find((c) => c.key === r.category)?.label}: {r.description}</p>
                                    <p className="text-xs text-ink-soft">Reported {formatDate(r.reported)}{r.tenant ? ` · ${r.tenant}` : ''}{r.supplier ? ` · ${r.supplier}` : ''}{r.cost ? ` · ${formatRand(r.cost)}${r.recover ? ', recovered from the tenant' : ''}` : ''}</p>
                                    {r.resolution && <p className="mt-1 text-sm text-ink-soft">{r.resolution}</p>}
                                </div>
                                <span className="text-sm capitalize">{r.status.replace('_', ' ')}{r.completed && <span className="block text-xs text-ink-soft">{formatDate(r.completed)}</span>}</span>
                            </div>
                            {canManage && r.status !== 'completed' && r.status !== 'cancelled' && <Update request={r} />}
                        </li>
                    ))}
                    {requests.data.length === 0 && <li className="text-ink-soft">Nothing outstanding.</li>}
                </ul>
                <Pager prev={requests.prev_page_url} next={requests.next_page_url} page={requests.current_page} last={requests.last_page} />
            </div>
        </>
    );
}

function Update({ request: r }: { request: Row }) {
    const form = useForm({ status: r.status, supplier: '', cost: r.cost ? String(r.cost) : '', recover_from_tenant: r.recover, resolution: '' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.transform((d) => ({ ...d, supplier: d.supplier || null, cost: d.cost || null, resolution: d.resolution || null })); form.patch(`/rentals/maintenance/${r.id}`, { preserveScroll: true }); }}
            className="mt-3 grid items-end gap-2 border-t border-concrete pt-3 sm:grid-cols-5">
            <SelectField label="Status" name={`s${r.id}`} value={form.data.status} onChange={(v) => form.setData('status', v)} options={STATUSES.filter((s) => !['open', 'all'].includes(s.key))} />
            <LookupField label="Contractor" name={`sup${r.id}`} type="suppliers" value={form.data.supplier} onChange={(v) => form.setData('supplier', v)} placeholder={r.supplier ?? 'Assign'} />
            <Field label="Cost (R)" name={`c${r.id}`} type="number" value={form.data.cost} onChange={(e) => form.setData('cost', e.target.value)} />
            <Field label="What was done" name={`r${r.id}`} value={form.data.resolution} onChange={(e) => form.setData('resolution', e.target.value)} error={form.errors.resolution} />
            <Button type="submit" size="sm" disabled={form.processing}>Save</Button>
        </form>
    );
}

function LogRequest({ categories, onDone }: { categories: { key: string; label: string }[]; onDone: () => void }) {
    const form = useForm({ unit: '', category: 'plumbing', description: '', priority: 'normal' });
    return (
        <form onSubmit={(e) => { e.preventDefault(); form.post('/rentals/maintenance', { preserveScroll: true, onSuccess: () => { form.reset(); onDone(); } }); }}
            className="grid items-end gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5 sm:grid-cols-5">
            <LookupField label="Unit" name="unit" type="units" value={form.data.unit} onChange={(v) => form.setData('unit', v)} error={form.errors.unit} placeholder="Search units" />
            <SelectField label="Category" name="category" value={form.data.category} onChange={(v) => form.setData('category', v)} options={categories} />
            <Field label="What is wrong" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} error={form.errors.description} />
            <SelectField label="Priority" name="priority" value={form.data.priority} onChange={(v) => form.setData('priority', v)} options={[{ key: 'urgent', label: 'Urgent' }, { key: 'normal', label: 'Normal' }, { key: 'low', label: 'Low' }]} />
            <Button type="submit" disabled={form.processing}>Log request</Button>
        </form>
    );
}

Maintenance.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
