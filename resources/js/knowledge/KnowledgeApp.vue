<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { useDisplay } from 'vuetify';
import { apiRequest } from '@/api';
import type { AuthUser } from '@/App.vue';

const props = defineProps<{
    user: AuthUser;
}>();

const emit = defineEmits<{
    logout: [];
    userUpdated: [user: AuthUser];
}>();

type KnowledgeDocument = {
    id: number;
    original_name: string;
    size: number;
    human_size: string;
    status: 'pending' | 'processing' | 'indexed' | 'failed';
    status_label: string;
    is_searchable: boolean;
    indexed_at: string | null;
    error_message: string | null;
    created_at: string | null;
};

type KnowledgeMeta = {
    upload: {
        max_pdf_mb: number;
    };
    services: {
        n8n_index_configured: boolean;
        n8n_search_configured: boolean;
        ollama_url: string;
        ollama_model: string;
    };
};

type SearchMatch = {
    document_id: number;
    document_name: string;
    page: number;
    excerpt: string;
    matched_terms: string[];
    phrase_matched: boolean;
    match_type: 'exact' | 'semantic';
};

type SearchResult = {
    matches: SearchMatch[];
};

type HighlightedTextPart = {
    text: string;
    highlighted: boolean;
};

const { mdAndUp } = useDisplay();

const documents = ref<KnowledgeDocument[]>([]);
const meta = ref<KnowledgeMeta | null>(null);
const navigationOpen = ref(mdAndUp.value);
const uploadDialogOpen = ref(false);
const selectedDocumentId = ref<number | null>(null);
const selectedFile = ref<File | File[] | null>(null);
const question = ref('');
const searchResult = ref<SearchResult | null>(null);
const isLoadingDocuments = ref(false);
const isUploading = ref(false);
const isSearching = ref(false);
const snackbar = ref({
    isOpen: false,
    message: '',
    color: 'success',
});
const currentPath = ref(window.location.pathname);
const profileName = ref(props.user.name);
const profileEmail = ref(props.user.email);
const currentPassword = ref('');
const newPassword = ref('');
const passwordConfirmation = ref('');
const isSavingProfile = ref(false);
const isSavingPassword = ref(false);

const currentSection = computed<'dashboard' | 'knowledge' | 'settings'>(() => {
    if (currentPath.value === '/knowledge') {
        return 'knowledge';
    }

    if (currentPath.value.startsWith('/settings')) {
        return 'settings';
    }

    return 'dashboard';
});

const pageTitle = computed(
    () =>
        ({
            dashboard: 'Обзор',
            knowledge: 'База знаний',
            settings: 'Настройки',
        })[currentSection.value],
);

const userInitial = computed(
    () => props.user.name.trim().charAt(0).toLocaleUpperCase('ru-RU') || 'A',
);

const searchableDocuments = computed(() =>
    documents.value.filter((document) => document.is_searchable),
);

const searchScopeOptions = computed(() => [
    {
        id: null,
        original_name: 'Все документы',
    },
    ...searchableDocuments.value,
]);

const indexedDocumentsCount = computed(() => searchableDocuments.value.length);

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
        documents.value.reduce((total, document) => total + document.size, 0),
    ),
);

const areCoreServicesConfigured = computed(
    () =>
        meta.value?.services.n8n_index_configured === true &&
        meta.value?.services.n8n_search_configured === true,
);

const hasDocumentsInProgress = computed(() =>
    documents.value.some((document) =>
        ['pending', 'processing'].includes(document.status),
    ),
);

function showMessage(message: string, color = 'success'): void {
    snackbar.value = {
        isOpen: true,
        message,
        color,
    };
}

function highlightedExcerpt(match: SearchMatch): HighlightedTextPart[] {
    const terms = [...match.matched_terms]
        .sort((left, right) => right.length - left.length)
        .map((term) => term.replace(/[.*+?^${}()|[\]\\]/g, '\\$&'));

    if (terms.length === 0) {
        return [{ text: match.excerpt, highlighted: false }];
    }

    const expression = new RegExp(`(${terms.join('|')})`, 'giu');
    const highlightExpression = new RegExp(`^(${terms.join('|')})$`, 'iu');

    return match.excerpt
        .split(expression)
        .filter(Boolean)
        .map((part) => ({
            text: part,
            highlighted: highlightExpression.test(part),
        }));
}

async function loadDocuments(): Promise<void> {
    isLoadingDocuments.value = true;

    try {
        const response = await apiRequest<{
            data: KnowledgeDocument[];
            meta: KnowledgeMeta;
        }>('/api/knowledge/documents');

        documents.value = response.data;
        meta.value = response.meta;
    } catch (error) {
        showMessage((error as Error).message, 'error');
    } finally {
        isLoadingDocuments.value = false;
    }
}

function fileToUpload(): File | null {
    if (Array.isArray(selectedFile.value)) {
        return selectedFile.value[0] ?? null;
    }

    return selectedFile.value;
}

async function uploadDocument(): Promise<void> {
    const file = fileToUpload();

    if (!file) {
        showMessage('Выберите PDF-файл.', 'warning');

        return;
    }

    const formData = new FormData();
    formData.append('document', file);
    isUploading.value = true;

    try {
        await apiRequest('/api/knowledge/documents', {
            method: 'POST',
            body: formData,
        });

        selectedFile.value = null;
        uploadDialogOpen.value = false;
        showMessage('PDF загружен и отправлен на индексацию.');
        await loadDocuments();
    } catch (error) {
        showMessage((error as Error).message, 'error');
    } finally {
        isUploading.value = false;
    }
}

async function deleteDocument(document: KnowledgeDocument): Promise<void> {
    if (!window.confirm(`Удалить документ «${document.original_name}»?`)) {
        return;
    }

    try {
        await apiRequest(`/api/knowledge/documents/${document.id}`, {
            method: 'DELETE',
        });

        if (selectedDocumentId.value === document.id) {
            selectedDocumentId.value = null;
            searchResult.value = null;
        }

        showMessage('Документ удалён.');
        await loadDocuments();
    } catch (error) {
        showMessage((error as Error).message, 'error');
    }
}

