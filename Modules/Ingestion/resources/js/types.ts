export interface SourceOption {
    value: string;
    label: string;
}

export interface HeaderCheck {
    ok: boolean;
    message: string;
    missing: string[];
    unexpected: string[];
}

export interface StagedUpload {
    id: string;
    source: string;
    source_label: string;
    business_date: string;
    filename: string;
    rows_read: number;
    rows_valid: number;
    rows_invalid: number;
    header_check: HeaderCheck;
    duplicate_of_batch_id: number | null;
    date_has_data: boolean;
    state: 'staged' | 'confirmed' | 'cancelled' | 'expired';
    mode: 'replace' | 'append' | null;
    batch_id: number | null;
    uploaded_by: string | null;
    created_at: string | null;
    expires_at: string;
    can: { confirm: boolean; cancel: boolean };
}

export interface PreviewRow {
    row: number;
    key: string;
    status: 'valid' | 'invalid';
    errors: string[];
    values: Record<string, string | null>;
}

export interface SourceBatch {
    id: number;
    source: string;
    source_label: string;
    business_date: string;
    version: number;
    origin: 'source_system' | 'upload';
    status: 'active' | 'superseded';
    mode: 'replace' | 'append';
    filename: string | null;
    rows_received: number;
    rows_loaded: number;
    rows_quarantined: number;
    quarantine_reasons: Record<string, number>;
    empty: boolean;
    extracted_at: string | null;
    created_by: string | null;
    created_at: string | null;
}

export interface QuarantinedRow {
    row_number: number;
    record_key: string;
    reasons: string[];
    values: Record<string, string | null>;
}

export interface Paginated<T> {
    data: T[];
    meta?: { current_page: number; last_page: number; total: number };
}
