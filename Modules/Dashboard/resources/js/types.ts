export interface Dashboard {
    date: string;
    preparing: boolean;
    kpis: {
        match_rate: string | null;
        items: number | null;
        value_expected: string | null;
        value_reconciled: string | null;
        value_at_variance: string | null;
        value_unmatched_payments: string | null;
        open_exceptions: number;
        timing_items: number;
        overdue_exceptions: number;
        critical_open: number;
        pending_approvals: number;
        posting_failed: number;
        pending_fuzzy: number;
        minutes_saved: number | null;
        prior_day_cleared: { count: number; value: string } | null;
    };
    latest_run: {
        id: number;
        version: number;
        status: string;
        provisional: boolean;
        stale: boolean;
        duration_ms: number | null;
        finished_at: string | null;
        blocked_reason: string | null;
        batches: { source: string; rows: number | null; quarantined: number | null; manual: boolean }[];
        signed_off: boolean;
    } | null;
    trend: { date: string; match_rate: number | null; exceptions: number | null }[];
    by_category: { category: string; count: number; value: string }[];
    ageing: { bucket: string; count: number }[];
    manual_seconds_per_item: number;
}