async function retryIndexing(document: KnowledgeDocument): Promise<void> {
    try {
        await apiRequest(
            `/api/knowledge/documents/${document.id}/retry-indexing`,
            {
                method: 'POST',
            },
        );

        showMessage('Документ повторно отправлен на индексацию.');
        await loadDocuments();
    } catch (error) {
        showMessage((error as Error).message, 'error');
    }
}

async function searchDocument(): Promise<void> {
    if (!question.value.trim()) {
        showMessage('Введите вопрос.', 'warning');

        return;
    }

    isSearching.value = true;
    searchResult.value = null;

    try {
        const response = await apiRequest<{ data: SearchResult }>(
            '/api/knowledge/search',
            {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify({
                    document_id: selectedDocumentId.value,
                    question: question.value,
                }),
            },
        );

        searchResult.value = response.data;
    } catch (error) {
        showMessage((error as Error).message, 'error');
    } finally {
        isSearching.value = false;
    }
}

async function saveProfile(): Promise<void> {
    isSavingProfile.value = true;

    try {
        const response = await apiRequest<{ data: AuthUser }>(
            '/api/settings/profile',
            {
                method: 'PATCH',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({
                    name: profileName.value,
                    email: profileEmail.value,
                }),
            },
        );

        emit('userUpdated', response.data);
        showMessage('Профиль обновлён.');
    } catch (error) {
        showMessage((error as Error).message, 'error');
    } finally {
        isSavingProfile.value = false;
    }
}

async function savePassword(): Promise<void> {
    isSavingPassword.value = true;

    try {
        await apiRequest('/api/settings/password', {
            method: 'PUT',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                current_password: currentPassword.value,
                password: newPassword.value,
                password_confirmation: passwordConfirmation.value,
            }),
        });

        currentPassword.value = '';
        newPassword.value = '';
        passwordConfirmation.value = '';
        showMessage('Пароль обновлён.');
    } catch (error) {
        showMessage((error as Error).message, 'error');
    } finally {
        isSavingPassword.value = false;
    }
}

function navigate(
    path: '/dashboard' | '/knowledge' | '/settings/profile',
): void {
    if (window.location.pathname !== path) {
        window.history.pushState({}, '', path);
    }

    currentPath.value = path;
    navigationOpen.value = mdAndUp.value;
}

function handlePopState(): void {
    currentPath.value = window.location.pathname;
}

function statusColor(status: KnowledgeDocument['status']): string {
    return (
        {
            pending: 'warning',
            processing: 'info',
            indexed: 'success',
            failed: 'error',
        } satisfies Record<KnowledgeDocument['status'], string>
    )[status];
}

function statusIcon(status: KnowledgeDocument['status']): string {
    return (
        {
            pending: 'mdi-clock-outline',
            processing: 'mdi-progress-clock',
            indexed: 'mdi-check-circle-outline',
            failed: 'mdi-alert-circle-outline',
        } satisfies Record<KnowledgeDocument['status'], string>
    )[status];
}

function formatFileSize(bytes: number): string {
    if (bytes === 0) {
        return '0 Б';
    }

    const units = ['Б', 'КБ', 'МБ', 'ГБ'];
    const unitIndex = Math.min(
        Math.floor(Math.log(bytes) / Math.log(1024)),
        units.length - 1,
    );
    const value = bytes / 1024 ** unitIndex;

    return `${value.toLocaleString('ru-RU', {
        maximumFractionDigits: unitIndex === 0 ? 0 : 1,
    })} ${units[unitIndex]}`;
}

function formatDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(value));
}

function viewDocument(document: KnowledgeDocument): void {
    window.open(
        `/api/knowledge/documents/${document.id}`,
        '_blank',
        'noopener,noreferrer',
    );
}

let refreshInterval: number | undefined;

watch(mdAndUp, (isDesktop) => {
    navigationOpen.value = isDesktop;
});

onMounted(async () => {
    window.addEventListener('popstate', handlePopState);
    await loadDocuments();

    refreshInterval = window.setInterval(() => {
        if (hasDocumentsInProgress.value) {
            void loadDocuments();
        }
    }, 5000);
});

onBeforeUnmount(() => {
    window.removeEventListener('popstate', handlePopState);
    window.clearInterval(refreshInterval);
});
</script>

