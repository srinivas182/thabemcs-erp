import { Head, Link, router } from '@inertiajs/react';
import { Button } from '@thabekhulu/ui';
import type { ReactNode } from 'react';
import { PageHeader, StatusBadge, tableClass, Usage } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface CompanyRow {
    id: string;
    name: string;
    legalName: string | null;
    status: 'active' | 'suspended';
    modulesEnabled: number;
    usage: { projects: { used: number; limit: number | null }; users: { used: number; limit: number | null } };
}

export default function CompaniesIndex({ companies, moduleCount }: { companies: CompanyRow[]; moduleCount: number }) {
    function setStatus(company: CompanyRow, status: 'active' | 'suspended') {
        const message =
            status === 'suspended'
                ? `Suspend ${company.name}? Its users will be signed out and cannot sign in until you reactivate it.`
                : `Reactivate ${company.name}?`;
        if (window.confirm(message)) {
            router.patch(`/platform/companies/${company.id}/status`, { status }, { preserveScroll: true });
        }
    }

    return (
        <>
            <Head title="Companies" />
            <div className="mx-auto grid max-w-6xl gap-6">
                <PageHeader
                    title="Companies"
                    description="Each company is a legal entity in the group. Set how many projects and users it may have, and which modules it can use."
                    action={
                        <Button asChild>
                            <Link href="/platform/companies/create">Add company</Link>
                        </Button>
                    }
                />

                {companies.length === 0 ? (
                    <div className="rounded-[var(--radius-panel)] border border-dashed border-concrete p-10 text-center">
                        <p className="font-semibold">No companies yet</p>
                        <p className="mt-1 text-ink-soft">Add the first company and invite its administrator.</p>
                    </div>
                ) : (
                    <div className="overflow-x-auto rounded-[var(--radius-panel)] border border-concrete bg-surface">
                        <table className={tableClass}>
                            <thead>
                                <tr>
                                    <th>Company</th>
                                    <th>Status</th>
                                    <th>Projects</th>
                                    <th>Users</th>
                                    <th>Modules</th>
                                    <th className="text-right">Actions</th>
                                </tr>
                            </thead>
                            <tbody>
                                {companies.map((c) => (
                                    <tr key={c.id}>
                                        <td>
                                            <p className="font-semibold">{c.name}</p>
                                            {c.legalName && <p className="text-ink-soft">{c.legalName}</p>}
                                        </td>
                                        <td>
                                            <StatusBadge active={c.status === 'active'} />
                                        </td>
                                        <td>
                                            <Usage {...c.usage.projects} />
                                        </td>
                                        <td>
                                            <Usage {...c.usage.users} />
                                        </td>
                                        <td className="tabular-nums">
                                            {c.modulesEnabled} of {moduleCount}
                                        </td>
                                        <td>
                                            <div className="flex justify-end gap-1">
                                                <Button variant="ghost" size="sm" asChild>
                                                    <Link href={`/platform/companies/${c.id}/edit`}>Edit</Link>
                                                </Button>
                                                {c.status === 'active' && (
                                                    <Button variant="ghost" size="sm" onClick={() => router.post(`/platform/acting-company/${c.id}`)}>
                                                        Work in company
                                                    </Button>
                                                )}
                                                <Button
                                                    variant="ghost"
                                                    size="sm"
                                                    className={c.status === 'active' ? 'text-brick' : ''}
                                                    onClick={() => setStatus(c, c.status === 'active' ? 'suspended' : 'active')}
                                                >
                                                    {c.status === 'active' ? 'Suspend' : 'Reactivate'}
                                                </Button>
                                            </div>
                                        </td>
                                    </tr>
                                ))}
                            </tbody>
                        </table>
                    </div>
                )}
            </div>
        </>
    );
}

CompaniesIndex.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
