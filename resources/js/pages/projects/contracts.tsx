import { Head, Link, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type FormEvent, type ReactNode, useState } from 'react';
import { STATUS_LABEL } from '@/components/approval-trail';
import { formatDate, formatRand, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string; label: string };
interface Certificate { id: string; reference: string; date: string; gross: number; held: number; released: number; previous: number; due: number; vat: number; status: string }
interface Contract {
    id: string; reference: string; form: string; contractor: string; sum: number; retention: number; cap: number | null; release: number;
    practical: string | null; final: string | null; paymentDays: number | null; defectsMonths: number | null; position: { certified: number; retentionHeld: number; retentionReleased: number; remaining: number };
    blockers: string[]; certificates: Certificate[];
}
interface FormDefault { label: string; retention_percent: number; retention_cap_percent: number | null; release_at_practical_percent: number; payment_terms_days: number; defects_period_months: number; notes: string }
interface Props { project: { id: string; name: string; code: string }; contracts: Contract[]; contractors: Option[]; budgetLines: Option[]; forms: Option[]; formDefaults: Record<string, FormDefault>; canManage: boolean }

const today = () => new Intl.DateTimeFormat('en-CA', { timeZone: 'Africa/Johannesburg' }).format(new Date());

export default function Contracts({ project, contracts, contractors, budgetLines, forms, formDefaults, canManage }: Props) {
    const [adding, setAdding] = useState(false);
    const form = useForm({ supplier: '', reference: '', contract_form: 'jbcc_pba', contract_sum: '', retention_percent: '10', retention_cap_percent: '5', release_at_practical_percent: '50', payment_terms_days: '7', defects_period_months: '3', budget_line_id: '' });
    function chooseForm(key: string) {
        const d = formDefaults[key];
        form.setData((f) => ({
            ...f, contract_form: key,
            ...(d ? { retention_percent: String(d.retention_percent), retention_cap_percent: d.retention_cap_percent === null ? '' : String(d.retention_cap_percent), release_at_practical_percent: String(d.release_at_practical_percent), payment_terms_days: String(d.payment_terms_days), defects_period_months: String(d.defects_period_months) } : {}),
        }));
    }
    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, retention_cap_percent: d.retention_cap_percent || null, budget_line_id: d.budget_line_id || null, payment_terms_days: d.payment_terms_days || null, defects_period_months: d.defects_period_months || null }));
        form.post(`/projects/${project.id}/contracts`, { preserveScroll: true, onSuccess: () => { form.reset(); setAdding(false); } });
    }

    return (
        <>
            <Head title={`Contracts: ${project.name}`} />
            <div className="mx-auto grid max-w-6xl gap-6">
                <header className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <p className="text-sm text-ink-soft"><Link href="/projects" className="hover:underline">Projects</Link> / <Link href={`/projects/${project.id}`} className="hover:underline">{project.code}</Link></p>
                        <h1 className="mt-1 text-3xl font-bold tracking-tight [font-stretch:92%]">Contracts and payment certificates</h1>
                        <p className="text-ink-soft">{project.name}. Each month the QS values the work done; the certificate deducts retention and what was certified before.</p>
                    </div>
                    {canManage && <Button onClick={() => setAdding(!adding)}>Add contract</Button>}
                </header>

                {adding && (
                    <form onSubmit={submit} className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5" noValidate>
                        <div className="grid gap-4 sm:grid-cols-3">
                            <SelectField label="Contractor" name="supplier" value={form.data.supplier} onChange={(v) => form.setData('supplier', v)} options={contractors} placeholder="Choose" error={form.errors.supplier} />
                            <Field label="Contract reference" name="reference" value={form.data.reference} onChange={(e) => form.setData('reference', e.target.value)} error={form.errors.reference} placeholder="e.g. BH-MAIN-01" />
                            <SelectField label="Form of contract" name="contract_form" value={form.data.contract_form} onChange={chooseForm} options={forms} />
                        </div>
                        {formDefaults[form.data.contract_form] && <p className="-mt-2 text-sm text-ink-soft">{formDefaults[form.data.contract_form]?.notes} Typical values are filled in below; use the figures in the signed contract data.</p>}
                        <div className="grid gap-4 sm:grid-cols-5">
                            <Field label="Contract sum excl. VAT (R)" name="contract_sum" type="number" value={form.data.contract_sum} onChange={(e) => form.setData('contract_sum', e.target.value)} error={form.errors.contract_sum} />
                            <Field label="Retention %" name="retention_percent" type="number" value={form.data.retention_percent} onChange={(e) => form.setData('retention_percent', e.target.value)} />
                            <Field label="Retention limit, % of sum" name="retention_cap_percent" type="number" value={form.data.retention_cap_percent} onChange={(e) => form.setData('retention_cap_percent', e.target.value)} hint="Blank = no limit" />
                            <Field label="Released at practical completion %" name="release_at_practical_percent" type="number" value={form.data.release_at_practical_percent} onChange={(e) => form.setData('release_at_practical_percent', e.target.value)} />
                            <SelectField label="Cost code" name="budget_line_id" value={form.data.budget_line_id} onChange={(v) => form.setData('budget_line_id', v)} options={budgetLines} placeholder="Choose" />
                        </div>
                        <div className="grid gap-4 sm:grid-cols-5">
                            <Field label="Payment due (days after certificate)" name="payment_terms_days" type="number" value={form.data.payment_terms_days} onChange={(e) => form.setData('payment_terms_days', e.target.value)} />
                            <Field label="Defects period (months)" name="defects_period_months" type="number" value={form.data.defects_period_months} onChange={(e) => form.setData('defects_period_months', e.target.value)} />
                        </div>
                        <div className="flex gap-3"><Button type="submit" disabled={form.processing}>Save contract</Button><Button type="button" variant="secondary" onClick={() => setAdding(false)}>Cancel</Button></div>
                    </form>
                )}

                {contracts.length === 0 ? <p className="text-ink-soft">No contracts recorded.</p> : contracts.map((c) => <ContractCard key={c.id} contract={c} canManage={canManage} />)}
            </div>
        </>
    );
}

