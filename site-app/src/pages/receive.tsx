import { useNavigate } from '@tanstack/react-router';
import { Button } from '@thabekhulu/ui';
import { useLiveQuery } from 'dexie-react-hooks';
import { ScanLine } from 'lucide-react';
import { useEffect, useRef, useState } from 'react';
import { db, enqueue } from '../lib/db';
import { compressImage, todayInSouthAfrica } from '../lib/device';
import { useCurrentProject } from '../lib/session';
import { flushOutbox } from '../lib/sync';
import { Page, PhotoInput, TextArea } from './ui';

interface BarcodeDetectorLike { detect(source: HTMLVideoElement): Promise<{ rawValue: string }[]> }
declare global { interface Window { BarcodeDetector?: new (options: { formats: string[] }) => BarcodeDetectorLike } }

/** Goods received against a purchase order: scan the order's QR code (or pick it), count what came, photograph the delivery note. */
export function ReceivePage() {
    const navigate = useNavigate();
    const project = useCurrentProject();
    const orders = useLiveQuery(() => (project ? db.orders.where('projectId').equals(project.id).sortBy('reference') : []), [project?.id], []);
    const [orderId, setOrderId] = useState('');
    const [qty, setQty] = useState<Record<number, string>>({});
    const [notes, setNotes] = useState('');
    const [photo, setPhoto] = useState<File | null>(null);
    const [scanning, setScanning] = useState(false);
    const [error, setError] = useState<string | null>(null);
    const order = orders.find((o) => o.id === orderId);

    useEffect(() => {
        if (order) setQty(Object.fromEntries(order.lines.map((l) => [l.id, String(l.outstanding)])));
    }, [order?.id]);

    async function submit() {
        if (!project || !order) return;
        const quantities = Object.fromEntries(Object.entries(qty).map(([k, v]) => [k, Number(v || 0)]).filter(([, v]) => Number(v) > 0));
        if (Object.keys(quantities).length === 0) return setError('Enter what was received for at least one line.');
        for (const l of order.lines) {
            if ((quantities[l.id] ?? 0) > l.outstanding) return setError(`More than ordered for "${l.description}" (${l.outstanding} outstanding).`);
        }
        await enqueue('receipt', `Goods received ${order.reference}, ${project.name}`, {
            clientId: crypto.randomUUID(), projectId: project.id, orderId: order.id, receivedOn: todayInSouthAfrica(),
            quantities: JSON.stringify(quantities), notes: notes || null,
        }, photo ? await compressImage(photo) : undefined, 'photo');
        // Reduce what is outstanding on the phone so a second delivery today starts from the right numbers.
        await db.orders.update(order.id, { lines: order.lines.map((l) => ({ ...l, outstanding: Math.max(0, l.outstanding - (quantities[l.id] ?? 0)) })) });
        void flushOutbox();
        await navigate({ to: '/' });
    }

    if (!project) return <p className="text-ink-soft">Choose a project first.</p>;

    return (
        <Page title="Goods received">
            {orders.length === 0 ? <p className="text-ink-soft">No open purchase orders for this project on the phone. Open this page with signal to download them.</p> : (
                <>
                    <div className="grid gap-1.5">
                        <label htmlFor="order" className="text-sm font-medium">Purchase order</label>
                        <div className="flex gap-2">
                            <select id="order" value={orderId} onChange={(e) => setOrderId(e.target.value)} className="h-11 flex-1 rounded-[var(--radius-control)] border border-concrete bg-surface px-3 text-base">
                                <option value="">Choose, or scan the QR code</option>
                                {orders.map((o) => <option key={o.id} value={o.id}>{o.reference} {o.supplier}</option>)}
                            </select>
                            {window.BarcodeDetector && <Button type="button" variant="secondary" onClick={() => setScanning(true)} aria-label="Scan QR code"><ScanLine className="size-5" /></Button>}
                        </div>
                    </div>
                    {scanning && <Scanner onResult={(value) => { setScanning(false); const id = value.replace(/^PO:/, ''); if (orders.some((o) => o.id === id)) { setOrderId(id); setError(null); } else setError('That code is not an open order for this project.'); }} onClose={() => setScanning(false)} />}
                    {order && (
                        <>
                            <ul className="grid gap-2">
                                {order.lines.filter((l) => l.outstanding > 0).map((l) => (
                                    <li key={l.id} className="flex items-center justify-between gap-3 rounded-[var(--radius-control)] border border-concrete bg-surface p-3">
                                        <span className="min-w-0"><span className="block font-medium">{l.description}</span><span className="block text-xs text-ink-soft">{l.outstanding} {l.unit} still to come</span></span>
                                        <input type="number" inputMode="decimal" min={0} max={l.outstanding} value={qty[l.id] ?? ''} onChange={(e) => setQty({ ...qty, [l.id]: e.target.value })} className="h-11 w-24 rounded-[var(--radius-control)] border border-concrete px-2 text-right text-base" aria-label={`Received of ${l.description}`} />
                                    </li>
                                ))}
                            </ul>
                            <PhotoInput label="Photo of the signed delivery note" facing="environment" onChange={setPhoto} preview={photo ? URL.createObjectURL(photo) : null} />
                            <TextArea label="Notes" name="notes" value={notes} onChange={(e) => setNotes(e.target.value)} optional />
                            <Button size="lg" onClick={() => void submit()}>Record goods received</Button>
                        </>
                    )}
                    {error && <p className="text-sm text-brick">{error}</p>}
                </>
            )}
        </Page>
    );
}

function Scanner({ onResult, onClose }: { onResult: (value: string) => void; onClose: () => void }) {
    const video = useRef<HTMLVideoElement>(null);
    useEffect(() => {
        let stream: MediaStream | null = null;
        let stop = false;
        const Detector = window.BarcodeDetector;
        void (async () => {
            if (!Detector) return;
            stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: 'environment' } });
            if (!video.current) return;
            video.current.srcObject = stream;
            await video.current.play();
            const detector = new Detector({ formats: ['qr_code'] });
            while (!stop && video.current) {
                const codes = await detector.detect(video.current).catch(() => []);
                if (codes[0]) { onResult(codes[0].rawValue); break; }
                await new Promise((r) => setTimeout(r, 300));
            }
        })();
        return () => { stop = true; stream?.getTracks().forEach((t) => t.stop()); };
    }, []);
    return (
        <div className="grid gap-2">
            <video ref={video} className="aspect-square w-full rounded-[var(--radius-panel)] bg-ink object-cover" muted playsInline />
            <Button variant="secondary" onClick={onClose}>Cancel scan</Button>
        </div>
    );
}

