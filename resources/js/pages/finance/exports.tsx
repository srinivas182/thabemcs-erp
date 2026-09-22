import { Head } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

const EXPORTS = [
    { type: 'supplier-invoices', title: 'Supplier invoices', text: 'Approved and paid invoices for import into Sage (supplier, dates, reference, cost code, amounts and VAT).' },
    { type: 'payments', title: 'Supplier payments', text: 'Payments made, for matching against the bank statement in the accounting system.' },
    { type: 'payroll-inputs', title: 'Payroll inputs', text: 'Overtime hours (with 1.5x or 2x) and approved leave days per employee, for SimplePay or your payroll system.' },
];

export default function Exports() {
    const now = new Date();
    const first = new Date(now.getFullYear(), now.getMonth() - 1, 1).toLocaleDateString('en-CA');
    const last = new Date(now.getFullYear(), now.getMonth(), 0).toLocaleDateString('en-CA');
    const [from, setFrom] = useState(first);
    const [to, setTo] = useState(last);
    return (
        <>
            <Head title="Exports" />
            <div className="mx-auto grid max-w-4xl gap-6">
                <PageHeader title="Accounting and payroll exports" description="Download CSV files for your accounting and payroll systems. Ask your accountant to confirm the column mapping the first time." />
                <div className="flex flex-wrap gap-4">
                    <Field label="From" name="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    <Field label="To" name="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                </div>
                <ul className="grid gap-3">
                    {EXPORTS.map((x) => (
                        <li key={x.type} className="flex flex-wrap items-center justify-between gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <div><p className="font-semibold">{x.title}</p><p className="text-sm text-ink-soft">{x.text}</p></div>
                            <Button variant="secondary" asChild><a href={`/exports/${x.type}?from=${from}&to=${to}`}>Download CSV</a></Button>
                        </li>
                    ))}
                </ul>
            </div>
        </>
    );
}

Exports.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
