import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { PageHeader, Pager, SelectField, tableClass } from '@/components/data';
import AppLayout from '@/layouts/app-layout';
import type { Paginated } from '@/types';

interface Row { id: string; number: string; name: string; jobTitle: string | null; type: string; status: string; site: string | null }
const TYPES = [{ key: 'permanent', label: 'Permanent' }, { key: 'fixed_term', label: 'Fixed term' }, { key: 'temporary', label: 'Temporary' }];

export default function Workforce({ employees, q, pendingLeave }: { employees: Paginated<Row>; q: string; pendingLeave: number }) {
    const [adding, setAdding] = useState(false);
    const [search, setSearch] = useState(q);
    const form = useForm({ employee_number: '', first_name: '', last_name: '', id_number: '', job_title: '', employment_type: 'permanent', start_date: '', end_date: '', phone: '', days_per_week: '5' });
    const bind = (k: 'employee_number' | 'first_name' | 'last_name' | 'id_number' | 'job_title' | 'start_date' | 'end_date' | 'phone') => ({ name: k, value: form.data[k], onChange: (e: { target: { value: string } }) => form.setData(k, e.target.value), error: form.errors[k] });
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => Object.fromEntries(Object.entries(d).map(([k, v]) => [k, v === '' ? null : v])));
        form.post('/workforce');
    }
    return (
        <>
            <Head title="Workforce" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader title="Workforce" description={`Employees, where they are working, leave and overtime.${pendingLeave ? ` ${pendingLeave} leave ${pendingLeave === 1 ? 'request is' : 'requests are'} waiting for approval.` : ''}`} action={<Button onClick={() => setAdding(!adding)}>Add employee</Button>} />
                {adding && (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-4">
                            <Field label="Employee number" {...bind('employee_number')} />
                            <Field label="First name" {...bind('first_name')} />
                            <Field label="Surname" {...bind('last_name')} />
                            <Field label="ID number" {...bind('id_number')} hint="Stored encrypted" />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-5">
                            <Field label="Job title" {...bind('job_title')} placeholder="e.g. General worker" />
                            <SelectField label="Employment" name="employment_type" value={form.data.employment_type} onChange={(v) => form.setData('employment_type', v)} options={TYPES} />
                            <Field label="Start date" type="date" {...bind('start_date')} />
                            {form.data.employment_type !== 'permanent' && <Field label="End date" type="date" {...bind('end_date')} />}
                            <SelectField label="Days per week" name="days_per_week" value={form.data.days_per_week} onChange={(v) => form.setData('days_per_week', v)} options={['5', '6'].map((d) => ({ key: d, label: `${d} days` }))} />
                        </div>
                        <div className="flex gap-3"><Button type="submit" disabled={form.processing}>Add employee</Button><Button type="button" variant="secondary" onClick={() => setAdding(false)}>Cancel</Button></div>
                    </form>
                )}
                <form onSubmit={(e) => { e.preventDefault(); router.get('/workforce', search ? { q: search } : {}); }}>
                    <input value={search} onChange={(e) => setSearch(e.target.value)} placeholder="Search by name or employee number" className="h-10 w-full max-w-md rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-sm" />
                </form>
                {employees.data.length === 0 ? <p className="text-ink-soft">No employees yet.</p> : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead><tr><th>Employee</th><th>Job</th><th>Employment</th><th>Current site</th></tr></thead>
                            <tbody>{employees.data.map((e) => (
                                <tr key={e.id} className={cn('hover:bg-plaster', e.status !== 'active' && 'opacity-60')}>
                                    <td><Link href={`/workforce/${e.id}`} className="font-semibold hover:underline">{e.name}</Link><p className="text-ink-soft">{e.number}</p></td>
                                    <td>{e.jobTitle}</td><td>{TYPES.find((t) => t.key === e.type)?.label}</td><td>{e.site ?? <span className="text-ink-soft">Not allocated</span>}</td>
                                </tr>
                            ))}</tbody>
                        </table>
                    </div>
                )}
                <Pager prev={employees.prev_page_url} next={employees.next_page_url} page={employees.current_page} last={employees.last_page} />
            </div>
        </>
    );
}

Workforce.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
