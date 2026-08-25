import { computed, ref } from 'vue';
import { knowledgeApi } from '@/features/knowledge/api';
import type {
    KnowledgeDocumentFormData,
    KnowledgeDocument,
    KnowledgeMeta,
    SearchResult,
} from '@/features/knowledge/types';
import { formatFileSize } from '@/shared/lib/formatters';

type Notify = (message: string, color?: string) => void;

function errorMessage(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Не удалось выполнить запрос.';
}

export function useKnowledgeBase(notify: Notify) {
    const documents = ref<KnowledgeDocument[]>([]);
    const meta = ref<KnowledgeMeta | null>(null);
    const selectedDocumentId = ref<number | null>(null);
    const question = ref('');
    const searchResult = ref<SearchResult | null>(null);
    const isLoadingDocuments = ref(false);
    const isUploading = ref(false);
    const isSearching = ref(false);

    const searchableDocuments = computed(() =>
        documents.value.filter((document) => document.is_searchable),
    );

    const searchScopeOptions = computed(() => [
        { id: null, original_name: 'Все документы' },
        ...searchableDocuments.value,
    ]);

    const indexedDocumentsCount = computed(
        () => searchableDocuments.value.length,
    );
    const processingDocumentsCount = computed(
        () =>
            documents.value.filter((document) =>
                ['pending', 'processing'].includes(document.status),
            ).length,
    );
    const failedDocumentsCount = computed(
        () =>
            documents.value.filter((document) => document.status === 'failed')
                .length,
    );
    const totalStorageSize = computed(() =>
        formatFileSize(
            documents.value.reduce(
                (total, document) => total + document.size,
                0,
            ),
        ),
    );
    const areCoreServicesConfigured = computed(
        () => meta.value?.services.n8n_search_configured === true,
    );
    const hasDocumentsInProgress = computed(() =>
        documents.value.some((document) =>
            ['pending', 'processing'].includes(document.status),
        ),
    );

    async function loadDocuments(): Promise<void> {
        isLoadingDocuments.value = true;

        try {
            const response = await knowledgeApi.documents();
            documents.value = response.data;
            meta.value = response.meta;
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            isLoadingDocuments.value = false;
        }
    }

    async function uploadDocument(
        document: File,
        metadata: KnowledgeDocumentFormData,
    ): Promise<boolean> {
        isUploading.value = true;

        try {
            await knowledgeApi.upload(document, metadata);
            notify('PDF загружен и отправлен на индексацию.');
            await loadDocuments();

            return true;
        } catch (error) {
            notify(errorMessage(error), 'error');

            return false;
        } finally {
            isUploading.value = false;
        }
    }

    async function deleteDocument(document: KnowledgeDocument): Promise<void> {
        if (!window.confirm(`Удалить документ «${document.original_name}»?`)) {
            return;
        }

        try {
            await knowledgeApi.delete(document.id);

            if (selectedDocumentId.value === document.id) {
                selectedDocumentId.value = null;
                searchResult.value = null;
            }

            notify('Документ удалён.');
            await loadDocuments();
        } catch (error) {
            notify(errorMessage(error), 'error');
        }
    }

    async function retryIndexing(document: KnowledgeDocument): Promise<void> {
        try {
            await knowledgeApi.retryIndexing(document.id);
            notify('Документ повторно отправлен на индексацию.');
            await loadDocuments();
        } catch (error) {
            notify(errorMessage(error), 'error');
        }
    }

    async function searchDocument(): Promise<void> {
        if (!question.value.trim()) {
            notify('Введите вопрос.', 'warning');

            return;
        }

        isSearching.value = true;
        searchResult.value = null;

        try {
            searchResult.value = await knowledgeApi.search(
                selectedDocumentId.value,
                question.value,
            );
        } catch (error) {
            notify(errorMessage(error), 'error');
        } finally {
            isSearching.value = false;
        }
    }

    return {
        areCoreServicesConfigured,
        deleteDocument,
        documentUrl: knowledgeApi.documentUrl,
        documents,
        failedDocumentsCount,
        hasDocumentsInProgress,
        indexedDocumentsCount,
        isLoadingDocuments,
        isSearching,
        isUploading,
        loadDocuments,
        meta,
        processingDocumentsCount,
        question,
        retryIndexing,
        searchDocument,
        searchResult,
        searchableDocuments,
        searchScopeOptions,
        selectedDocumentId,
        totalStorageSize,
        uploadDocument,
    };
}
