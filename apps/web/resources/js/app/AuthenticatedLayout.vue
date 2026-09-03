<script setup lang="ts">
import {
    computed,
    defineAsyncComponent,
    onBeforeUnmount,
    onMounted,
    ref,
    watch,
} from 'vue';
import { useDisplay } from 'vuetify';
import '@/app/styles.css';
import AppNavigation from '@/app/AppNavigation.vue';
import { useAppRouter } from '@/app/router';
import type { AppRoutePath } from '@/app/router';
import EmployeesPage from '@/modules/access/EmployeesPage.vue';
import DataExtractionPage from '@/modules/extraction/DataExtractionPage.vue';
import { knowledgeApi } from '@/modules/knowledge/api';
import KnowledgeDocumentDetailsDialog from '@/modules/knowledge/components/KnowledgeDocumentDetailsDialog.vue';
import KnowledgeDocumentEditDialog from '@/modules/knowledge/components/KnowledgeDocumentEditDialog.vue';
import KnowledgeUploadDialog from '@/modules/knowledge/components/KnowledgeUploadDialog.vue';
import DashboardPage from '@/modules/knowledge/DashboardPage.vue';
import KnowledgeBasePage from '@/modules/knowledge/KnowledgeBasePage.vue';
import type {
    KnowledgeDocument,
    KnowledgeDocumentFormData,
    KnowledgeSearchQuality,
    SearchMode,
    SearchSource,
} from '@/modules/knowledge/types';
import { useKnowledgeBase } from '@/modules/knowledge/useKnowledgeBase';
import ProfilePage from '@/modules/profile/ProfilePage.vue';
import type { AuthUser } from '@/shared/types/auth';

const SwaggerPage = defineAsyncComponent(
    () => import('@/modules/api-docs/SwaggerPage.vue'),
);

const props = defineProps<{
    user: AuthUser;
}>();

const emit = defineEmits<{
    logout: [];
    userUpdated: [user: AuthUser];
}>();

const { mdAndUp } = useDisplay();
const navigationOpen = ref(mdAndUp.value);
const uploadDialogOpen = ref(false);
const detailsDialogOpen = ref(false);
const editDialogOpen = ref(false);
const selectedDocument = ref<KnowledgeDocument | null>(null);
const isUpdatingDocument = ref(false);
const snackbar = ref({
    isOpen: false,
    message: '',
    color: 'success',
});

const userInitial = computed(
    () => props.user.name.trim().charAt(0).toLocaleUpperCase('ru-RU') || 'A',
);

const {
    areCoreServicesConfigured,
    clearSearchConversation,
    deleteDocument,
    documentUrl,
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
    searchMode,
    searchResult,
    feedbackRating,
    isSubmittingFeedback,
    sendFeedback,
    copySearchAnswer,
    searchableDocuments,
    searchScopeOptions,
    selectedDocumentId,
    totalStorageSize,
    updateSearchMode,
    updateDocument: updateKnowledgeDocument,
    uploadDocument: uploadKnowledgeDocument,
} = useKnowledgeBase(showMessage);

const { currentRoute, navigate: navigateTo } = useAppRouter();
const currentSection = computed(() => currentRoute.value.section);
const pageTitle = computed(() => currentRoute.value.title);
const modelName = computed(() => meta.value?.services.ollama_model ?? '');
const searchQuality = ref<KnowledgeSearchQuality | null>(null);
const isAdmin = computed(
    () =>
        props.user.is_super_admin === true ||
        props.user.roles?.some((role) => role.key === 'admin') === true,
);
const canCreateKnowledge = computed(
    () =>
        props.user.is_super_admin === true ||
        meta.value?.permissions.includes('knowledge.create') === true,
);
const canUpdateKnowledge = computed(
    () =>
        props.user.is_super_admin === true ||
        meta.value?.permissions.includes('knowledge.update') === true,
);
const canDeleteKnowledge = computed(
    () =>
        props.user.is_super_admin === true ||
        meta.value?.permissions.includes('knowledge.delete') === true,
);
let refreshInterval: number | undefined;

async function loadSearchQuality(): Promise<void> {
    if (!props.user.is_super_admin) {
        return;
    }

    try {
        searchQuality.value = await knowledgeApi.searchQuality();
    } catch (error) {
        showMessage(
            error instanceof Error
                ? error.message
                : 'Не удалось загрузить статистику AI-поиска.',
            'error',
        );
    }
}

function showMessage(message: string, color = 'success'): void {
    snackbar.value = {
        isOpen: true,
        message,
        color,
    };
}

function navigate(path: AppRoutePath): void {
    navigateTo(path);
    navigationOpen.value = mdAndUp.value;
}

async function uploadDocument(
    file: File,
    metadata: KnowledgeDocumentFormData,
): Promise<void> {
    if (await uploadKnowledgeDocument(file, metadata)) {
        uploadDialogOpen.value = false;
    }
}

function viewDocument(document: { id: number }): void {
    window.open(documentUrl(document.id), '_blank', 'noopener,noreferrer');
}

function openSearchSource(source: SearchSource): void {
    window.open(
        `${documentUrl(source.document_id)}#page=${source.page}`,
        '_blank',
        'noopener,noreferrer',
    );
}

function openDocumentDetails(document: KnowledgeDocument): void {
    selectedDocument.value = document;
    detailsDialogOpen.value = true;
}

function openSelectedDocumentPdf(): void {
    if (selectedDocument.value) {
        viewDocument(selectedDocument.value);
    }
}

