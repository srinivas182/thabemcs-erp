/**
 * The Thabekhulu development operating cycle.
 * Must stay in sync with App\Domains\Projects\Enums\ProjectStage.
 */
export const PROJECT_STAGES = [
    { key: 'plan', label: 'Plan' },
    { key: 'fund', label: 'Fund' },
    { key: 'land', label: 'Secure land' },
    { key: 'approve', label: 'Approvals' },
    { key: 'build', label: 'Build' },
    { key: 'sell_rent', label: 'Sell / rent' },
    { key: 'close', label: 'Close out' },
] as const;

export type ProjectStageKey = (typeof PROJECT_STAGES)[number]['key'];
