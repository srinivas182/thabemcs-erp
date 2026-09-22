import { Head, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { formatDateTime, PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Provider { key: 'sage_za' | 'simplepay'; label: string; enabled: boolean; settings: Record<string, unknown>; credentials: Record<string, boolean>; lastSynced: string | null; lastError: string | null }
interface Props {
    providers: Provider[];
    syncs: { provider: string; type: string; id: number; status: string; externalId: string | null; error: string | null; attempts: number; at: string }[];
    suppliers: { id: string; name: string; ref: string | null }[];
    employees: { id: string; name: string; ref: string | null }[];
}

const toLines = (m: unknown) => Object.entries((m ?? {}) as Record<string, string>).map(([k, v]) => `${k}=${v}`).join('\n');
const fromLines = (t: string) => Object.fromEntries(t.split('\n').map((l) => l.split('=').map((x) => x.trim())).filter((p) => p.length === 2 && p[0] && p[1]) as [string, string][]);

const SECRETS = {
    sage_za: [{ key: 'api_key', label: 'Sage API key' }, { key: 'username', label: 'Sage login email' }, { key: 'password', label: 'Sage password' }],
    simplepay: [{ key: 'api_key', label: 'SimplePay API key' }],
};

export default function Integrations({ providers, syncs, suppliers, employees }: Props) {
    return (
        <>
            <Head title="Integrations" />
            <div className="mx-auto grid max-w-5xl gap-8">
                <PageHeader title="Accounting and payroll" description="Send approved supplier invoices to Sage, and overtime, allowances and leave to SimplePay. Nothing is sent twice." />
                {providers.map((p) => <ProviderCard key={p.key} provider={p} />)}
                <div className="grid gap-8 lg:grid-cols-2">
                    <RefList title="Sage supplier IDs" hint="The supplier's ID in Sage. Invoices from suppliers without one are not sent." rows={suppliers} url={(id) => `/suppliers/${id}/accounting-ref`} field="accounting_ref" />
                    <RefList title="SimplePay employee IDs" hint="The employee's ID in SimplePay (from the employee list or URL)." rows={employees} url={(id) => `/workforce/${id}/payroll-ref`} field="payroll_ref" />
                </div>
                {syncs.length > 0 && (
                    <section className="grid gap-2">
                        <h2 className="text-lg font-bold">Recent sends</h2>
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {syncs.map((s, i) => (
                                <li key={i} className="flex flex-wrap justify-between gap-2 px-3 py-2">
                                    <span>{s.provider === 'sage_za' ? 'Sage' : 'SimplePay'}: {s.type} #{s.id}{s.externalId && <span className="text-ink-soft"> (their ID {s.externalId})</span>}{s.error && <span className="block text-brick">{s.error}</span>}</span>
                                    <span className={cn(s.status === 'failed' ? 'font-semibold text-brick' : 'text-line-deep')}>{s.status === 'sent' ? 'Sent' : `Failed (${s.attempts} tries)`}, {formatDateTime(s.at)}</span>
                                </li>
                            ))}
                        </ul>
                    </section>
                )}
            </div>
        </>
    );
}

function ProviderCard({ provider: p }: { provider: Provider }) {
    const s = p.settings as Record<string, string | Record<string, string>>;
    const form = useForm({
        enabled: p.enabled,
        credentials: Object.fromEntries(SECRETS[p.key].map((f) => [f.key, ''])) as Record<string, string>,
        company_id: String(s.company_id ?? ''), client_id: String(s.client_id ?? ''), default_account_id: String(s.default_account_id ?? ''),
        tax_type_vat: String(s.tax_type_vat ?? ''), tax_type_none: String(s.tax_type_none ?? ''),
        accounts: toLines(s.accounts), items: toLines(s.items), leave_types: toLines(s.leave_types),
    });
    const now = new Date();
    const [from, setFrom] = useState(new Date(now.getFullYear(), now.getMonth(), 1).toLocaleDateString('en-CA'));
    const [to, setTo] = useState(new Date(now.getFullYear(), now.getMonth() + 1, 0).toLocaleDateString('en-CA'));

    function save(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({
            enabled: d.enabled, credentials: d.credentials,
            settings: p.key === 'sage_za'
                ? { company_id: d.company_id, default_account_id: d.default_account_id, tax_type_vat: d.tax_type_vat, tax_type_none: d.tax_type_none, accounts: fromLines(d.accounts) }
                : { client_id: d.client_id, items: fromLines(d.items), leave_types: fromLines(d.leave_types) },
        }));
        form.put(`/settings/integrations/${p.key}`, { preserveScroll: true, onSuccess: () => form.setData('credentials', Object.fromEntries(SECRETS[p.key].map((f) => [f.key, '']))) });
    }
    const area = 'min-h-24 w-full rounded-[var(--radius-control)] border border-concrete bg-surface p-2 font-mono text-xs';

    return (
        <section className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold">{p.label}</h2>
                    <p className="text-sm text-ink-soft">{p.enabled ? 'Switched on' : 'Switched off'}{p.lastSynced && `, last sent ${formatDateTime(p.lastSynced)}`}</p>
                    {p.lastError && <p className="text-sm text-brick">{p.lastError}</p>}
                </div>
                <div className="flex flex-wrap gap-2">
                    <Button variant="secondary" size="sm" onClick={() => router.post(`/settings/integrations/${p.key}/test`, {}, { preserveScroll: true })}>Test connection</Button>
                    {p.key === 'sage_za' && <Button size="sm" disabled={!p.enabled} onClick={() => router.post('/settings/integrations/sage_za/sync', {}, { preserveScroll: true })}>Send approved invoices</Button>}
                </div>
            </div>
            {p.key === 'simplepay' && (
                <div className="flex flex-wrap items-end gap-3">
                    <Field label="Pay period from" name="from" type="date" value={from} onChange={(e) => setFrom(e.target.value)} />
                    <Field label="to (payslip date)" name="to" type="date" value={to} onChange={(e) => setTo(e.target.value)} />
                    <Button size="sm" disabled={!p.enabled} onClick={() => window.confirm('Send overtime, allowances and approved leave for this period to SimplePay?') && router.post('/settings/integrations/simplepay/sync', { from, to }, { preserveScroll: true })}>Send to SimplePay</Button>
                </div>
            )}
            <form onSubmit={save} className="grid gap-4 border-t border-concrete pt-4">
                <label className="flex items-center gap-2 text-sm"><input type="checkbox" className="size-4 accent-line" checked={form.data.enabled} onChange={(e) => form.setData('enabled', e.target.checked)} /> Switched on</label>
                <div className="grid gap-3 sm:grid-cols-3">
                    {SECRETS[p.key].map((f) => (
                        <Field key={f.key} label={f.label} name={f.key} type="password" autoComplete="off" value={form.data.credentials[f.key] ?? ''} onChange={(e) => form.setData('credentials', { ...form.data.credentials, [f.key]: e.target.value })} placeholder={p.credentials[f.key] ? 'Saved (leave blank to keep)' : ''} />
                    ))}
                </div>
                {p.key === 'sage_za' ? (
                    <>
                        <div className="grid gap-3 sm:grid-cols-4">
                            <Field label="Sage company ID" name="company_id" value={form.data.company_id} onChange={(e) => form.setData('company_id', e.target.value)} />
                            <Field label="Default expense account ID" name="default_account_id" value={form.data.default_account_id} onChange={(e) => form.setData('default_account_id', e.target.value)} />
                            <Field label="Tax type ID: standard VAT" name="tax_type_vat" value={form.data.tax_type_vat} onChange={(e) => form.setData('tax_type_vat', e.target.value)} />
                            <Field label="Tax type ID: no VAT" name="tax_type_none" value={form.data.tax_type_none} onChange={(e) => form.setData('tax_type_none', e.target.value)} />
                        </div>
                        <label className="grid gap-1.5 text-sm font-medium">Cost code to Sage account (one per line: 05.03=1234)<textarea className={area} value={form.data.accounts} onChange={(e) => form.setData('accounts', e.target.value)} /></label>
                    </>
                ) : (
                    <>
                        <Field label="SimplePay client ID" name="client_id" value={form.data.client_id} onChange={(e) => form.setData('client_id', e.target.value)} />
                        <label className="grid gap-1.5 text-sm font-medium">Payslip items (one per line). Keys: overtime, overtime_double, allowance_travel, allowance_site, allowance_tool, allowance_meal, allowance_housing, allowance_cellphone, allowance_other. Values are SimplePay inputs, e.g. allowance_travel=calc.76523.amount
                            <textarea className={area} value={form.data.items} onChange={(e) => form.setData('items', e.target.value)} />
                        </label>
                        <label className="grid gap-1.5 text-sm font-medium">Leave types (one per line, SimplePay leave type IDs): annual=5, sick=6, family=7<textarea className={area} value={form.data.leave_types} onChange={(e) => form.setData('leave_types', e.target.value)} /></label>
                    </>
                )}
                <div><Button type="submit" disabled={form.processing}>Save settings</Button></div>
                <p className="text-xs text-ink-soft">Keys and passwords are stored encrypted and are never shown again.</p>
            </form>
        </section>
    );
}

function RefList({ title, hint, rows, url, field }: { title: string; hint: string; rows: { id: string; name: string; ref: string | null }[]; url: (id: string) => string; field: string }) {
    const [values, setValues] = useState<Record<string, string>>(Object.fromEntries(rows.map((r) => [r.id, r.ref ?? ''])));
    return (
        <section className="grid content-start gap-2">
            <h2 className="font-bold">{title} <span className="font-normal text-ink-soft">({rows.filter((r) => !r.ref).length} missing)</span></h2>
            <p className="text-xs text-ink-soft">{hint}</p>
            <ul className="max-h-80 divide-y divide-concrete overflow-y-auto rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                {rows.map((r) => (
                    <li key={r.id} className="flex items-center justify-between gap-2 px-3 py-1.5">
                        <span className={cn(!r.ref && 'text-brick')}>{r.name}</span>
                        <span className="flex gap-1">
                            <input value={values[r.id] ?? ''} onChange={(e) => setValues({ ...values, [r.id]: e.target.value })} className="h-8 w-24 rounded-[var(--radius-control)] border border-concrete px-2" aria-label={`ID for ${r.name}`} />
                            {(values[r.id] ?? '') !== (r.ref ?? '') && <Button size="sm" onClick={() => router.patch(url(r.id), { [field]: values[r.id] || null }, { preserveScroll: true })}>Save</Button>}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

Integrations.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
