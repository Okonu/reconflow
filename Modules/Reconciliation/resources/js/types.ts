export interface RunBatchRef {
    id: number;
    version: number;
    mode: string;
    manual: boolean;
    rows: number;
    quarantined: number;
}

export interface RunSummary {
    items: number;
    sale_items: number;
    by_status: Record<string, number>;
    by_roll_up: Record<string, number>;
    match_rate: string;
    value_expected: string;
    value_reconciled: string;
    value_at_variance: string;
    value_unmatched_payments: string;
    exceptions: number;
    prior_day_cleared: { count: number; value: string };
    escalated_from_prior_day: number;
}

export interface ReconRun {
    id: number;
    business_date: string;
    version: number;
    status: 'queued' | 'running' | 'completed' | 'failed' | 'blocked_data';
    trigger: string;
    triggered_by: string | null;
    provisional: boolean;
    stale: boolean;
    superseded: boolean;
    rule_config: Record<string, string | number | null>;
    batches: Record<string, RunBatchRef | null>;
    summary: Partial<RunSummary>;
    blocked_reason: string | null;
    error: string | null;
    started_at: string | null;
    finished_at: string | null;
    duration_ms: number | null;
}

export interface SourceReadiness {
    source: string;
    label: string;
    batch_id: number | null;
    version: number | null;
    manual: boolean;
    mode_label: string | null;
    rows: number;
}

export interface Readiness {
    date: string;
    closed: boolean;
    sources: SourceReadiness[];
}