<template>
    <v-app>
        <v-navigation-drawer
            v-model="navigationOpen"
            class="admin-sidebar"
            color="#181a1f"
            :permanent="mdAndUp"
            width="252"
        >
            <div class="sidebar-brand">
                <div class="brand-mark">
                    <v-icon icon="mdi-layers-triple-outline" size="22" />
                </div>
                <div class="min-w-0">
                    <div class="brand-name">Knowledge</div>
                    <div class="brand-caption">Control center</div>
                </div>
            </div>

            <v-list class="sidebar-nav" density="compact" nav>
                <v-list-subheader>УПРАВЛЕНИЕ</v-list-subheader>
                <v-list-item
                    :active="currentSection === 'dashboard'"
                    color="white"
                    prepend-icon="mdi-view-dashboard-outline"
                    title="Обзор"
                    @click="navigate('/dashboard')"
                />
                <v-list-item
                    :active="currentSection === 'knowledge'"
                    color="white"
                    prepend-icon="mdi-database-outline"
                    title="База знаний"
                    @click="navigate('/knowledge')"
                />
                <v-list-item
                    :active="currentSection === 'settings'"
                    color="white"
                    prepend-icon="mdi-cog-outline"
                    title="Настройки"
                    @click="navigate('/settings/profile')"
                />
            </v-list>

            <template #append>
                <div class="service-summary">
                    <div class="service-summary__header">
                        <span>Система</span>
                        <span
                            class="health-dot"
                            :class="{
                                'health-dot--online': areCoreServicesConfigured,
                            }"
                        />
                    </div>
                    <div class="service-summary__row">
                        <span>n8n</span>
                        <span>{{
                            areCoreServicesConfigured ? 'Подключён' : 'Проверка'
                        }}</span>
                    </div>
                    <div class="service-summary__row">
                        <span>Модель</span>
                        <span class="text-truncate">{{
                            meta?.services.ollama_model ?? '—'
                        }}</span>
                    </div>
                </div>
            </template>
        </v-navigation-drawer>

        <v-app-bar class="admin-toolbar" color="white" elevation="0">
            <v-btn
                v-if="!mdAndUp"
                icon="mdi-menu"
                title="Открыть меню"
                variant="text"
                @click="navigationOpen = !navigationOpen"
            />
            <div class="toolbar-path">
                <span>Управление</span>
                <v-icon icon="mdi-chevron-right" size="16" />
                <strong>{{ pageTitle }}</strong>
            </div>
            <v-spacer />
            <v-tooltip text="Обновить данные">
                <template #activator="{ props }">
                    <v-btn
                        v-bind="props"
                        :loading="isLoadingDocuments"
                        icon="mdi-refresh"
                        size="small"
                        variant="text"
                        @click="loadDocuments"
                    />
                </template>
            </v-tooltip>
            <v-divider class="toolbar-divider" vertical />
            <v-avatar class="admin-avatar" size="32">{{
                userInitial
            }}</v-avatar>
            <div v-if="mdAndUp" class="admin-identity">
                <strong>{{ user.name }}</strong>
                <span>{{ user.email }}</span>
            </div>
            <v-menu location="bottom end">
                <template #activator="{ props }">
                    <v-btn
                        v-bind="props"
                        icon="mdi-chevron-down"
                        size="small"
                        variant="text"
                    />
                </template>
                <v-list density="compact">
                    <v-list-item
                        prepend-icon="mdi-account-outline"
                        title="Настройки профиля"
                        @click="navigate('/settings/profile')"
                    />
                    <v-list-item
                        prepend-icon="mdi-logout"
                        title="Выйти"
                        @click="emit('logout')"
                    />
                </v-list>
            </v-menu>
        </v-app-bar>

        <v-main class="admin-main">
            <v-container class="admin-container" fluid>
                <template v-if="currentSection === 'knowledge'">
                    <header class="page-heading">
                        <div>
                            <h1>База знаний</h1>
                            <p>
                                Документы, индексация и ответы по внутренним
                                данным
                            </p>
                        </div>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-plus"
                            @click="uploadDialogOpen = true"
                        >
                            Добавить документ
                        </v-btn>
                    </header>

                    <section class="metrics-grid" aria-label="Сводка">
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--neutral">
                                <v-icon
                                    icon="mdi-file-document-multiple-outline"
                                />
                            </div>
                            <div>
                                <span>Всего документов</span>
                                <strong>{{ documents.length }}</strong>
                            </div>
                        </v-sheet>
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--success">
                                <v-icon icon="mdi-check-circle-outline" />
                            </div>
                            <div>
                                <span>Проиндексировано</span>
                                <strong>{{ indexedDocumentsCount }}</strong>
                            </div>
                        </v-sheet>
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--warning">
                                <v-icon icon="mdi-progress-clock" />
                            </div>
                            <div>
                                <span>В обработке</span>
                                <strong>{{ processingDocumentsCount }}</strong>
                            </div>
                        </v-sheet>
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--storage">
                                <v-icon icon="mdi-database-outline" />
                            </div>
                            <div>
                                <span>Объём файлов</span>
                                <strong>{{ totalStorageSize }}</strong>
                            </div>
                        </v-sheet>
                    </section>

                    <div class="workspace-grid">
                        <v-sheet class="admin-panel documents-panel" border>
                            <div class="panel-header">
                                <div>
                                    <h2>Документы</h2>
                                    <span>{{ documents.length }} записей</span>
                                </div>
                                <div class="panel-actions">
                                    <v-chip
                                        v-if="failedDocumentsCount"
                                        color="error"
                                        size="small"
                                        variant="tonal"
                                    >
                                        Ошибок: {{ failedDocumentsCount }}
                                    </v-chip>
                                    <v-tooltip text="Обновить список">
                                        <template #activator="{ props }">
                                            <v-btn
                                                v-bind="props"
                                                :loading="isLoadingDocuments"
                                                icon="mdi-refresh"
                                                size="small"
                                                variant="text"
                                                @click="loadDocuments"
                                            />
                                        </template>
                                    </v-tooltip>
                                </div>
                            </div>

                            <v-progress-linear
                                v-if="isLoadingDocuments"
                                color="primary"
                                indeterminate
                            />

                            <div
                                v-if="documents.length === 0"
                                class="empty-state"
                            >
                                <div class="empty-state__icon">
                                    <v-icon
                                        icon="mdi-file-document-plus-outline"
                                        size="30"
                                    />
                                </div>
                                <h3>Документов пока нет</h3>
                                <p>
                                    Добавьте PDF, чтобы начать работу с базой
                                    знаний.
                                </p>
                                <v-btn
                                    color="primary"
                                    prepend-icon="mdi-plus"
                                    variant="tonal"
                                    @click="uploadDialogOpen = true"
                                >
                                    Добавить PDF
                                </v-btn>
                            </div>

                            <div v-else class="table-scroll">
                                <v-table
                                    class="documents-table"
                                    density="comfortable"
                                >
                                    <thead>
                                        <tr>
                                            <th>Документ</th>
                                            <th>Статус</th>
                                            <th>Размер</th>
                                            <th>Добавлен</th>
                                            <th aria-label="Действия" />
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <tr
                                            v-for="document in documents"
                                            :key="document.id"
                                            :class="{
                                                'document-row--active':
                                                    document.id ===
                                                    selectedDocumentId,
                                            }"
                                            tabindex="0"
                                            :title="`Открыть ${document.original_name}`"
                                            @click="viewDocument(document)"
                                            @keydown.enter="
                                                viewDocument(document)
                                            "
                                        >
                                            <td>
                                                <div class="document-cell">
                                                    <div class="document-icon">
                                                        <v-icon
                                                            color="#c43d3d"
                                                            icon="mdi-file-pdf-box"
                                                            size="22"
                                                        />
                                                    </div>
                                                    <div
                                                        class="document-details"
                                                    >
                                                        <strong>{{
                                                            document.original_name
                                                        }}</strong>
                                                        <span
                                                            v-if="
                                                                document.error_message
                                                            "
                                                            class="document-error"
                                                        >
                                                            {{
                                                                document.error_message
                                                            }}
                                                        </span>
                                                        <span v-else
                                                            >ID
                                                            {{
                                                                document.id
                                                            }}</span
                                                        >
                                                        <v-chip
                                                            class="mobile-document-status"
                                                            :color="
                                                                statusColor(
                                                                    document.status,
                                                                )
                                                            "
                                                            size="x-small"
                                                            variant="tonal"
                                                        >
                                                            {{
                                                                document.status_label
                                                            }}
                                                        </v-chip>
                                                    </div>
                                                </div>
                                            </td>
                                            <td>
                                                <v-chip
                                                    :color="
                                                        statusColor(
                                                            document.status,
                                                        )
                                                    "
                                                    :prepend-icon="
                                                        statusIcon(
                                                            document.status,
                                                        )
                                                    "
                                                    size="small"
                                                    variant="tonal"
                                                >
                                                    {{ document.status_label }}
                                                </v-chip>
                                            </td>
                                            <td class="table-secondary">
                                                {{ document.human_size }}
                                            </td>
                                            <td class="table-secondary">
                                                {{
                                                    formatDate(
                                                        document.created_at,
                                                    )
                                                }}
                                            </td>
                                            <td class="text-right">
                                                <v-tooltip text="Открыть PDF">
                                                    <template
                                                        #activator="{ props }"
                                                    >
                                                        <v-btn
                                                            v-bind="props"
                                                            icon="mdi-eye-outline"
                                                            size="small"
                                                            variant="text"
                                                            @click.stop="
                                                                viewDocument(
                                                                    document,
                                                                )
                                                            "
                                                        />
                                                    </template>
                                                </v-tooltip>
                                                <v-menu location="bottom end">
                                                    <template
                                                        #activator="{ props }"
                                                    >
                                                        <v-btn
                                                            v-bind="props"
                                                            icon="mdi-dots-horizontal"
                                                            size="small"
                                                            variant="text"
                                                            @click.stop
                                                        />
                                                    </template>
                                                    <v-list density="compact">
                                                        <v-list-item
                                                            v-if="
                                                                document.status ===
                                                                'failed'
                                                            "
                                                            prepend-icon="mdi-refresh"
                                                            title="Повторить индексацию"
                                                            @click="
                                                                retryIndexing(
                                                                    document,
                                                                )
                                                            "
                                                        />
                                                        <v-list-item
                                                            base-color="error"
                                                            prepend-icon="mdi-delete-outline"
                                                            title="Удалить"
                                                            @click="
                                                                deleteDocument(
                                                                    document,
                                                                )
                                                            "
                                                        />
                                                    </v-list>
                                                </v-menu>
                                            </td>
                                        </tr>
                                    </tbody>
                                </v-table>
                            </div>
                        </v-sheet>

                        <v-sheet class="admin-panel assistant-panel" border>
                            <div class="panel-header assistant-header">
                                <div class="assistant-title">
                                    <div class="assistant-mark">
                                        <v-icon icon="mdi-brain" size="19" />
                                    </div>
                                    <div>
                                        <h2>Поиск по документам</h2>
                                        <span
                                            >Точные слова и смысловые
                                            фрагменты</span
                                        >
                                    </div>
                                </div>
                            </div>

                            <div class="assistant-body">
                                <v-select
                                    v-model="selectedDocumentId"
                                    :disabled="searchableDocuments.length === 0"
                                    hide-details
                                    item-title="original_name"
                                    item-value="id"
                                    :items="searchScopeOptions"
                                    label="Область поиска"
                                    variant="outlined"
                                    @update:model-value="searchResult = null"
                                />

                                <v-textarea
                                    v-model="question"
                                    auto-grow
                                    hide-details
                                    label="Вопрос"
                                    placeholder="Например: какие требования указаны в документе?"
                                    rows="4"
                                    variant="outlined"
                                    @keydown.ctrl.enter="searchDocument"
                                />

                                <v-btn
                                    block
                                    color="primary"
                                    :disabled="
                                        searchableDocuments.length === 0 ||
                                        !question.trim()
                                    "
                                    :loading="isSearching"
                                    prepend-icon="mdi-magnify"
                                    size="large"
                                    @click="searchDocument"
                                >
                                    Найти в документах
                                </v-btn>

                                <div
                                    v-if="searchableDocuments.length === 0"
                                    class="assistant-notice"
                                >
                                    <v-icon icon="mdi-information-outline" />
                                    <span
                                        >Поиск станет доступен после индексации
                                        документа.</span
                                    >
                                </div>

                                <v-skeleton-loader
                                    v-if="isSearching"
                                    class="answer-skeleton"
                                    type="paragraph, paragraph"
                                />

                                <section
                                    v-else-if="searchResult"
                                    class="answer-block"
                                >
                                    <div class="matches-heading">
                                        <div>
                                            <v-icon
                                                color="primary"
                                                icon="mdi-text-search"
                                            />
                                            <h3>Совпадения в документах</h3>
                                        </div>
                                        <span>{{
                                            searchResult.matches.length
                                        }}</span>
                                    </div>

                                    <div
                                        v-if="searchResult.matches.length"
                                        class="matches-list"
                                    >
                                        <article
                                            v-for="(
                                                match, index
                                            ) in searchResult.matches"
                                            :key="`${match.document_id}-${match.page}-${index}`"
                                            class="match-item"
                                        >
                                            <div class="match-meta">
                                                <span class="match-document">
                                                    <v-icon
                                                        icon="mdi-file-pdf-box"
                                                        size="15"
                                                    />
                                                    {{ match.document_name }}
                                                </span>
                                                <span
                                                    >стр. {{ match.page }}</span
                                                >
                                            </div>
                                            <p>
                                                <template
                                                    v-for="(
                                                        part, partIndex
                                                    ) in highlightedExcerpt(
                                                        match,
                                                    )"
                                                    :key="partIndex"
                                                >
                                                    <mark
                                                        v-if="part.highlighted"
                                                        >{{ part.text }}</mark
                                                    ><template v-else>{{
                                                        part.text
                                                    }}</template>
                                                </template>
                                            </p>
                                            <v-chip
                                                v-if="
                                                    match.match_type ===
                                                    'semantic'
                                                "
                                                color="primary"
                                                size="x-small"
                                                variant="tonal"
                                            >
                                                Подобрано AI
                                            </v-chip>
                                            <v-chip
                                                v-else
                                                color="success"
                                                size="x-small"
                                                variant="tonal"
                                            >
                                                Точное слово
                                            </v-chip>
                                        </article>
                                    </div>
                                    <div v-else class="matches-empty">
                                        Подходящих фрагментов не найдено.
                                    </div>
                                </section>
                            </div>
                        </v-sheet>
                    </div>
                </template>

                <template v-else-if="currentSection === 'dashboard'">
                    <header class="page-heading">
                        <div>
                            <h1>Обзор</h1>
                            <p>Состояние базы знаний и сервисов</p>
                        </div>
                        <v-btn
                            color="primary"
                            prepend-icon="mdi-database-outline"
                            @click="navigate('/knowledge')"
                        >
                            Открыть базу знаний
                        </v-btn>
                    </header>

                    <section class="metrics-grid" aria-label="Сводка">
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--neutral">
                                <v-icon
                                    icon="mdi-file-document-multiple-outline"
                                />
                            </div>
                            <div>
                                <span>Всего документов</span>
                                <strong>{{ documents.length }}</strong>
                            </div>
                        </v-sheet>
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--success">
                                <v-icon icon="mdi-check-circle-outline" />
                            </div>
                            <div>
                                <span>Готово к поиску</span>
                                <strong>{{ indexedDocumentsCount }}</strong>
                            </div>
                        </v-sheet>
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--warning">
                                <v-icon icon="mdi-progress-clock" />
                            </div>
                            <div>
                                <span>В обработке</span>
                                <strong>{{ processingDocumentsCount }}</strong>
                            </div>
                        </v-sheet>
                        <v-sheet class="metric-tile" border>
                            <div class="metric-icon metric-icon--storage">
                                <v-icon icon="mdi-database-outline" />
                            </div>
                            <div>
                                <span>Объём файлов</span>
                                <strong>{{ totalStorageSize }}</strong>
                            </div>
                        </v-sheet>
                    </section>

                    <div class="dashboard-grid">
                        <v-sheet class="admin-panel" border>
                            <div class="panel-header">
                                <div>
                                    <h2>Последние документы</h2>
                                    <span>Актуальные записи базы знаний</span>
                                </div>
                            </div>
                            <v-list class="recent-documents" lines="two">
                                <v-list-item
                                    v-for="document in documents.slice(0, 6)"
                                    :key="document.id"
                                    prepend-icon="mdi-file-pdf-box"
                                    :subtitle="formatDate(document.created_at)"
                                    :title="document.original_name"
                                >
                                    <template #append>
                                        <v-chip
                                            :color="
                                                statusColor(document.status)
                                            "
                                            size="small"
                                            variant="tonal"
                                        >
                                            {{ document.status_label }}
                                        </v-chip>
                                    </template>
                                </v-list-item>
                                <div
                                    v-if="documents.length === 0"
                                    class="dashboard-empty"
                                >
                                    Документов пока нет
                                </div>
                            </v-list>
                        </v-sheet>

                        <v-sheet class="admin-panel system-panel" border>
                            <div class="panel-header">
                                <div>
                                    <h2>Сервисы</h2>
                                    <span>Текущая конфигурация</span>
                                </div>
                            </div>
                            <div class="system-list">
                                <div>
                                    <span>API базы знаний</span>
                                    <v-chip
                                        color="success"
                                        size="small"
                                        variant="tonal"
                                    >
                                        Доступен
                                    </v-chip>
                                </div>
                                <div>
                                    <span>n8n workflows</span>
                                    <v-chip
                                        :color="
                                            areCoreServicesConfigured
                                                ? 'success'
                                                : 'warning'
                                        "
                                        size="small"
                                        variant="tonal"
                                    >
                                        {{
                                            areCoreServicesConfigured
                                                ? 'Подключены'
                                                : 'Проверка'
                                        }}
                                    </v-chip>
                                </div>
                                <div>
                                    <span>Модель</span>
                                    <strong>{{
                                        meta?.services.ollama_model ?? '—'
                                    }}</strong>
                                </div>
                            </div>
                        </v-sheet>
                    </div>
                </template>

                <template v-else>
                    <header class="page-heading">
                        <div>
                            <h1>Настройки</h1>
                            <p>Профиль и безопасность учетной записи</p>
                        </div>
                    </header>

                    <div class="settings-grid">
                        <v-sheet class="admin-panel settings-panel" border>
                            <div class="panel-header">
                                <div>
                                    <h2>Профиль</h2>
                                    <span>Данные администратора</span>
                                </div>
                            </div>
                            <form
                                class="settings-form"
                                @submit.prevent="saveProfile"
                            >
                                <v-text-field
                                    v-model="profileName"
                                    autocomplete="name"
                                    label="Имя"
                                    required
                                    variant="outlined"
                                />
                                <v-text-field
                                    v-model="profileEmail"
                                    autocomplete="email"
                                    label="Email"
                                    required
                                    type="email"
                                    variant="outlined"
                                />
                                <v-btn
                                    color="primary"
                                    :loading="isSavingProfile"
                                    type="submit"
                                >
                                    Сохранить профиль
                                </v-btn>
                            </form>
                        </v-sheet>

                        <v-sheet class="admin-panel settings-panel" border>
                            <div class="panel-header">
                                <div>
                                    <h2>Пароль</h2>
                                    <span>Обновление данных для входа</span>
                                </div>
                            </div>
                            <form
                                class="settings-form"
                                @submit.prevent="savePassword"
                            >
                                <v-text-field
                                    v-model="currentPassword"
                                    autocomplete="current-password"
                                    label="Текущий пароль"
                                    required
                                    type="password"
                                    variant="outlined"
                                />
                                <v-text-field
                                    v-model="newPassword"
                                    autocomplete="new-password"
                                    label="Новый пароль"
                                    required
                                    type="password"
                                    variant="outlined"
                                />
                                <v-text-field
                                    v-model="passwordConfirmation"
                                    autocomplete="new-password"
                                    label="Повторите новый пароль"
                                    required
                                    type="password"
                                    variant="outlined"
                                />
                                <v-btn
                                    color="primary"
                                    :loading="isSavingPassword"
                                    type="submit"
                                >
                                    Обновить пароль
                                </v-btn>
                            </form>
                        </v-sheet>
                    </div>
                </template>
            </v-container>
        </v-main>

        <v-dialog v-model="uploadDialogOpen" max-width="540">
            <v-card class="upload-dialog" rounded="lg">
                <v-card-title class="dialog-title">
                    <div>
                        <strong>Добавить документ</strong>
                        <span
                            >PDF до {{ meta?.upload.max_pdf_mb ?? 50 }} МБ</span
                        >
                    </div>
                    <v-btn
                        icon="mdi-close"
                        size="small"
                        variant="text"
                        @click="uploadDialogOpen = false"
                    />
                </v-card-title>
                <v-divider />
                <v-card-text class="dialog-body">
                    <v-file-input
                        v-model="selectedFile"
                        accept="application/pdf"
                        clearable
                        label="PDF-документ"
                        prepend-icon=""
                        prepend-inner-icon="mdi-file-pdf-box"
                        show-size
                        variant="outlined"
                    />
                </v-card-text>
                <v-card-actions class="dialog-actions">
                    <v-btn variant="text" @click="uploadDialogOpen = false">
                        Отмена
                    </v-btn>
                    <v-btn
                        color="primary"
                        :disabled="!fileToUpload()"
                        :loading="isUploading"
                        prepend-icon="mdi-upload"
                        @click="uploadDocument"
                    >
                        Загрузить
                    </v-btn>
                </v-card-actions>
            </v-card>
        </v-dialog>

        <v-snackbar
            v-model="snackbar.isOpen"
            :color="snackbar.color"
            timeout="4500"
        >
            {{ snackbar.message }}
        </v-snackbar>
    </v-app>
