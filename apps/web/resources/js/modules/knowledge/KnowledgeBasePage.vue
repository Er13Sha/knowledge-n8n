<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import KnowledgeDocumentsPanel from '@/modules/knowledge/components/KnowledgeDocumentsPanel.vue';
import KnowledgeMetrics from '@/modules/knowledge/components/KnowledgeMetrics.vue';
import KnowledgeSearchPanel from '@/modules/knowledge/components/KnowledgeSearchPanel.vue';
import type {
    KnowledgeDocument,
    KnowledgeDocumentStatus,
    SearchMode,
    SearchResult,
    SearchSource,
} from '@/modules/knowledge/types';
import type { SelectOption } from '@/shared/types/options';

const props = defineProps<{
    documents: KnowledgeDocument[];
    canCreate: boolean;
    canUpdate: boolean;
    canDelete: boolean;
    departments: SelectOption[];
    documentTypes: SelectOption[];
    indexedDocumentsCount: number;
    processingDocumentsCount: number;
    totalStorageSize: string;
    failedDocumentsCount: number;
    isLoadingDocuments: boolean;
    isAdmin: boolean;
    searchableDocuments: KnowledgeDocument[];
    searchScopeOptions: Array<{
        id: number | null;
        original_name: string;
    }>;
    selectedDocumentId: number | null;
    question: string;
    searchMode: SearchMode;
    isSearching: boolean;
    searchResult: SearchResult | null;
    feedbackRating: 'positive' | 'negative' | null;
    isSubmittingFeedback: boolean;
}>();

const documentSearch = ref('');
const selectedDepartmentId = ref<string | null>(null);
const selectedDocumentType = ref<string | null>(null);
const selectedStatus = ref<KnowledgeDocumentStatus | null>(null);
const currentPage = ref(1);
const pageSize = ref(25);
const pageSizeOptions = [25, 50, 100];
const documentStatusOptions: SelectOption[] = [
    { value: 'pending', title: 'В очереди' },
    { value: 'processing', title: 'Идёт индексация' },
    { value: 'indexed', title: 'Готов к поиску' },
    { value: 'failed', title: 'Ошибка индексации' },
];

const filteredDocuments = computed(() => {
    const search = documentSearch.value.trim().toLocaleLowerCase();

    return props.documents.filter((document) => {
        const matchesSearch =
            !search ||
            [document.title, document.original_name].some((value) =>
                value?.toLocaleLowerCase().includes(search),
            );
        const matchesDepartment =
            !selectedDepartmentId.value ||
            document.department_id === selectedDepartmentId.value;
        const matchesDocumentType =
            !selectedDocumentType.value ||
            document.doc_type === selectedDocumentType.value;
        const matchesStatus =
            !selectedStatus.value || document.status === selectedStatus.value;

        return (
            matchesSearch &&
            matchesDepartment &&
            matchesDocumentType &&
            matchesStatus
        );
    });
});

const hasActiveFilters = computed(() =>
    Boolean(
        documentSearch.value.trim() ||
        selectedDepartmentId.value ||
        selectedDocumentType.value ||
        selectedStatus.value,
    ),
);

const pageCount = computed(() =>
    Math.max(1, Math.ceil(filteredDocuments.value.length / pageSize.value)),
);

const paginatedDocuments = computed(() => {
    const start = (currentPage.value - 1) * pageSize.value;

    return filteredDocuments.value.slice(start, start + pageSize.value);
});

const emit = defineEmits<{
    upload: [];
    refresh: [];
    openPdf: [document: KnowledgeDocument];
    openDetails: [document: KnowledgeDocument];
    edit: [document: KnowledgeDocument];
    retry: [document: KnowledgeDocument];
    delete: [document: KnowledgeDocument];
    'update:selectedDocumentId': [value: number | null];
    'update:question': [value: string];
    search: [];
    openSource: [source: SearchSource];
    feedback: [rating: 'positive' | 'negative'];
    copyAnswer: [];
    'update:searchMode': [value: SearchMode];
}>();

function clearFilters(): void {
    documentSearch.value = '';
    selectedDepartmentId.value = null;
    selectedDocumentType.value = null;
    selectedStatus.value = null;
}

