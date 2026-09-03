import type { SelectOption } from '@/shared/types/options';

export type KnowledgeDocumentStatus =
    'pending' | 'processing' | 'indexed' | 'failed';

export type KnowledgeDocument = {
    id: number;
    original_name: string;
    title: string | null;
    department_id: string | null;
    department_label: string | null;
    doc_type: string | null;
    doc_type_label: string | null;
    approved_at: string | null;
    user_name?: string | null;
    size: number;
    human_size: string;
    status: KnowledgeDocumentStatus;
    status_label: string;
    index_progress: number;
    is_searchable: boolean;
    indexed_at: string | null;
    error_message: string | null;
    created_at: string | null;
};

export type KnowledgeMeta = {
    upload: {
        max_pdf_mb: number;
    };
    form: {
        departments: SelectOption[];
        document_types: SelectOption[];
    };
    filters: {
        departments: SelectOption[];
    };
    services: {
        n8n_index_configured: boolean;
        n8n_search_configured: boolean;
        ollama_url: string;
        ollama_model: string;
    };
    permissions: string[];
    is_super_admin: boolean;
};

export type KnowledgeDocumentFormData = {
    departmentId: string;
    title: string;
    documentType: string;
    approvedAt: string;
};

export type SearchMatch = {
    document_id: number;
    document_name: string;
    page: number;
    chunk_index?: number;
    excerpt: string;
    matched_terms: string[];
    phrase_matched: boolean;
    match_type: 'exact' | 'semantic' | 'hybrid';
    retrieval?: 'lexical' | 'semantic' | 'hybrid';
    score?: number | null;
};

export type SearchMode = 'fulltext' | 'rag';

export type SearchSource = {
    number: number;
    document_id: number;
    document_name: string;
    page: number;
    excerpt: string;
    score?: number | null;
};

export type SearchHistoryMessage = {
    role: 'user' | 'assistant';
    content: string;
};

export type SearchResult = {
    mode: SearchMode;
    answer: string;
    sources: SearchSource[];
    matches: SearchMatch[];
    quality?: AnswerQuality;
    interaction_id?: number;
};

export type HighlightedTextPart = {
    text: string;
    highlighted: boolean;
};
export type AnswerQuality = {
    answer_status: 'grounded' | 'insufficient_evidence' | 'citation_error';
    confidence: 'high' | 'medium' | 'low';
    citations_valid: boolean;
    cited_source_numbers: number[];
};

export type KnowledgeSearchQuality = {
    total_interactions: number;
    feedback_count: number;
    positive_feedback: number;
    negative_feedback: number;
    positive_rate: number | null;
    citation_errors: number;
    low_confidence: number;
    popular_questions: Array<{ question: string; total: number }>;
};
