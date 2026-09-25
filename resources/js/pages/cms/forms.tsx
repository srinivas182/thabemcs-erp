import { Head, Link, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { PageHeader, SelectField } from '@/components/data';
import { LookupMulti } from '@/components/lookup-field';
import AppLayout from '@/layouts/app-layout';

type FormField = { name: string; label: string; type: string; required: boolean };
interface Row { id: string; name: string; slug: string; fields: FormField[]; creates: string; active: boolean; recipients: string[]; successMessage: string | null; submissions: number }

export default function Forms({ forms, fieldTypes }: { forms: Row[]; fieldTypes: string[] }) {
    const [building, setBuilding] = useState(false);
    const form = useForm<{ name: string; fields: FormField[]; creates: string; recipients: string[]; success_message: string }>({
        name: '', creates: 'buyer', recipients: [], success_message: 'Thank you. We will be in touch shortly.',
        fields: [
            { name: 'name', label: 'Your name', type: 'text', required: true },
            { name: 'email', label: 'Email', type: 'email', required: true },
            { name: 'phone', label: 'Phone', type: 'phone', required: false },
            { name: 'message', label: 'How can we help?', type: 'textarea', required: true },
        ],
    });
    const set = (i: number, patch: Partial<FormField>) => form.setData('fields', form.data.fields.map((f, j) => (j === i ? { ...f, ...patch } : f)));

    return (
        <>
            <Head title="Website forms" />
            <div className="mx-auto grid max-w-4xl gap-5">
                <PageHeader title="Forms" description="Forms people fill in on the website. An enquiry form creates a buyer or tenant record, so nothing is lost in an inbox."
                    action={<div className="flex gap-2"><Button variant="ghost" asChild><Link href="/website/enquiries">Enquiries</Link></Button><Button onClick={() => setBuilding(!building)}>New form</Button></div>} />

                {building && (
                    <form onSubmit={(e) => { e.preventDefault(); form.post('/website/forms', { preserveScroll: true, onSuccess: () => { form.reset(); setBuilding(false); } }); }}
                        className="grid gap-4 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
                        <div className="grid gap-4 sm:grid-cols-2">
                            <Field label="Form name" name="name" value={form.data.name} onChange={(e) => form.setData('name', e.target.value)} error={form.errors.name} placeholder="Enquire about a home" />
                            <SelectField label="An entry becomes" name="creates" value={form.data.creates} onChange={(v) => form.setData('creates', v)}
                                options={[{ key: 'buyer', label: 'A buyer enquiry' }, { key: 'tenant', label: 'A tenant application' }, { key: 'none', label: 'Just a message' }]} />
                        </div>

                        <fieldset className="grid gap-2">
                            <legend className="text-sm font-medium">Questions</legend>
                            {form.data.fields.map((f, i) => (
                                <div key={i} className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_140px_auto_auto]">
                                    <Field label="" aria-label="Label" name={`l${i}`} value={f.label} onChange={(e) => set(i, { label: e.target.value })} />
                                    <Field label="" aria-label="Field name" name={`n${i}`} value={f.name} onChange={(e) => set(i, { name: e.target.value })} placeholder="lowercase_name" />
                                    <SelectField label="" name={`t${i}`} value={f.type} onChange={(v) => set(i, { type: v })} options={fieldTypes.map((t) => ({ key: t, label: t }))} />
                                    <label className="mb-2 flex items-center gap-1.5 text-sm"><input type="checkbox" className="accent-line" checked={f.required} onChange={(e) => set(i, { required: e.target.checked })} /> Needed</label>
                                    <button type="button" className="mb-2 text-sm text-brick" onClick={() => form.setData('fields', form.data.fields.filter((_, j) => j !== i))}>Remove</button>
                                </div>
                            ))}
                            <Button type="button" variant="ghost" size="sm" className="justify-self-start"
                                onClick={() => form.setData('fields', [...form.data.fields, { name: '', label: '', type: 'text', required: false }])}>Add a question</Button>
                        </fieldset>

                        <LookupMulti label="Tell these people when someone sends it" name="recipients" type="people" value={form.data.recipients} onChange={(v) => form.setData('recipients', v)} />
                        <Field label="Message shown after sending" name="success_message" value={form.data.success_message} onChange={(e) => form.setData('success_message', e.target.value)} />
                        <p className="text-xs text-ink-soft">Every form asks for POPIA consent before it can be sent, and that consent is recorded with the entry.</p>
                        <div><Button type="submit" disabled={form.processing}>Create form</Button></div>
                    </form>
                )}

                <ul className="grid gap-2">
                    {forms.map((f) => (
                        <li key={f.id} className="rounded-[var(--radius-panel)] border border-concrete bg-surface p-3">
                            <div className="flex flex-wrap items-center justify-between gap-2">
                                <span>
                                    <span className="font-semibold">{f.name}</span>
                                    <span className="block text-sm text-ink-soft">
                                        {f.fields.length} questions · becomes {f.creates === 'none' ? 'a message' : `a ${f.creates}`} · {f.submissions} received
                                    </span>
                                </span>
                                <span className={cn('text-sm', f.active ? 'text-line-deep' : 'text-ink-soft')}>{f.active ? 'In use' : 'Off'}</span>
                            </div>
                        </li>
                    ))}
                    {forms.length === 0 && <li className="text-ink-soft">No forms yet.</li>}
                </ul>
            </div>
        </>
    );
}

Forms.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
