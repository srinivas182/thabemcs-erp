import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent, ReactNode } from 'react';
import { PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface ModuleOption {
    key: string;
    label: string;
}

interface CompanyData {
    id: string;
    name: string;
    legal_name: string | null;
    registration_number: string | null;
    vat_number: string | null;
    max_projects: number | null;
    max_users: number | null;
    max_storage_mb: number | null;
    modules: string[];
}

const toInput = (v: number | null | undefined) => (v === null || v === undefined ? '' : String(v));

export default function CompanyForm({ company, modules }: { company: CompanyData | null; modules: ModuleOption[] }) {
    const editing = company !== null;
    const form = useForm({
        name: company?.name ?? '',
        legal_name: company?.legal_name ?? '',
        registration_number: company?.registration_number ?? '',
        vat_number: company?.vat_number ?? '',
        max_projects: toInput(company?.max_projects),
        max_users: toInput(company?.max_users),
        max_storage_mb: toInput(company?.max_storage_mb),
        modules: company?.modules ?? modules.map((m) => m.key),
        admin_name: '',
        admin_email: '',
    });

    function toggleModule(key: string) {
        form.setData('modules', form.data.modules.includes(key) ? form.data.modules.filter((m) => m !== key) : [...form.data.modules, key]);
    }

    function submit(event: FormEvent) {
        event.preventDefault();
        if (editing) form.put(`/platform/companies/${company.id}`);
        else form.post('/platform/companies');
    }

    const bind = (key: 'name' | 'legal_name' | 'registration_number' | 'vat_number' | 'max_projects' | 'max_users' | 'max_storage_mb' | 'admin_name' | 'admin_email') => ({
        name: key,
        value: form.data[key],
        onChange: (e: { target: { value: string } }) => form.setData(key, e.target.value),
        error: form.errors[key],
    });

    return (
        <>
            <Head title={editing ? `Edit ${company.name}` : 'Add company'} />
            <form onSubmit={submit} className="mx-auto grid max-w-3xl gap-8" noValidate>
                <PageHeader title={editing ? `Edit ${company.name}` : 'Add company'} />

                <Section title="Company details">
                    <Field label="Trading name" {...bind('name')} />
                    <Field label="Registered name" {...bind('legal_name')} placeholder="e.g. Steve Maqueens (Pty) Ltd" />
                    <div className="grid gap-5 sm:grid-cols-2">
                        <Field label="CIPC registration number" {...bind('registration_number')} placeholder="2021/123456/07" />
                        <Field label="VAT number" {...bind('vat_number')} inputMode="numeric" placeholder="4123456789" />
                    </div>
                </Section>

                <Section title="Limits" description="Leave a limit empty for unlimited.">
                    <div className="grid gap-5 sm:grid-cols-3">
                        <Field label="Projects" type="number" min={1} {...bind('max_projects')} />
                        <Field label="Users" type="number" min={1} {...bind('max_users')} />
                        <Field label="Storage (MB)" type="number" min={100} {...bind('max_storage_mb')} />
                    </div>
                </Section>

                <Section title="Modules" description="People in this company only see the modules switched on here.">
                    <div className="grid gap-2 sm:grid-cols-2">
                        {modules.map((m) => (
                            <label key={m.key} className="flex items-center gap-2.5 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 py-2.5">
                                <input type="checkbox" className="size-4 accent-line" checked={form.data.modules.includes(m.key)} onChange={() => toggleModule(m.key)} />
                                <span className="text-sm">{m.label}</span>
                            </label>
                        ))}
                    </div>
                    {form.errors.modules && <p className="text-sm text-brick">{form.errors.modules}</p>}
                </Section>

                {!editing && (
                    <Section title="Company administrator" description="We'll email this person a link to set their password. They can then invite everyone else.">
                        <div className="grid gap-5 sm:grid-cols-2">
                            <Field label="Full name" {...bind('admin_name')} />
                            <Field label="Email address" type="email" {...bind('admin_email')} />
                        </div>
                    </Section>
                )}

                <div className="flex gap-3">
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create company and send invitation'}
                    </Button>
                    <Button variant="secondary" size="lg" asChild>
                        <Link href="/platform/companies">Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

function Section({ title, description, children }: { title: string; description?: string; children: ReactNode }) {
    return (
        <fieldset className="grid gap-5 border-t-2 border-ink pt-4">
            <div>
                <legend className="font-bold">{title}</legend>
                {description && <p className="mt-0.5 text-sm text-ink-soft">{description}</p>}
            </div>
            {children}
        </fieldset>
    );
}

CompanyForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