</template>

<style>
html,
body {
    margin: 0;
    min-height: 100vh;
    background: #f4f5f7;
}

.v-application {
    min-height: 100vh;
    color: #20232a;
}

.admin-sidebar {
    border-right: 0 !important;
}

.sidebar-brand {
    display: flex;
    min-height: 72px;
    align-items: center;
    gap: 12px;
    padding: 0 20px;
    border-bottom: 1px solid rgb(255 255 255 / 8%);
    color: #fff;
}

.brand-mark {
    display: grid;
    width: 36px;
    height: 36px;
    flex: 0 0 36px;
    place-items: center;
    border-radius: 7px;
    background: #2f806c;
}

.brand-name {
    overflow: hidden;
    font-size: 15px;
    font-weight: 700;
    line-height: 20px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.brand-caption {
    color: #9297a2;
    font-size: 11px;
    line-height: 16px;
}

.sidebar-nav {
    padding: 14px 12px;
}

.sidebar-nav .v-list-subheader {
    min-height: 34px;
    padding-inline: 10px !important;
    color: #747a86;
    font-size: 10px;
    font-weight: 700;
    letter-spacing: 0;
}

.sidebar-nav .v-list-item {
    min-height: 42px;
    margin-bottom: 3px;
    border-radius: 6px;
    color: #aeb3bd;
}

.sidebar-nav .v-list-item--active {
    background: #2b2e35;
    color: #fff;
}

.sidebar-nav .v-list-item--active::before {
    position: absolute;
    top: 9px;
    bottom: 9px;
    left: 0;
    width: 3px;
    border-radius: 0 3px 3px 0;
    background: #45a68d;
    content: '';
}

.service-summary {
    margin: 12px;
    padding: 14px;
    border: 1px solid rgb(255 255 255 / 8%);
    border-radius: 7px;
    background: #202228;
    color: #b5bac4;
    font-size: 11px;
}

.service-summary__header,
.service-summary__row {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
}

.service-summary__header {
    margin-bottom: 10px;
    color: #fff;
    font-size: 12px;
    font-weight: 600;
}

.service-summary__row + .service-summary__row {
    margin-top: 7px;
}

.service-summary__row span:last-child {
    max-width: 116px;
    color: #d4d7dd;
}

.health-dot {
    width: 8px;
    height: 8px;
    border-radius: 50%;
    background: #d29c44;
}

.health-dot--online {
    background: #51b88f;
    box-shadow: 0 0 0 3px rgb(81 184 143 / 14%);
}

.admin-toolbar {
    border-bottom: 1px solid #e6e8eb !important;
}

.admin-toolbar .v-toolbar__content {
    padding: 0 20px;
}

.toolbar-path {
    display: flex;
    align-items: center;
    gap: 5px;
    color: #858a94;
    font-size: 12px;
}

.toolbar-path strong {
    color: #31343b;
    font-weight: 600;
}

.toolbar-divider {
    height: 26px !important;
    margin: 0 14px;
}

.admin-avatar {
    background: #e7f3ef;
    color: #246b59;
    font-size: 12px;
    font-weight: 700;
}

.admin-identity {
    display: flex;
    flex-direction: column;
    margin-left: 9px;
}

.admin-identity strong {
    font-size: 12px;
    font-weight: 600;
    line-height: 17px;
}

.admin-identity span {
    color: #8a8f98;
    font-size: 10px;
    line-height: 14px;
}

.admin-main {
    background: #f4f5f7;
}

.admin-container {
    max-width: 1700px;
    padding: 18px 20px 30px;
}

.page-heading {
    display: flex;
    align-items: flex-start;
    justify-content: space-between;
    gap: 20px;
    margin-bottom: 22px;
}

.page-heading h1 {
    margin: 0;
    color: #20232a;
    font-size: 24px;
    font-weight: 700;
    line-height: 32px;
}

.page-heading p {
    margin: 4px 0 0;
    color: #777c86;
    font-size: 13px;
}

.metrics-grid {
    display: grid;
    grid-template-columns: repeat(4, minmax(0, 1fr));
    gap: 14px;
    margin-bottom: 18px;
}

.metric-tile {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 13px;
    min-height: 84px;
    padding: 15px 17px;
    border-color: #e2e4e8 !important;
    border-radius: 7px !important;
    background: #fff !important;
}

.metric-icon {
    display: grid;
    width: 42px;
    height: 42px;
    flex: 0 0 42px;
    place-items: center;
    border-radius: 7px;
}

.metric-icon--neutral {
    background: #eef0f3;
    color: #555b66;
}

.metric-icon--success {
    background: #e7f4ee;
    color: #23745d;
}

.metric-icon--warning {
    background: #fff2dd;
    color: #a76816;
}

.metric-icon--storage {
    background: #e8eff8;
    color: #3c6591;
}

.metric-tile span {
    display: block;
    overflow: hidden;
    color: #7c818b;
    font-size: 11px;
    line-height: 17px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.metric-tile strong {
    display: block;
    color: #25282f;
    font-size: 21px;
    font-weight: 700;
    line-height: 28px;
}

.workspace-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.65fr) minmax(320px, 0.85fr);
    align-items: start;
    gap: 18px;
}

