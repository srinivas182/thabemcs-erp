import { Head, router, useForm } from '@inertiajs/react';
import { Button, cn, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { formatDateTime, PageHeader } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

interface Props {
    tokens: { id: number; name: string; created: string | null; lastUsed: string | null }[];
    webhooks: { id: string; name: string; url: string; events: string[]; active: boolean; lastDelivered: string | null; lastError: string | null; failures: number }[];
    deliveries: { id: number; webhook: string; event: string; status: number | null; error: string | null; attempts: number; delivered: string | null; at: string }[];
    events: { key: string; label: string }[];
}

export default function Api({ tokens, webhooks, deliveries, events }: Props) {
    const token = useForm({ name: '' });
    const hook = useForm<{ name: string; url: string; events: string[] }>({ name: '', url: '', events: [] });
    const [showHook, setShowHook] = useState(false);

    return (
        <>
            <Head title="API and webhooks" />
            <div className="mx-auto grid max-w-4xl gap-8">
                <PageHeader title="API and webhooks" description="Let another system read data from here, or be told as things happen." />

                <section className="grid gap-3">
                    <h2 className="text-lg font-bold">Read-only API tokens</h2>
                    <p className="text-sm text-ink-soft">A token lets another system read data through the API. It can never change anything. The full token is shown once, when you create it.</p>
                    <form onSubmit={(e) => { e.preventDefault(); token.post('/settings/api/tokens', { preserveScroll: true, onSuccess: () => token.reset() }); }} className="flex flex-wrap items-end gap-3">
                        <div className="w-64"><Field label="What is it for" name="name" value={token.data.name} onChange={(e) => token.setData('name', e.target.value)} error={token.errors.name} placeholder="Accountant's dashboard" /></div>
                        <Button type="submit" disabled={token.processing}>Create a token</Button>
                    </form>
                    <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                        {tokens.map((t) => (
                            <li key={t.id} className="flex items-center justify-between gap-3 px-3 py-2">
                                <span>{t.name}<span className="block text-xs text-ink-soft">Created {formatDateTime(t.created)}{t.lastUsed ? `, last used ${formatDateTime(t.lastUsed)}` : ', never used'}</span></span>
                                <button className="text-xs text-brick hover:underline" onClick={() => window.confirm(`Revoke "${t.name}"? Anything using it stops working.`) && router.delete(`/settings/api/tokens/${t.id}`, { preserveScroll: true })}>Revoke</button>
                            </li>
                        ))}
                        {tokens.length === 0 && <li className="px-3 py-2 text-ink-soft">No tokens.</li>}
                    </ul>
                </section>

                <section className="grid gap-3">
                    <div className="flex items-center justify-between">
                        <h2 className="text-lg font-bold">Webhooks</h2>
                        <Button size="sm" variant="secondary" onClick={() => setShowHook(!showHook)}>Add a webhook</Button>
                    </div>
                    <p className="text-sm text-ink-soft">We send a signed message to your address when something happens. Check the <code className="text-xs">X-Thabekhulu-Signature</code> header against your secret before trusting it.</p>

                    {showHook && (
                        <form onSubmit={(e) => { e.preventDefault(); hook.post('/settings/api/webhooks', { preserveScroll: true, onSuccess: () => { hook.reset(); setShowHook(false); } }); }} className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-4">
                            <div className="grid gap-3 sm:grid-cols-2">
                                <Field label="Name" name="name" value={hook.data.name} onChange={(e) => hook.setData('name', e.target.value)} error={hook.errors.name} />
                                <Field label="Address (https)" name="url" value={hook.data.url} onChange={(e) => hook.setData('url', e.target.value)} error={hook.errors.url} placeholder="https://example.co.za/hooks/thabekhulu" />
                            </div>
                            <fieldset className="grid gap-1">
                                <legend className="text-sm font-medium">Tell me about</legend>
                                {events.map((ev) => (
                                    <label key={ev.key} className="flex items-center gap-2 text-sm">
                                        <input type="checkbox" className="size-4 accent-line" checked={hook.data.events.includes(ev.key)}
                                            onChange={(e) => hook.setData('events', e.target.checked ? [...hook.data.events, ev.key] : hook.data.events.filter((x) => x !== ev.key))} />
                                        {ev.label}
                                    </label>
                                ))}
                                {hook.errors.events && <p className="text-sm text-brick">{hook.errors.events}</p>}
                            </fieldset>
                            <div><Button type="submit" disabled={hook.processing}>Add webhook</Button></div>
                        </form>
                    )}

                    <ul className="grid gap-2">
                        {webhooks.map((w) => (
                            <li key={w.id} className={cn('rounded-[var(--radius-panel)] border bg-surface p-3 text-sm', w.failures > 0 ? 'border-brick' : 'border-concrete')}>
                                <div className="flex flex-wrap items-start justify-between gap-2">
                                    <span>
                                        <span className="font-semibold">{w.name}</span> <span className="text-ink-soft">{w.url}</span>
                                        <span className="block text-xs text-ink-soft">{w.events.length} events{w.lastDelivered ? `, last delivered ${formatDateTime(w.lastDelivered)}` : ', nothing delivered yet'}</span>
                                        {w.lastError && <span className="block text-xs text-brick">{w.lastError} ({w.failures} failures)</span>}
                                    </span>
                                    <span className="flex gap-2">
                                        <button className="text-xs underline" onClick={() => router.patch(`/settings/api/webhooks/${w.id}`, { active: !w.active }, { preserveScroll: true })}>{w.active ? 'Switch off' : 'Switch on'}</button>
                                        <button className="text-xs text-brick underline" onClick={() => window.confirm('Remove this webhook?') && router.delete(`/settings/api/webhooks/${w.id}`, { preserveScroll: true })}>Remove</button>
                                    </span>
                                </div>
                            </li>
                        ))}
                        {webhooks.length === 0 && <li className="text-ink-soft">No webhooks.</li>}
                    </ul>

                    {deliveries.length > 0 && (
                        <ul className="divide-y divide-concrete rounded-[var(--radius-panel)] border border-concrete bg-surface text-sm">
                            {deliveries.map((d) => (
                                <li key={d.id} className="flex flex-wrap justify-between gap-2 px-3 py-2">
                                    <span>{d.webhook}: {d.event}</span>
                                    <span className={cn(d.delivered ? 'text-line-deep' : 'text-brick')}>{d.delivered ? `delivered (${d.status})` : `${d.error ?? 'not delivered'} after ${d.attempts} attempts`}, {formatDateTime(d.at)}</span>
                                </li>
                            ))}
                        </ul>
                    )}
                </section>
            </div>
        </>
    );
}

Api.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