function openDocumentEdit(document: KnowledgeDocument): void {
    selectedDocument.value = document;
    editDialogOpen.value = true;
    detailsDialogOpen.value = false;
}

async function updateDocument(
    metadata: KnowledgeDocumentFormData,
): Promise<void> {
    if (!selectedDocument.value) {
        return;
    }

    isUpdatingDocument.value = true;

    if (await updateKnowledgeDocument(selectedDocument.value, metadata)) {
        editDialogOpen.value = false;
    }

    isUpdatingDocument.value = false;
}

function updateSelectedDocument(documentId: number | null): void {
    selectedDocumentId.value = documentId;
    clearSearchConversation();
}

function changeSearchMode(mode: SearchMode): void {
    updateSearchMode(mode);
}

watch(mdAndUp, (isDesktop) => {
    navigationOpen.value = isDesktop;
});

onMounted(async () => {
    await loadDocuments();
    await loadSearchQuality();

    refreshInterval = window.setInterval(() => {
        if (hasDocumentsInProgress.value) {
            void loadDocuments();
        }
    }, 5000);
});

onBeforeUnmount(() => {
    window.clearInterval(refreshInterval);
});
</script>

<template>
    <v-app>
        <AppNavigation
            v-model:navigation-open="navigationOpen"
            :are-core-services-configured="areCoreServicesConfigured"
            :current-section="currentSection"
            :is-loading-documents="isLoadingDocuments"
            :can-manage-employees="props.user.is_super_admin === true"
            :md-and-up="mdAndUp"
            :model-name="modelName"
            :page-title="pageTitle"
            :user="props.user"
            :user-initial="userInitial"
            @logout="emit('logout')"
            @navigate="navigate"
            @refresh="loadDocuments"
        />

        <v-main class="admin-main">
            <v-container class="admin-container" fluid>
                <KnowledgeBasePage
                    v-if="currentSection === 'knowledge'"
                    :departments="meta?.filters.departments ?? []"
                    :document-types="meta?.form.document_types ?? []"
                    :documents="documents"
                    :can-create="canCreateKnowledge"
                    :can-delete="canDeleteKnowledge"
                    :can-update="canUpdateKnowledge"
                    :failed-documents-count="failedDocumentsCount"
                    :indexed-documents-count="indexedDocumentsCount"
                    :is-admin="isAdmin"
                    :is-loading-documents="isLoadingDocuments"
                    :is-searching="isSearching"
                    :processing-documents-count="processingDocumentsCount"
                    :question="question"
                    :search-mode="searchMode"
                    :search-result="searchResult"
                    :feedback-rating="feedbackRating"
                    :is-submitting-feedback="isSubmittingFeedback"
                    :search-scope-options="searchScopeOptions"
                    :searchable-documents="searchableDocuments"
                    :selected-document-id="selectedDocumentId"
                    :total-storage-size="totalStorageSize"
                    @delete="deleteDocument"
                    @edit="openDocumentEdit"
                    @open-details="openDocumentDetails"
                    @open-pdf="viewDocument"
                    @open-source="openSearchSource"
                    @feedback="sendFeedback"
                    @copy-answer="copySearchAnswer"
                    @refresh="loadDocuments"
                    @retry="retryIndexing"
                    @search="searchDocument"
                    @update:question="question = $event"
                    @update:search-mode="changeSearchMode"
                    @update:selected-document-id="updateSelectedDocument"
                    @upload="uploadDialogOpen = true"
                />

                <DataExtractionPage
                    v-else-if="currentSection === 'extraction'"
                    @notify="showMessage"
                />

                <DashboardPage
                    v-else-if="currentSection === 'dashboard'"
                    :are-core-services-configured="areCoreServicesConfigured"
                    :documents="documents"
                    :indexed-documents-count="indexedDocumentsCount"
                    :is-admin="isAdmin"
                    :model-name="modelName"
                    :processing-documents-count="processingDocumentsCount"
                    :total-storage-size="totalStorageSize"
                    :search-quality="searchQuality"
                    @navigate="navigate"
                />

                <ProfilePage
                    v-else-if="currentSection === 'settings'"
                    :user="props.user"
                    @notify="showMessage"
                    @user-updated="emit('userUpdated', $event)"
                />

                <EmployeesPage
                    v-else-if="currentSection === 'employees'"
                    @notify="showMessage"
                />

                <SwaggerPage v-else-if="currentSection === 'api-docs'" />
            </v-container>
        </v-main>

        <KnowledgeUploadDialog
            v-model="uploadDialogOpen"
            :is-uploading="isUploading"
            :meta="meta"
            @submit="uploadDocument"
        />

        <KnowledgeDocumentDetailsDialog
            v-model="detailsDialogOpen"
            :can-edit="canUpdateKnowledge"
            :document="selectedDocument"
            :is-admin="isAdmin"
            @edit="selectedDocument && openDocumentEdit(selectedDocument)"
            @open-pdf="openSelectedDocumentPdf"
        />

        <KnowledgeDocumentEditDialog
            v-model="editDialogOpen"
            :document="selectedDocument"
            :is-saving="isUpdatingDocument"
            :meta="meta"
            @save="updateDocument"
        />

        <v-snackbar
            v-model="snackbar.isOpen"
            :color="snackbar.color"
            timeout="4500"
        >
            {{ snackbar.message }}
        </v-snackbar>
    </v-app>
</template>
