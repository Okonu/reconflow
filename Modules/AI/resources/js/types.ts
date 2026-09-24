export interface AiSuggestion {
    id: number;
    kind: 'triage' | 'run_summary';
    status: 'pending' | 'accepted' | 'overridden' | 'informational' | 'failed';
    status_label: string;
    exception_id: number | null;
    run_id: number | null;
    model: string;
    origin_label: string;
    prompt_version: string;
    served_by_fallback: boolean;
    output: {
        likely_cause?: string;
        recommended_action?: string;
        explanation?: string;
        evidence?: string[];
        confidence?: number;
        headline?: string;
        paragraphs?: string[];
        watch_items?: string[];
    };
    likely_cause_label: string | null;
    recommended_action_label: string | null;
    override_action_label: string | null;
    error: string | null;
    latency_ms: number;
    input_tokens: number;
    output_tokens: number;
    requested_by: string | null;
    decided_by: string | null;
    decision_reason: string | null;
    decided_at: string | null;
    created_at: string | null;
    can: { decide: boolean };
}

export interface AiStatus {
    enabled: boolean;
    configured: boolean;
    killed: boolean;
    client_available: boolean;
    model: string;
    reason: string | null;
    toggled_by: string | null;
    toggled_at: string | null;
}

export interface OversightStats {
    days: number;
    triage: { total: number; pending: number; accepted: number; overridden: number; failed: number; acceptance_rate: number | null };
    summaries: number;
    avg_latency_ms: number;
    input_tokens: number;
    output_tokens: number;
    fallback_served: number;
    avg_confidence: number | null;
    failure_rate: number | null;
    by_category: { category: string; accepted: number; overridden: number }[];
}


export interface EvalRun {
    id: number;
    eval_set: string;
    model: string;
    prompt_version: string;
    cases: number;
    action_correct: number;
    cause_correct: number;
    errors: number;
    created_at: string | null;
}

