export interface JournalLine {
    account: string;
    debit: string;
    credit: string;
    transaction_id?: string | null;
}

export interface AdjustmentRow {
    id: number;
    exception_id: number;
    type: string;
    type_label: string;
    amount: string;
    reason: string;
    state: string;
    state_label: string;
    high_value: boolean;
    idempotency_key: string;
    journal: { posting_date: string; reference: string; lines: JournalLine[]; reverse_journal_id?: string } | null;
    erp_journal_id: string | null;
    posting_attempts: number;
    proposed_by: string | null;
    decided_by: string | null;
    decision_comment: string | null;
    created_at: string | null;
    posted_at: string | null;
    exception: { business_date: string; status: string; transaction_id: string | null; category: string } | null;
    can: { approve: boolean; reject: boolean; retry: boolean };
    why_not: string | null;
}

export interface AdjustmentContribution {
    items: AdjustmentRow[];
    types: { value: string; label: string }[];
    suggested_amount: string;
    threshold: string;
    can_propose: boolean;
}