function ContractCard({ contract: c, canManage }: { contract: Contract; canManage: boolean }) {
    const cert = useForm({ gross_value: '', valuation_date: today(), notes: '' });
    const done = useForm({ practical_completion_on: c.practical ?? '', final_completion_on: c.final ?? '' });
    const pct = c.sum ? Math.min(100, (c.position.certified / c.sum) * 100) : 0;
    const open = c.certificates.some((x) => ['draft', 'pending_approval'].includes(x.status));

    return (
        <section className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div>
                    <h2 className="text-lg font-bold">{c.contractor} <span className="font-normal text-ink-soft">{c.reference}</span></h2>
                    <p className="text-sm text-ink-soft">{c.form}; retention {c.retention}%{c.cap !== null && ` up to ${c.cap}% of the sum`}, {c.release}% released at practical completion{c.paymentDays !== null && `; payment ${c.paymentDays} days after certificate`}{c.defectsMonths !== null && `; ${c.defectsMonths}-month defects period`}</p>
                    {c.blockers.length > 0 && <p className="text-sm font-semibold text-brick first-letter:uppercase">Compliance: {c.blockers.join('; ')}</p>}
                </div>
                <div className="text-right">
                    <p className="text-xl font-bold tabular-nums">{formatRand(c.sum)}</p>
                    <p className="text-xs text-ink-soft">contract sum excl. VAT</p>
                </div>
            </div>
            <div>
                <div className="h-2 overflow-hidden rounded-full bg-concrete-soft"><div className="h-full bg-line" style={{ width: `${pct}%` }} /></div>
                <p className="mt-1 text-sm text-ink-soft">Certified to date {formatRand(c.position.certified)} ({pct.toFixed(0)}%). Retention held {formatRand(c.position.retentionHeld - c.position.retentionReleased)}.</p>
            </div>

            {c.certificates.length > 0 && (
                <div className="overflow-x-auto">
                    <table className="w-full min-w-[760px] text-sm [&_td]:px-2 [&_td]:py-1.5 [&_th]:px-2 [&_th]:py-1.5 [&_th]:text-left [&_th]:font-semibold [&_th]:text-ink-soft [&_tbody_tr]:border-t [&_tbody_tr]:border-concrete">
                        <thead><tr><th>Certificate</th><th>Valuation</th><th className="text-right">Value to date</th><th className="text-right">Retention</th><th className="text-right">Previous</th><th className="text-right">Due excl. VAT</th><th>Status</th></tr></thead>
                        <tbody>
                            {c.certificates.map((x) => (
                                <tr key={x.id}>
                                    <td className="font-medium">{x.reference}</td><td>{formatDate(x.date)}</td>
                                    <td className="text-right tabular-nums">{formatRand(x.gross)}</td>
                                    <td className="text-right tabular-nums">−{formatRand(x.held - x.released)}{x.released > 0 && <span className="block text-xs text-line-deep">{formatRand(x.released)} released</span>}</td>
                                    <td className="text-right tabular-nums">−{formatRand(x.previous)}</td>
                                    <td className="text-right font-semibold tabular-nums">{formatRand(x.due)}<span className="block text-xs font-normal text-ink-soft">+ VAT {formatRand(x.vat)}</span></td>
                                    <td>
                                        {x.status === 'draft' && canManage ? <Button size="sm" onClick={() => router.post(`/payment-certificates/${x.id}/submit`, {}, { preserveScroll: true })}>Submit for approval</Button>
                                            : <span className={cn(x.status === 'certified' ? 'text-line-deep' : x.status === 'rejected' ? 'text-brick' : 'text-ink-soft')}>{x.status === 'certified' ? 'Certified' : STATUS_LABEL[x.status] ?? x.status}</span>}
                                    </td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}

            {canManage && (
                <div className="grid gap-4 border-t border-concrete pt-4 lg:grid-cols-2">
                    {!open && (
                        <form onSubmit={(e) => { e.preventDefault(); cert.post(`/contracts/${c.id}/certificates`, { preserveScroll: true, onSuccess: () => cert.reset('gross_value', 'notes') }); }} className="grid content-start gap-3">
                            <p className="font-semibold">Prepare the next certificate</p>
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Value of work to date, excl. VAT (R)" name="gross_value" type="number" value={cert.data.gross_value} onChange={(e) => cert.setData('gross_value', e.target.value)} error={cert.errors.gross_value} hint="Include materials on site and approved variations" />
                                <Field label="Valuation date" name="valuation_date" type="date" value={cert.data.valuation_date} onChange={(e) => cert.setData('valuation_date', e.target.value)} />
                            </div>
                            <div><Button type="submit" disabled={cert.processing}>Calculate certificate</Button></div>
                        </form>
                    )}
                    <form onSubmit={(e) => { e.preventDefault(); done.transform((d) => ({ practical_completion_on: d.practical_completion_on || null, final_completion_on: d.final_completion_on || null })); done.patch(`/contracts/${c.id}/completion`, { preserveScroll: true }); }} className="grid content-start gap-3">
                        <p className="font-semibold">Completion (releases retention)</p>
                        <div className="grid gap-3 sm:grid-cols-2">
                            <Field label="Practical completion" name="practical_completion_on" type="date" value={done.data.practical_completion_on} onChange={(e) => done.setData('practical_completion_on', e.target.value)} />
                            <Field label="Final completion" name="final_completion_on" type="date" value={done.data.final_completion_on} onChange={(e) => done.setData('final_completion_on', e.target.value)} error={done.errors.final_completion_on} />
                        </div>
                        <div><Button type="submit" variant="secondary" disabled={done.processing}>Save dates</Button></div>
                    </form>
                </div>
            )}
        </section>
    );
}

Contracts.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
