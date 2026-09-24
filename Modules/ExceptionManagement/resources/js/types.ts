export type Severity = 'low' | 'medium' | 'high' | 'critical';

export interface ReconExceptionRow {
    id: number;
    business_date: string;
    carried_from: string | null;
    status: string;
    status_label: string;
    category: string;
    severity: Severity;
    amount_at_risk: string;
    transaction_id: string | null;
    payment_ids: string[];
    region: string | null;
    owner: { id: number; name: string } | null;
    due_at: string | null;
    overdue: boolean;
    state: string;
    state_label: string;
    soft: boolean;
    needs_review: boolean;
    resolution: string | null;
    created_at: string | null;
}

export interface QueueFilters {
    state: string | null;
    category: string | null;
    severity: Severity | null;
    owner: string | null;
    overdue: boolean;
    business_date: string | null;
    search: string | null;
}

export interface Paginated<T> {
    data: T[];
    meta: { current_page: number; last_page: number; total: number; from: number | null; to: number | null };
}

export interface TimelineEvent {
    id: number;
    type: string;
    actor: string;
    from: string | null;
    to: string | null;
    comment: string | null;
    at: string | null;
}

export interface ResultDetail {
    id: number;
    status: string;
    rule_id: string;
    explanation: string;
    expected_amount: string | null;
    actual_amount: string | null;
    posted_amount: string | null;
    variance: string | null;
    variance_pct: string | number | null;
    run_id: number;
    run_version: number | null;
}

export interface SourceRecordSet {
    sale: Record<string, string | null> | null;
    payments: Record<string, string | null>[];
    postings: Record<string, string | null>[];
}
