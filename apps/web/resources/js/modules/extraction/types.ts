export type ExtractionStatus =
    'pending' | 'processing' | 'completed' | 'failed';

export type ExtractionField = {
    label: string;
    value: string;
};

export type ExtractionTable = {
    sheet: string;
    columns: string[];
    rows: string[][];
    truncated: boolean;
};

export type ExtractionResult = {
    document_type: string;
    document_type_label: string;
    format: string;
    title: string | null;
    language: string;
    snippet: string | null;
    fields: ExtractionField[];
    emails: string[];
    phones: string[];
    urls: string[];
    links: Record<string, string>;
    dates: string[];
    amounts: string[];
    key_values: ExtractionField[];
    keywords: string[];
    metadata: Record<string, string>;
    tables: ExtractionTable[];
    stats: Record<string, number | boolean>;
    resume: {
        name: string | null;
        skills: string[];
        education: string[];
        years_of_experience: number | null;
        suggested_role: string | null;
        role_confidence: number;
        role_matched_skills: string[];
    } | null;
    text: string;
    text_truncated: boolean;
};

export type DocumentExtraction = {
    id: number;
    original_name: string;
    mime_type: string | null;
    detected_format: string | null;
    size: number;
    human_size: string;
    status: ExtractionStatus;
    status_label: string;
    progress: number;
    result: ExtractionResult | null;
    error_message: string | null;
    completed_at: string | null;
    created_at: string | null;
    user_name?: string | null;
};
