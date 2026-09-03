import adminRoutes from '@/routes/api/admin';
import knowledgeRoutes from '@/routes/api/knowledge';
import { apiRequest } from '@/shared/api/http';
import type {
    KnowledgeDocumentFormData,
    KnowledgeSearchQuality,
    KnowledgeDocument,
    KnowledgeMeta,
    SearchHistoryMessage,
    SearchMode,
    SearchResult,
} from './types';

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

    async update(
        documentId: number,
        metadata: KnowledgeDocumentFormData,
    ): Promise<void> {
        await apiRequest(knowledgeRoutes.documents.update.url(documentId), {
            method: 'PATCH',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                department_id: metadata.departmentId,
                title: metadata.title,
                doc_type: metadata.documentType,
                approved_at: metadata.approvedAt,
            }),
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
        mode: SearchMode,
        history: SearchHistoryMessage[],
    ): Promise<SearchResult> {
        const response = await apiRequest<{ data: SearchResult }>(
            knowledgeRoutes.search.url(),
            {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    document_id: documentId,
                    question,
                    mode,
                    history,
                }),
            },
        );

        return response.data;
    },

    async feedback(
        interactionId: number,
        rating: 'positive' | 'negative',
        comment?: string,
    ): Promise<void> {
        await apiRequest(knowledgeRoutes.search.feedback.url(interactionId), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ rating, comment }),
        });
    },

    async searchQuality(): Promise<KnowledgeSearchQuality> {
        const response = await apiRequest<{ data: KnowledgeSearchQuality }>(
            adminRoutes.knowledge.searchQuality.url(),
        );

        return response.data;
    },

    documentUrl(documentId: number): string {
        return knowledgeRoutes.documents.show.url(documentId);
    },
};
