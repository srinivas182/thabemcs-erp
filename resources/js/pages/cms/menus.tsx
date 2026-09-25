import { Head, router } from '@inertiajs/react';
import { Button, Field } from '@thabekhulu/ui';
import { type ReactNode, useState } from 'react';
import { PageHeader, SelectField } from '@/components/data';
import AppLayout from '@/layouts/app-layout';

type Item = { label: string; link: string };
interface Props { menus: { primary: Item[]; footer: Item[] }; pages: { key: string; label: string }[] }

export default function Menus({ menus, pages }: Props) {
    return (
        <>
            <Head title="Menus" />
            <div className="mx-auto grid max-w-3xl gap-6">
                <PageHeader title="Menus" description="What appears in the navigation at the top of the website, and in the footer." />
                <Menu location="primary" title="Top navigation" items={menus.primary} pages={pages} />
                <Menu location="footer" title="Footer" items={menus.footer} pages={pages} />
            </div>
        </>
    );
}

function Menu({ location, title, items: initial, pages }: { location: string; title: string; items: Item[]; pages: Props['pages'] }) {
    const [items, setItems] = useState<Item[]>(initial);
    const [saving, setSaving] = useState(false);

    const set = (i: number, patch: Partial<Item>) => setItems(items.map((x, j) => (j === i ? { ...x, ...patch } : x)));
    const move = (i: number, by: number) => {
        const next = [...items]; const j = i + by;
        if (j < 0 || j >= next.length) return;
        [next[i], next[j]] = [next[j]!, next[i]!];
        setItems(next);
    };

    return (
        <section className="grid gap-3 rounded-[var(--radius-panel)] border border-concrete bg-surface p-5">
            <h2 className="text-lg font-bold">{title}</h2>
            <ol className="grid gap-2">
                {items.map((item, i) => (
                    <li key={i} className="grid items-end gap-2 sm:grid-cols-[1fr_1fr_auto]">
                        <Field label="" aria-label="Label" name={`l${i}`} value={item.label} onChange={(e) => set(i, { label: e.target.value })} placeholder="What it says" />
                        <SelectField label="" name={`p${i}`} value={item.link} onChange={(v) => set(i, { link: v })} options={pages} placeholder="Which page" />
                        <span className="mb-2 flex gap-2 text-sm text-ink-soft">
                            <button onClick={() => move(i, -1)} aria-label="Move up">Up</button>
                            <button onClick={() => move(i, 1)} aria-label="Move down">Down</button>
                            <button className="text-brick" onClick={() => setItems(items.filter((_, j) => j !== i))}>Remove</button>
                        </span>
                    </li>
                ))}
            </ol>
            <div className="flex gap-2">
                <Button variant="ghost" size="sm" onClick={() => setItems([...items, { label: '', link: pages[0]?.key ?? '/' }])}>Add an item</Button>
                <Button size="sm" disabled={saving} onClick={() => { setSaving(true); router.put(`/website/menus/${location}`, { items }, { preserveScroll: true, onFinish: () => setSaving(false) }); }}>Save menu</Button>
            </div>
        </section>
    );
}

Menus.layout = (page: ReactNode) => <AppLayout>{page}</AppLayout>;