watch(
    [
        documentSearch,
        selectedDepartmentId,
        selectedDocumentType,
        selectedStatus,
        pageSize,
    ],
    () => {
        currentPage.value = 1;
    },
);

watch(pageCount, (count) => {
    if (currentPage.value > count) {
        currentPage.value = count;
    }
});
</script>

<template>
    <header class="page-heading">
        <div>
            <h1>База знаний</h1>
            <p>Документы, индексация и ответы по внутренним данным</p>
        </div>
        <v-btn
            v-if="canCreate"
            color="primary"
            prepend-icon="mdi-plus"
            @click="emit('upload')"
        >
            Добавить документ
        </v-btn>
    </header>

    <KnowledgeMetrics
        :documents-count="documents.length"
        :indexed-documents-count="indexedDocumentsCount"
        :processing-documents-count="processingDocumentsCount"
        :is-admin="isAdmin"
        :total-storage-size="totalStorageSize"
    />

    <div class="document-filters">
        <v-text-field
            v-model="documentSearch"
            clearable
            density="compact"
            hide-details
            label="Поиск по названию"
            placeholder="Название или имя PDF"
            prepend-inner-icon="mdi-magnify"
            variant="outlined"
        />
        <v-select
            v-model="selectedDepartmentId"
            clearable
            density="compact"
            hide-details
            item-title="title"
            item-value="value"
            :items="departments"
            label="Отдел"
            variant="outlined"
        />
        <v-select
            v-model="selectedDocumentType"
            clearable
            density="compact"
            hide-details
            item-title="title"
            item-value="value"
            :items="documentTypes"
            label="Тип документа"
            variant="outlined"
        />
        <v-select
            v-if="isAdmin"
            v-model="selectedStatus"
            clearable
            density="compact"
            hide-details
            item-title="title"
            item-value="value"
            :items="documentStatusOptions"
            label="Статус"
            variant="outlined"
        />
        <v-btn
            v-if="hasActiveFilters"
            class="document-filters__reset"
            prepend-icon="mdi-filter-off-outline"
            variant="text"
            @click="clearFilters"
        >
            Сбросить
        </v-btn>
    </div>

    <div class="workspace-grid">
        <KnowledgeDocumentsPanel
            :all-documents-count="documents.length"
            :documents="paginatedDocuments"
            :can-create="canCreate"
            :can-delete="canDelete"
            :can-update="canUpdate"
            :failed-documents-count="failedDocumentsCount"
            :filtered-documents-count="filteredDocuments.length"
            :has-active-filters="hasActiveFilters"
            :is-admin="isAdmin"
            :is-loading-documents="isLoadingDocuments"
            :page="currentPage"
            :page-count="pageCount"
            :page-size="pageSize"
            :page-size-options="pageSizeOptions"
            :selected-document-id="selectedDocumentId"
            @update:page="currentPage = $event"
            @update:page-size="pageSize = $event"
            @delete="emit('delete', $event)"
            @clear-filters="clearFilters"
            @edit="emit('edit', $event)"
            @open-details="emit('openDetails', $event)"
            @open-pdf="emit('openPdf', $event)"
            @refresh="emit('refresh')"
            @retry="emit('retry', $event)"
            @upload="emit('upload')"
        />

        <KnowledgeSearchPanel
            :is-searching="isSearching"
            :question="question"
            :search-mode="searchMode"
            :search-result="searchResult"
            :feedback-rating="feedbackRating"
            :is-submitting-feedback="isSubmittingFeedback"
            :search-scope-options="searchScopeOptions"
            :searchable-documents="searchableDocuments"
            :selected-document-id="selectedDocumentId"
            @search="emit('search')"
            @open-source="emit('openSource', $event)"
            @feedback="emit('feedback', $event)"
            @copy-answer="emit('copyAnswer')"
            @update:question="emit('update:question', $event)"
            @update:search-mode="emit('update:searchMode', $event)"
            @update:selected-document-id="
                emit('update:selectedDocumentId', $event)
            "
        />
    </div>
</template>