.dashboard-grid {
    display: grid;
    grid-template-columns: minmax(0, 1.45fr) minmax(300px, 0.55fr);
    align-items: start;
    gap: 18px;
}

.settings-grid {
    display: grid;
    grid-template-columns: repeat(2, minmax(0, 1fr));
    gap: 18px;
    align-items: start;
}

.settings-form {
    display: flex;
    flex-direction: column;
    padding: 22px 20px 24px;
}

.settings-form .v-btn {
    align-self: flex-start;
}

.recent-documents {
    padding: 4px 10px 10px;
}

.recent-documents .v-list-item {
    border-bottom: 1px solid #eceef0;
}

.recent-documents .v-list-item:last-child {
    border-bottom: 0;
}

.dashboard-empty {
    padding: 36px 18px;
    color: #838892;
    font-size: 12px;
    text-align: center;
}

.system-list {
    display: flex;
    flex-direction: column;
    padding: 8px 18px 18px;
}

.system-list > div {
    display: flex;
    min-height: 52px;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    border-bottom: 1px solid #eceef0;
    color: #555a64;
    font-size: 12px;
}

.system-list > div:last-child {
    border-bottom: 0;
}

.system-list strong {
    max-width: 160px;
    overflow: hidden;
    color: #2f3339;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.admin-panel {
    overflow: hidden;
    border-color: #e1e3e7 !important;
    border-radius: 7px !important;
    background: #fff !important;
}

