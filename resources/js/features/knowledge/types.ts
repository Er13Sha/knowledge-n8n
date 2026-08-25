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
        departments: KnowledgeOption[];
        document_types: KnowledgeOption[];
    };
    services: {
        n8n_index_configured: boolean;
        n8n_search_configured: boolean;
        ollama_url: string;
        ollama_model: string;
    };
};

export type KnowledgeOption = {
    value: string;
    title: string;
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
    excerpt: string;
    matched_terms: string[];
    phrase_matched: boolean;
    match_type: 'exact' | 'semantic';
};

export type SearchResult = {
    matches: SearchMatch[];
};

export type HighlightedTextPart = {
    text: string;
    highlighted: boolean;
};
