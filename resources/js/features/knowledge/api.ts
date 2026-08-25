import { apiRequest } from '@/api';
import type {
    KnowledgeDocumentFormData,
    KnowledgeDocument,
    KnowledgeMeta,
    SearchResult,
} from '@/features/knowledge/types';
import knowledgeRoutes from '@/routes/api/knowledge';

type KnowledgeDocumentsResponse = {
    data: KnowledgeDocument[];
    meta: KnowledgeMeta;
};

export const knowledgeApi = {
    async documents(): Promise<KnowledgeDocumentsResponse> {
        return apiRequest<KnowledgeDocumentsResponse>(
            knowledgeRoutes.documents.index.url(),
        );
    },

    async upload(
        document: File,
        metadata: KnowledgeDocumentFormData,
    ): Promise<void> {
        const formData = new FormData();
        formData.append('department_id', metadata.departmentId);
        formData.append('title', metadata.title);
        formData.append('doc_type', metadata.documentType);
        formData.append('approved_at', metadata.approvedAt);
        formData.append('document', document);

        await apiRequest(knowledgeRoutes.documents.store.url(), {
            method: 'POST',
            body: formData,
        });
    },

    async delete(documentId: number): Promise<void> {
        await apiRequest(knowledgeRoutes.documents.destroy.url(documentId), {
            method: 'DELETE',
        });
    },

    async retryIndexing(documentId: number): Promise<void> {
        await apiRequest(
            knowledgeRoutes.documents.retryIndexing.url(documentId),
            { method: 'POST' },
        );
    },

    async search(
        documentId: number | null,
        question: string,
    ): Promise<SearchResult> {
        const response = await apiRequest<{ data: SearchResult }>(
            knowledgeRoutes.search.url(),
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_id: documentId,
                    question,
                }),
            },
        );

        return response.data;
    },

    documentUrl(documentId: number): string {
        return knowledgeRoutes.documents.show.url(documentId);
    },
};