.panel-header {
    display: flex;
    min-height: 68px;
    align-items: center;
    justify-content: space-between;
    gap: 16px;
    padding: 13px 18px;
    border-bottom: 1px solid #e8e9ec;
}

.panel-header h2 {
    margin: 0;
    color: #292c33;
    font-size: 14px;
    font-weight: 700;
    line-height: 20px;
}

.panel-header span {
    color: #898e98;
    font-size: 11px;
}

.panel-actions {
    display: flex;
    align-items: center;
    gap: 5px;
}

.table-scroll {
    overflow-x: auto;
}

.documents-table {
    min-width: 760px;
}

.documents-table thead th {
    height: 42px !important;
    border-bottom-color: #e9eaed !important;
    background: #fafafb;
    color: #777c86 !important;
    font-size: 10px !important;
    font-weight: 700 !important;
    text-transform: uppercase;
}

.documents-table tbody tr {
    cursor: pointer;
    transition: background-color 140ms ease;
}

.documents-table tbody tr:hover,
.document-row--active {
    background: #f2f8f6 !important;
}

.document-row--disabled {
    cursor: default !important;
}

.document-cell {
    display: flex;
    min-width: 260px;
    align-items: center;
    gap: 11px;
    padding: 4px 0;
}

.document-icon {
    display: grid;
    width: 34px;
    height: 34px;
    flex: 0 0 34px;
    place-items: center;
    border: 1px solid #ece2e2;
    border-radius: 6px;
    background: #fff7f7;
}

