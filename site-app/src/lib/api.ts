/**
 * Talks to the Laravel API with Sanctum cookie authentication.
 * The site app is served from the same domain (/site), so cookies and CSRF work without tokens on the phone.
 */

export class ApiError extends Error {
    constructor(
        public status: number,
        message: string,
        public errors: Record<string, string[]> = {},
    ) {
        super(message);
    }
}

function xsrfToken(): string | null {
    const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/);
    return match?.[1] ? decodeURIComponent(match[1]) : null;
}

async function ensureCsrf(): Promise<void> {
    if (!xsrfToken()) {
        await fetch('/sanctum/csrf-cookie', { credentials: 'include' });
    }
}

export async function api<T>(path: string, init: RequestInit = {}): Promise<T> {
    const method = (init.method ?? 'GET').toUpperCase();
    if (method !== 'GET') await ensureCsrf();

    const headers = new Headers(init.headers);
    headers.set('Accept', 'application/json');
    headers.set('X-Requested-With', 'XMLHttpRequest');
    const token = xsrfToken();
    if (token) headers.set('X-XSRF-TOKEN', token);
    if (init.body && !(init.body instanceof FormData)) headers.set('Content-Type', 'application/json');

    const response = await fetch(path, { ...init, headers, credentials: 'include' });

    if (!response.ok) {
        let body: { message?: string; errors?: Record<string, string[]> } = {};
        try {
            body = await response.json();
        } catch {
            /* not JSON */
        }
        throw new ApiError(response.status, body.message ?? `Request failed (${response.status})`, body.errors ?? {});
    }

    return response.status === 204 ? (undefined as T) : ((await response.json()) as T);
}

export interface Me {
    id: string;
    name: string;
    email: string;
    company: { ulid: string; name: string } | null;
    roles: string[];
}

export const getMe = () => api<{ data: Me }>('/api/v1/me').then((r) => r.data);

/** Returns 'two-factor' when the account needs an authenticator code next. */
export async function login(email: string, password: string): Promise<'ok' | 'two-factor'> {
    await ensureCsrf();
    const result = await api<{ two_factor?: boolean } | undefined>('/login', { method: 'POST', body: JSON.stringify({ email, password, remember: true }) });
    return result?.two_factor ? 'two-factor' : 'ok';
}

export const twoFactor = (code: string) => api('/two-factor-challenge', { method: 'POST', body: JSON.stringify({ code }) });

export const logout = () => api('/logout', { method: 'POST' });
