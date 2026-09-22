import { Head, Link, useForm } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import type { FormEvent, ReactNode } from 'react';
import { PageHeader, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Option = { key: string | number; label: string };

interface ProjectData {
    id: string;
    code: string;
    name: string;
    development_type: string | null;
    status: string;
    region_id: number | null;
    province: string | null;
    town: string | null;
    estimated_value: string | null;
    planned_start_date: string | null;
    planned_completion_date: string | null;
    description: string | null;
    project_manager: string | null;
}

interface Props {
    project: ProjectData | null;
    suggestedCode: string | null;
    developmentTypes: Option[];
    provinces: Option[];
    regions: Option[];
    people: Option[];
    statuses: string[];
}

const STATUS_LABELS: Record<string, string> = { active: 'Active', on_hold: 'On hold', completed: 'Completed', cancelled: 'Cancelled' };

export default function ProjectForm({ project, suggestedCode, developmentTypes, provinces, regions, people, statuses }: Props) {
    const editing = project !== null;
    const form = useForm({
        code: project?.code ?? '',
        name: project?.name ?? '',
        development_type: project?.development_type ?? '',
        status: project?.status ?? 'active',
        region_id: project?.region_id ? String(project.region_id) : '',
        province: project?.province ?? '',
        town: project?.town ?? '',
        estimated_value: project?.estimated_value ?? '',
        planned_start_date: project?.planned_start_date ?? '',
        planned_completion_date: project?.planned_completion_date ?? '',
        description: project?.description ?? '',
        project_manager: project?.project_manager ?? '',
    });

    type Key = keyof typeof form.data;
    const text = (key: Key) => ({
        name: key,
        value: form.data[key],
        onChange: (e: { target: { value: string } }) => form.setData(key, e.target.value),
        error: form.errors[key],
    });

    function submit(e: FormEvent) {
        e.preventDefault();
        form.transform((d) => ({ ...d, region_id: d.region_id || null, project_manager: d.project_manager || null }));
        if (editing) form.put(`/projects/${project.id}`);
        else form.post('/projects');
    }

    return (
        <>
            <Head title={editing ? `Edit ${project.name}` : 'New project'} />
            <form onSubmit={submit} className="mx-auto grid max-w-3xl gap-6" noValidate>
                <PageHeader title={editing ? `Edit ${project.name}` : 'New project'} />

                <div className="grid gap-5 sm:grid-cols-[1fr_180px]">
                    <Field label="Project name" {...text('name')} placeholder="e.g. Ballito Heights" />
                    <Field label="Project code" {...text('code')} placeholder={suggestedCode ?? ''} hint={!editing ? 'Leave empty to use the next number.' : undefined} />
                </div>

                <div className="grid gap-5 sm:grid-cols-2">
                    <SelectField label="Development type" name="development_type" value={form.data.development_type} onChange={(v) => form.setData('development_type', v)} options={developmentTypes} error={form.errors.development_type} placeholder="Choose a type" />
                    <SelectField label="Project manager" name="project_manager" value={form.data.project_manager} onChange={(v) => form.setData('project_manager', v)} options={people} error={form.errors.project_manager} placeholder="Not assigned yet" />
                </div>

                <div className="grid gap-5 sm:grid-cols-3">
                    <SelectField label="Province" name="province" value={form.data.province} onChange={(v) => form.setData('province', v)} options={provinces} error={form.errors.province} placeholder="Choose a province" />
                    <Field label="Town or suburb" {...text('town')} />
                    <SelectField label="Region" name="region_id" value={form.data.region_id} onChange={(v) => form.setData('region_id', v)} options={regions} error={form.errors.region_id} placeholder="None" />
                </div>

                <div className="grid gap-5 sm:grid-cols-3">
                    <Field label="Estimated value (R, excl. VAT)" type="number" min={0} {...text('estimated_value')} />
                    <Field label="Planned start" type="date" {...text('planned_start_date')} />
                    <Field label="Planned completion" type="date" {...text('planned_completion_date')} />
                </div>

                {editing && (
                    <SelectField label="Status" name="status" value={form.data.status} onChange={(v) => form.setData('status', v)} options={statuses.map((s) => ({ key: s, label: STATUS_LABELS[s] ?? s }))} error={form.errors.status} />
                )}

                <div className="grid gap-1.5">
                    <label htmlFor="description" className="text-sm font-medium">
                        Description
                    </label>
                    <textarea id="description" rows={4} value={form.data.description} onChange={(e) => form.setData('description', e.target.value)} className="rounded-[var(--radius-control)] border border-concrete bg-surface p-3 text-base focus:border-line focus:outline-none" />
                </div>

                <div className="flex gap-3">
                    <Button type="submit" size="lg" disabled={form.processing}>
                        {editing ? 'Save changes' : 'Create project'}
                    </Button>
                    <Button variant="secondary" size="lg" asChild>
                        <Link href={editing ? `/projects/${project.id}` : '/projects'}>Cancel</Link>
                    </Button>
                </div>
            </form>
        </>
    );
}

ProjectForm.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