.document-details {
    display: flex;
    min-width: 0;
    flex-direction: column;
}

.document-details strong {
    overflow: hidden;
    max-width: 380px;
    color: #30333a;
    font-size: 12px;
    font-weight: 600;
    line-height: 18px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.document-details span {
    overflow: hidden;
    max-width: 380px;
    color: #969aa3;
    font-size: 10px;
    line-height: 15px;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.document-details .document-error {
    color: #b42318;
}

.mobile-document-status {
    display: none !important;
}

.table-secondary {
    color: #717680;
    font-size: 11px;
    white-space: nowrap;
}

.empty-state {
    display: flex;
    min-height: 360px;
    align-items: center;
    justify-content: center;
    flex-direction: column;
    padding: 30px;
    text-align: center;
}

.empty-state__icon {
    display: grid;
    width: 58px;
    height: 58px;
    margin-bottom: 14px;
    place-items: center;
    border-radius: 8px;
    background: #eef3f1;
    color: #357763;
}

.empty-state h3 {
    margin: 0 0 5px;
    font-size: 14px;
}

.empty-state p {
    margin: 0 0 18px;
    color: #838892;
    font-size: 12px;
}

.assistant-panel {
    position: sticky;
    top: 84px;
}

.assistant-title {
    display: flex;
    align-items: center;
    gap: 10px;
}

.assistant-mark {
    display: grid;
    width: 34px;
    height: 34px;
    place-items: center;
    border-radius: 7px;
    background: #e7f3ef;
    color: #2a755f;
}

.assistant-body {
    display: flex;
    flex-direction: column;
    gap: 15px;
    padding: 18px;
}

.assistant-notice {
    display: flex;
    align-items: flex-start;
    gap: 8px;
    padding: 11px;
    border-radius: 6px;
    background: #fff6e6;
    color: #91611f;
    font-size: 11px;
    line-height: 17px;
}

.answer-skeleton {
    margin: 0 -8px;
}

.answer-block {
    overflow: hidden;
    border: 1px solid #e4e7e9;
    border-radius: 7px;
    background: #fbfcfc;
}

.matches-heading,
.matches-heading > div {
    display: flex;
    align-items: center;
}

.matches-heading {
    justify-content: space-between;
    gap: 12px;
    padding: 14px 15px 10px;
}

.matches-heading > div {
    min-width: 0;
    gap: 8px;
}

.matches-heading h3 {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
}

.matches-heading > span {
    display: grid;
    width: 22px;
    height: 22px;
    flex: 0 0 22px;
    place-items: center;
    border-radius: 50%;
    background: #e7f3ef;
    color: #246b59;
    font-size: 10px;
    font-weight: 700;
}

.matches-list {
    max-height: 520px;
    overflow-y: auto;
    border-top: 1px solid #e7e9eb;
}

.match-item {
    padding: 13px 15px;
    background: #fff;
}

.match-item + .match-item {
    border-top: 1px solid #e7e9eb;
}

.match-meta {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 12px;
    color: #747983;
    font-size: 10px;
}

.match-document {
    display: flex;
    min-width: 0;
    align-items: center;
    gap: 5px;
    overflow: hidden;
    color: #3f444c;
    font-weight: 600;
    text-overflow: ellipsis;
    white-space: nowrap;
}

.match-document .v-icon {
    flex: 0 0 auto;
    color: #c43d3d;
}

.match-item p {
    margin: 8px 0;
    color: #4c5159;
    font-size: 11px;
    line-height: 18px;
}

.match-item mark {
    border-radius: 2px;
    background: #ffdda1;
    color: #34373d;
    font-weight: 700;
}

.matches-empty {
    padding: 4px 15px 15px;
    color: #7b8089;
    font-size: 11px;
}

.answer-heading {
    display: flex;
    align-items: center;
    gap: 8px;
    padding: 14px 15px 0;
}

.answer-heading h3 {
    margin: 0;
    font-size: 12px;
    font-weight: 700;
}

.answer-text {
    margin: 0;
    padding: 11px 15px 16px;
    color: #444951;
    font-size: 12px;
    line-height: 20px;
    white-space: pre-wrap;
}

.sources-heading {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 12px 15px 7px;
    color: #555a64;
    font-size: 11px;
    font-weight: 700;
}

.sources-heading span {
    display: grid;
    width: 20px;
    height: 20px;
    place-items: center;
    border-radius: 50%;
    background: #eceff1;
    color: #656a73;
    font-size: 9px;
}

.sources-list {
    max-height: 280px;
    overflow-y: auto;
    padding: 0 15px 10px;
}

.source-item {
    padding: 9px 0;
}

.source-item + .source-item {
    border-top: 1px solid #e7e9eb;
}

.source-item p {
    display: -webkit-box;
    margin: 6px 0 0;
    overflow: hidden;
    color: #70757e;
    font-size: 10px;
    line-height: 16px;
    -webkit-box-orient: vertical;
    -webkit-line-clamp: 3;
}

.upload-dialog {
    border: 1px solid #e1e3e7;
}

.dialog-title {
    display: flex;
    align-items: center;
    justify-content: space-between;
    padding: 18px 20px !important;
}

.dialog-title div {
    display: flex;
    flex-direction: column;
}

.dialog-title strong {
    font-size: 15px;
}

.dialog-title span {
    color: #858a94;
    font-size: 11px;
}

.dialog-body {
    padding: 24px 20px 8px !important;
}

.dialog-actions {
    gap: 8px;
    justify-content: flex-end;
    padding: 12px 20px 18px !important;
}

@media (max-width: 1100px) {
    .metrics-grid {
        grid-template-columns: repeat(2, minmax(0, 1fr));
    }

    .workspace-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .dashboard-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .settings-grid {
        grid-template-columns: minmax(0, 1fr);
    }

    .assistant-panel {
        position: static;
    }
}

@media (max-width: 700px) {
    .admin-toolbar .v-toolbar__content {
        padding: 0 8px;
    }

    .toolbar-path span,
    .toolbar-path .v-icon,
    .toolbar-divider {
        display: none;
    }

    .admin-container {
        padding: 20px 14px 32px;
    }

    .page-heading {
        align-items: stretch;
        flex-direction: column;
    }

    .page-heading .v-btn {
        align-self: flex-start;
    }

    .metrics-grid {
        grid-template-columns: minmax(0, 1fr);
        gap: 10px;
    }

    .metric-tile {
        min-height: 72px;
    }

    .panel-header {
        padding: 12px 14px;
    }

    .assistant-body {
        padding: 14px;
    }

    .documents-table {
        min-width: 100%;
    }

    .documents-table th:nth-child(2),
    .documents-table th:nth-child(3),
    .documents-table th:nth-child(4),
    .documents-table td:nth-child(2),
    .documents-table td:nth-child(3),
    .documents-table td:nth-child(4) {
        display: none;
    }

    .document-cell {
        min-width: 0;
    }

    .document-details strong,
    .document-details span {
        max-width: 235px;
    }

    .mobile-document-status {
        display: inline-flex !important;
        align-self: flex-start;
        margin-top: 4px;
    }
}
</style>
