import { cn } from '@thabekhulu/ui';
import { formatDateTime } from '@/components/data';

export interface ApprovalTrailData {
    status: string;
    steps: { sequence: number; role: string; decision: string; by: string | null; at: string | null; comment: string | null; current: boolean }[];
}

/** Who approves, in which order, and what each person decided. */
export function ApprovalTrail({ approval }: { approval: ApprovalTrailData }) {
    return (
        <section className="grid gap-2">
            <h2 className="font-bold">Approvals</h2>
            <ol className="grid gap-2">
                {approval.steps.map((s) => (
                    <li key={s.sequence} className={cn('border-l-2 pl-3 text-sm', s.decision === 'approved' ? 'border-line' : s.decision === 'rejected' ? 'border-brick' : s.current ? 'border-hivis' : 'border-concrete')}>
                        <p className="font-medium">
                            {s.sequence}. {s.role}{' '}
                            <span className={cn('font-normal', s.decision === 'rejected' ? 'text-brick' : 'text-ink-soft')}>
                                {s.decision === 'pending' ? (s.current ? 'waiting' : 'not yet') : `${s.decision} by ${s.by}, ${formatDateTime(s.at)}`}
                            </span>
                        </p>
                        {s.comment && <p className="italic">{s.comment}</p>}
                    </li>
                ))}
            </ol>
        </section>
    );
}

export const STATUS_LABEL: Record<string, string> = {
    draft: 'Draft', submitted: 'Waiting for approval', approved: 'Approved', rejected: 'Rejected', awarded: 'Ordered', cancelled: 'Cancelled',
    pending_approval: 'Waiting for approval', issued: 'Issued to supplier', partially_received: 'Partly received', received: 'Received',
};
