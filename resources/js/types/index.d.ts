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
    brand: {
        name: string; shortName: string; owner: string; tagline: string; logo: string | null;
        supportEmail: string | null; supportPhone: string | null; supportHours: string | null; privacyUrl: string | null; poweredBy: string | null;
    };
    auth: { user: AuthUser | null };
    can: { manageCompanies: boolean; viewPortfolio: boolean; manageMasterData: boolean; managePopia: boolean; viewSales: boolean; manageSales: boolean; viewRentals: boolean; manageRentals: boolean; manageForms: boolean; manageIntegrations: boolean; manageUsers: boolean; viewAuditLog: boolean };
    notifications: { unread: number };
    company: CurrentCompany | null;
    flash: { success: string | null; error: string | null };
}

/** Laravel paginator as serialised to JSON. */
export interface Paginated<T> {
    data: T[];
    current_page: number;
    last_page: number;
    total: number;
    prev_page_url: string | null;
    next_page_url: string | null;
}
