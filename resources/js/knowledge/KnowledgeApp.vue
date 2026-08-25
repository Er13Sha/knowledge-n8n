<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import './styles.css';
import { useAppRouter } from '@/core/navigation/useAppRouter';
import KnowledgeDashboardView from '@/features/knowledge/components/KnowledgeDashboardView.vue';
import KnowledgeDocumentDetailsDialog from '@/features/knowledge/components/KnowledgeDocumentDetailsDialog.vue';
import KnowledgeDocumentsView from '@/features/knowledge/components/KnowledgeDocumentsView.vue';
import KnowledgeNavigation from '@/features/knowledge/components/KnowledgeNavigation.vue';
import KnowledgeProfileView from '@/features/knowledge/components/KnowledgeProfileView.vue';
import KnowledgeUploadDialog from '@/features/knowledge/components/KnowledgeUploadDialog.vue';
import type {
    KnowledgeDocument,
    KnowledgeDocumentFormData,
} from '@/features/knowledge/types';
import { useKnowledgeBase } from '@/features/knowledge/useKnowledgeBase';
import type { AuthUser } from '@/shared/types/auth';

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
const selectedDocument = ref<KnowledgeDocument | null>(null);
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
    searchResult,
    searchableDocuments,
    searchScopeOptions,
    selectedDocumentId,
    totalStorageSize,
    uploadDocument: uploadKnowledgeDocument,
} = useKnowledgeBase(showMessage);

const { currentRoute, navigate: navigateTo } = useAppRouter();
const currentSection = computed(() => currentRoute.value.section);
const pageTitle = computed(() => currentRoute.value.title);
const modelName = computed(() => meta.value?.services.ollama_model ?? '');
let refreshInterval: number | undefined;

function showMessage(message: string, color = 'success'): void {
    snackbar.value = {
        isOpen: true,
        message,
        color,
    };
}

function navigate(
    path: '/dashboard' | '/knowledge' | '/settings/profile',
): void {
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

function openDocumentDetails(document: KnowledgeDocument): void {
    selectedDocument.value = document;
    detailsDialogOpen.value = true;
}

function openSelectedDocumentPdf(): void {
    if (selectedDocument.value) {
        viewDocument(selectedDocument.value);
    }
}

function updateSelectedDocument(documentId: number | null): void {
    selectedDocumentId.value = documentId;
    searchResult.value = null;
}

watch(mdAndUp, (isDesktop) => {
    navigationOpen.value = isDesktop;
});

onMounted(async () => {
    await loadDocuments();

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
        <KnowledgeNavigation
            v-model:navigation-open="navigationOpen"
            :are-core-services-configured="areCoreServicesConfigured"
            :current-section="currentSection"
            :is-loading-documents="isLoadingDocuments"
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
                <KnowledgeDocumentsView
                    v-if="currentSection === 'knowledge'"
                    :departments="meta?.form.departments ?? []"
                    :document-types="meta?.form.document_types ?? []"
                    :documents="documents"
                    :failed-documents-count="failedDocumentsCount"
                    :indexed-documents-count="indexedDocumentsCount"
                    :is-loading-documents="isLoadingDocuments"
                    :is-searching="isSearching"
                    :processing-documents-count="processingDocumentsCount"
                    :question="question"
                    :search-result="searchResult"
                    :search-scope-options="searchScopeOptions"
                    :searchable-documents="searchableDocuments"
                    :selected-document-id="selectedDocumentId"
                    :total-storage-size="totalStorageSize"
                    @delete="deleteDocument"
                    @open-details="openDocumentDetails"
                    @open-pdf="viewDocument"
                    @refresh="loadDocuments"
                    @retry="retryIndexing"
                    @search="searchDocument"
                    @update:question="question = $event"
                    @update:selected-document-id="updateSelectedDocument"
                    @upload="uploadDialogOpen = true"
                />

                <KnowledgeDashboardView
                    v-else-if="currentSection === 'dashboard'"
                    :are-core-services-configured="areCoreServicesConfigured"
                    :documents="documents"
                    :indexed-documents-count="indexedDocumentsCount"
                    :model-name="modelName"
                    :processing-documents-count="processingDocumentsCount"
                    :total-storage-size="totalStorageSize"
                    @navigate="navigate"
                />

                <KnowledgeProfileView
                    v-else
                    :user="props.user"
                    @notify="showMessage"
                    @user-updated="emit('userUpdated', $event)"
                />
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
            :document="selectedDocument"
            @open-pdf="openSelectedDocumentPdf"
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
