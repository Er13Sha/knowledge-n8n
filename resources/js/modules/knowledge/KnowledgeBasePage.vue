<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import KnowledgeDocumentsPanel from '@/modules/knowledge/components/KnowledgeDocumentsPanel.vue';
import KnowledgeMetrics from '@/modules/knowledge/components/KnowledgeMetrics.vue';
import KnowledgeSearchPanel from '@/modules/knowledge/components/KnowledgeSearchPanel.vue';
import type {
    KnowledgeDocument,
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
}>();

const selectedDepartmentId = ref<string | null>(null);
const selectedDocumentType = ref<string | null>(null);
const currentPage = ref(1);
const pageSize = ref(25);
const pageSizeOptions = [25, 50, 100];

const filteredDocuments = computed(() =>
    props.documents.filter((document) => {
        const matchesDepartment =
            !selectedDepartmentId.value ||
            document.department_id === selectedDepartmentId.value;
        const matchesDocumentType =
            !selectedDocumentType.value ||
            document.doc_type === selectedDocumentType.value;

        return matchesDepartment && matchesDocumentType;
    }),
);

const hasActiveFilters = computed(() =>
    Boolean(selectedDepartmentId.value || selectedDocumentType.value),
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
    'update:searchMode': [value: SearchMode];
}>();

function clearFilters(): void {
    selectedDepartmentId.value = null;
    selectedDocumentType.value = null;
}

watch([selectedDepartmentId, selectedDocumentType, pageSize], () => {
    currentPage.value = 1;
});

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
        :total-storage-size="totalStorageSize"
    />

    <div class="document-filters">
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
            :search-scope-options="searchScopeOptions"
            :searchable-documents="searchableDocuments"
            :selected-document-id="selectedDocumentId"
            @search="emit('search')"
            @open-source="emit('openSource', $event)"
            @update:question="emit('update:question', $event)"
            @update:search-mode="emit('update:searchMode', $event)"
            @update:selected-document-id="
                emit('update:selectedDocumentId', $event)
            "
        />
    </div>
</template>
