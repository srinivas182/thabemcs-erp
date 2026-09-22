import { useNavigate } from '@tanstack/react-router';
import { Button, Field } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { useState } from 'react';
import { db, enqueue } from '../lib/db';
import { nowIso } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Choice, Page, TextArea } from './ui';

export function DeliveryPage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const suppliers = useLiveQuery(() => db.suppliers.orderBy('name').toArray(), [], []);
    const [supplierId, setSupplierId] = useState('');
    const [supplierName, setSupplierName] = useState('');
    const [note, setNote] = useState('');
    const [items, setItems] = useState('');
    const [condition, setCondition] = useState<'good' | 'damaged' | 'short'>('good');
    const [notes, setNotes] = useState('');
    const [error, setError] = useState<string | null>(null);

    async function submit() {
        if (!project) return;
        if (!supplierId && !supplierName.trim()) return setError('Choose or type the supplier.');
        if (items.trim().length < 3) return setError('List what was delivered.');
        if (condition !== 'good' && !notes.trim()) return setError('Describe what was damaged or short.');

        const name = supplierId ? suppliers.find((s) => s.id === supplierId)?.name : supplierName;
        await enqueue('delivery', `Delivery from ${name}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, supplierId: supplierId || null, supplierName: supplierId ? null : supplierName,
            deliveryNote: note || null, items, condition, notes: notes || null, receivedAt: nowIso(),
        });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;

    return (
        <Page title="Delivery received">
            <div className="grid gap-1.5">
                <label htmlFor="supplier" className="text-sm font-medium">Supplier</label>
                <select id="supplier" value={supplierId} onChange={(e) => setSupplierId(e.target.value)} className="h-11 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base">
                    <option value="">Not in the list</option>
                    {suppliers.map((s) => (<option key={s.id} value={s.id}>{s.name}</option>))}
                </select>
            </div>
            {!supplierId && <Field label="Supplier name" name="supplierName" value={supplierName} onChange={(e) => setSupplierName(e.target.value)} />}
            <Field label="Delivery note number" name="deliveryNote" value={note} onChange={(e) => setNote(e.target.value)} />
            <TextArea label="What was delivered" name="items" value={items} onChange={(e) => setItems(e.target.value)} placeholder="e.g. 120 bags cement 42.5N, 6 m³ 19 mm stone" />
            <Choice label="Condition" value={condition} onChange={setCondition} options={[{ key: 'good', label: 'All good' }, { key: 'damaged', label: 'Damaged' }, { key: 'short', label: 'Short' }]} />
            {condition !== 'good' && <TextArea label="What was wrong?" name="notes" value={notes} onChange={(e) => setNotes(e.target.value)} />}
            {error && <p className="text-sm text-brick">{error}</p>}
            <Button size="lg" onClick={() => void submit()}>Save delivery</Button>
            <p className="text-sm text-ink-soft">Tip: take a progress photo of the signed delivery note as well.</p>
        </Page>
    );
}
