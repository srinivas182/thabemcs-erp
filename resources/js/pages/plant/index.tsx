import { Head, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDate, formatRand, PageHeader, SelectField } from '@/components/data';
import { LookupField } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

interface Item {
    id: string; asset: string; description: string; category: string; makeModel: string | null; ownership: string; supplier: string | null; rate: number | null;
    project: string | null; status: string; nextService: string | null; serviceDue: boolean; events: { type: string; on: string; project: string | null; notes: string | null }[];
}
const STATUS: Record<string, string> = { available: 'In the yard', on_site: 'On site', in_service: 'Being serviced', broken: 'Broken down', off_hired: 'Off-hired' };
const EVENTS = [{ key: 'moved', label: 'Moved to a site' }, { key: 'serviced', label: 'Serviced' }, { key: 'breakdown', label: 'Broke down' }, { key: 'repaired', label: 'Repaired' }, { key: 'off_hired', label: 'Off-hired / returned' }];
const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function Plant({ items, canManage }: { items: Item[]; canManage: boolean }) {
    const [adding, setAdding] = useState(false);
    const form = useForm({ asset_number: '', description: '', category: 'Earthmoving', make_model: '', serial_number: '', ownership: 'owned', supplier: '', hire_rate_per_day: '', service_interval_days: '', next_service_on: '' });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.post('/plant', { preserveScroll: true, onSuccess: () => { form.reset(); setAdding(false); } });
    }
    return (
        <>
            <Head title="Plant and equipment" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader title="Plant and equipment" description="Owned and hired plant: where it is, its condition and when it is due for a service." action={canManage && <Button onClick={() => setAdding(!adding)}>Add plant</Button>} />
                {adding && (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <Field label="Asset number" name="asset_number" value={form.data.asset_number} onChange={(e) => form.setData('asset_number', e.target.value)} error={form.errors.asset_number} />
                            <Field label="Description" name="description" value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} error={form.errors.description} placeholder="e.g. TLB 4x4" />
                            <SelectField label="Category" name="category" value={form.data.category} onChange={(v) => form.setData('category', v)} options={['Earthmoving', 'Lifting', 'Concrete', 'Compaction', 'Vehicles', 'Power and pumps', 'Scaffolding', 'Small tools'].map((c) => ({ key: c, label: c }))} />
                            <Field label="Make and model" name="make_model" value={form.data.make_model} onChange={(e) => form.setData('make_model', e.target.value)} />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-5">
                            <SelectField label="Owned or hired" name="ownership" value={form.data.ownership} onChange={(v) => form.setData('ownership', v)} options={[{ key: 'owned', label: 'Owned' }, { key: 'hired', label: 'Hired' }]} />
                            {form.data.ownership === 'hired' && <LookupField label="Hire company" name="supplier" value={form.data.supplier} onChange={(v) => form.setData('supplier', v)} type="suppliers" params={{ types: 'plant_hire' }} placeholder="Choose" error={form.errors.supplier} />}
                            {form.data.ownership === 'hired' && <Field label="Rate per day (R)" name="hire_rate_per_day" type="number" value={form.data.hire_rate_per_day} onChange={(e) => form.setData('hire_rate_per_day', e.target.value)} />}
                            <Field label="Service every (days)" name="service_interval_days" type="number" value={form.data.service_interval_days} onChange={(e) => form.setData('service_interval_days', e.target.value)} />
                            <Field label="Next service" name="next_service_on" type="date" value={form.data.next_service_on} onChange={(e) => form.setData('next_service_on', e.target.value)} />
                        </div>
                        <div className="flex gap-3"><Button type="submit" disabled={form.processing}>Add to register</Button><Button type="button" variant="secondary" onClick={() => setAdding(false)}>Cancel</Button></div>
                    </form>
                )}
                {items.length === 0 ? <p className="text-ink-soft">No plant recorded.</p> : <ul className="grid gap-3">{items.map((i) => <PlantRow key={i.id} item={i} canManage={canManage} />)}</ul>}
            </div>
        </>
    );
}

function PlantRow({ item: i, canManage }: { item: Item; canManage: boolean }) {
    const [open, setOpen] = useState(false);
    const ev = useForm({ type: 'moved', project: '', happened_on: today(), cost: '', notes: '' });
    return (
        <li className={cn('rounded-[var(--radius-panel)] border bg-surface p-4', i.status === 'broken' ? 'border-brick' : i.serviceDue ? 'border-hivis' : 'border-concrete')}>
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <p className="font-semibold">{i.asset} {i.description} <span className="font-normal text-ink-soft">{i.makeModel}</span></p>
                    <p className="text-sm text-ink-soft">{[i.category, i.ownership === 'hired' ? `hired from ${i.supplier}${i.rate ? ` at ${formatRand(i.rate)}/day` : ''}` : 'owned', i.nextService && `next service ${formatDate(i.nextService)}`].filter(Boolean).join(', ')}</p>
                    {i.serviceDue && <p className="text-sm font-semibold text-ink">Service due</p>}
                </div>
                <div className="text-right text-sm">
                    <p className={cn('font-semibold', i.status === 'broken' && 'text-brick')}>{STATUS[i.status]}</p>
                    {i.project && <p className="text-ink-soft">{i.project}</p>}
                    {canManage && <button className="mt-1 text-line hover:underline" onClick={() => setOpen(!open)}>Record movement or service</button>}
                </div>
            </div>
            {open && (
                <form onSubmit={(e) => { e.preventDefault(); ev.transform((d) => ({ ...d, project: d.project || null, cost: d.cost || null })); ev.post(`/plant/${i.id}/events`, { preserveScroll: true, onSuccess: () => setOpen(false) }); }} className="mt-3 grid items-end gap-3 border-t border-concrete pt-3 sm:grid-cols-[1fr_1fr_150px_120px_1fr_auto]">
                    <SelectField label="What happened" name="type" value={ev.data.type} onChange={(v) => ev.setData('type', v)} options={EVENTS} />
                    {ev.data.type === 'moved' ? <LookupField label="To project" name="project" value={ev.data.project} onChange={(v) => ev.setData('project', v)} type="projects" params={{ status: 'active' }} placeholder="Choose" error={ev.errors.project} /> : <span />}
                    <Field label="Date" name="happened_on" type="date" value={ev.data.happened_on} onChange={(e) => ev.setData('happened_on', e.target.value)} />
                    <Field label="Cost (R)" name="cost" type="number" value={ev.data.cost} onChange={(e) => ev.setData('cost', e.target.value)} />
                    <Field label="Notes" name="notes" value={ev.data.notes} onChange={(e) => ev.setData('notes', e.target.value)} />
                    <Button type="submit" disabled={ev.processing}>Save</Button>
                </form>
            )}
            {i.events.length > 0 && <p className="mt-2 text-xs text-ink-soft">Recent: {i.events.map((x) => `${EVENTS.find((t) => t.key === x.type)?.label.toLowerCase()} ${formatDate(x.on)}${x.project ? ` (${x.project})` : ''}`).join('; ')}</p>}
        </li>
    );
}

Plant.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
