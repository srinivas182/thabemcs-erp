export interface AuthUser {
    id: string;
    name: string;
    email: string;
    jobTitle: string | null;
    isSuperAdmin: boolean;
    roles: string[];
}

export interface CompanyModule {
    key: string;
    label: string;
}

export interface CurrentCompany {
    id: string;
    name: string;
    modules: CompanyModule[];
}

/** Props shared with every Inertia page (see HandleInertiaRequests). */
export interface SharedProps {
    [key: string]: unknown;
    app: { name: string; timezone: string };
    auth: { user: AuthUser | null };
    company: CurrentCompany | null;
    flash: { success: string | null; error: string | null };
}
