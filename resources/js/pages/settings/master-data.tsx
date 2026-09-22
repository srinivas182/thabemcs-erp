import { Head, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import type { FormEvent, ReactNode } from 'react';
import { PageHeader, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Props {
    costCodes: { id: number; code: string; description: string; category: string | null; active: boolean }[];
    units: { id: number; code: string; name: string }[];
    categories: { key: string; label: string }[];
}

export default function MasterData({ costCodes, units, categories }: Props) {
    const code = useForm({ code: '', description: '', category: 'construction' });
    const unit = useForm({ code: '', name: '' });
    function addCode(e: FormEvent) { e.preventDefault(); code.post('/settings/master-data/cost-codes', { preserveScroll: true, onSuccess: () => code.reset('code', 'description') }); }
    function addUnit(e: FormEvent) { e.preventDefault(); unit.post('/settings/master-data/units', { preserveScroll: true, onSuccess: () => unit.reset() }); }
    return (
        <>
            <Head title="Master data" />
            <div className="mx-auto grid max-w-6xl gap-8">
                <PageHeader title="Master data" description="The company's cost code library and units. New project budgets and requisitions pick from these lists." />
                <div className="grid gap-8 lg:grid-cols-[1.6fr_1fr]">
                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-bold">Cost codes</h2>
                        <form onSubmit={addCode} className="grid items-end gap-2 sm:grid-cols-[100px_1fr_170px_auto]">
                            <Field label="Code" name="code" value={code.data.code} onChange={(e) => code.setData('code', e.target.value)} error={code.errors.code} />
                            <Field label="Description" name="description" value={code.data.description} onChange={(e) => code.setData('description', e.target.value)} error={code.errors.description} />
                            <SelectField label="Heading" name="category" value={code.data.category} onChange={(v) => code.setData('category', v)} options={categories} />
                            <Button type="submit" disabled={code.processing}>Add</Button>
                        </form>
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {costCodes.map((c) => (
                                <li key={c.id} className={cn('flex items-center justify-between gap-2 px-3 py-2', !c.active && 'text-ink-soft')}>
                                    <span><span className="tabular-nums text-ink-soft">{c.code}</span> {c.description}</span>
                                    <button className="text-xs hover:underline" onClick={() => router.patch(`/settings/master-data/cost-codes/${c.id}`, {}, { preserveScroll: true })}>{c.active ? 'Stop using' : 'Use again'}</button>
                                </li>
                            ))}
                        </ul>
                    </section>
                    <section className="grid content-start gap-3">
                        <h2 className="text-lg font-bold">Units</h2>
                        <form onSubmit={addUnit} className="grid items-end gap-2 sm:grid-cols-[90px_1fr_auto]">
                            <Field label="Code" name="code" value={unit.data.code} onChange={(e) => unit.setData('code', e.target.value)} error={unit.errors.code} />
                            <Field label="Name" name="name" value={unit.data.name} onChange={(e) => unit.setData('name', e.target.value)} error={unit.errors.name} />
                            <Button type="submit" disabled={unit.processing}>Add</Button>
                        </form>
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {units.map((u) => (
                                <li key={u.id} className="flex items-center justify-between px-3 py-2">
                                    <span><span className="font-medium">{u.code}</span> <span className="text-ink-soft">{u.name}</span></span>
                                    <button className="text-xs text-brick hover:underline" onClick={() => router.delete(`/settings/master-data/units/${u.id}`, { preserveScroll: true })}>Remove</button>
                                </li>
                            ))}
                        </ul>
                    </section>
                </div>
            </div>
        </>
    );
}

MasterData.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
