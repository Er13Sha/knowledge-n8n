<script setup lang="ts">
import { statusColor, statusIcon } from '@/modules/knowledge/presentation';
import type { KnowledgeDocument } from '@/modules/knowledge/types';
import { formatDate } from '@/shared/lib/formatters';

defineProps<{
    allDocumentsCount: number;
    canCreate: boolean;
    canDelete: boolean;
    canUpdate: boolean;
    documents: KnowledgeDocument[];
    filteredDocumentsCount: number;
    hasActiveFilters: boolean;
    selectedDocumentId: number | null;
    isLoadingDocuments: boolean;
    failedDocumentsCount: number;
    page: number;
    pageCount: number;
    pageSize: number;
    pageSizeOptions: number[];
}>();

const emit = defineEmits<{
    upload: [];
    refresh: [];
    openPdf: [document: KnowledgeDocument];
    openDetails: [document: KnowledgeDocument];
    edit: [document: KnowledgeDocument];
    retry: [document: KnowledgeDocument];
    delete: [document: KnowledgeDocument];
    clearFilters: [];
    'update:page': [value: number];
    'update:pageSize': [value: number];
}>();
</script>

<template>
    <v-sheet class="admin-panel documents-panel" border>
        <div class="panel-header">
            <div>
                <h2>Документы</h2>
                <span v-if="hasActiveFilters">
                    {{ filteredDocumentsCount }} из {{ allDocumentsCount }}
                    записей
                </span>
                <span v-else>{{ filteredDocumentsCount }} записей</span>
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
                    <template #activator="{ props: activatorProps }">
                        <v-btn
                            v-bind="activatorProps"
                            :loading="isLoadingDocuments"
                            icon="mdi-refresh"
                            size="small"
                            variant="text"
                            @click="emit('refresh')"
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
            v-if="filteredDocumentsCount === 0 && allDocumentsCount === 0"
            class="empty-state"
        >
            <div class="empty-state__icon">
                <v-icon icon="mdi-file-document-plus-outline" size="30" />
            </div>
            <h3>Документов пока нет</h3>
            <p>Добавьте PDF, чтобы начать работу с базой знаний.</p>
            <v-btn
                v-if="canCreate"
                color="primary"
                prepend-icon="mdi-plus"
                variant="tonal"
                @click="emit('upload')"
            >
                Добавить PDF
            </v-btn>
        </div>

        <div
            v-else-if="filteredDocumentsCount === 0"
            class="empty-state empty-state--filtered"
        >
            <div class="empty-state__icon">
                <v-icon icon="mdi-filter-off-outline" size="30" />
            </div>
            <h3>Ничего не найдено</h3>
            <p>Измените параметры фильтра или сбросьте их.</p>
            <v-btn
                prepend-icon="mdi-filter-off-outline"
                variant="tonal"
                @click="emit('clearFilters')"
            >
                Сбросить фильтры
            </v-btn>
        </div>

        <div v-else class="table-scroll">
            <v-table class="documents-table" density="comfortable">
                <thead>
                    <tr>
                        <th>Документ</th>
                        <th>Отдел</th>
                        <th>Тип</th>
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
                                document.id === selectedDocumentId,
                        }"
                        tabindex="0"
                        :title="`Открыть ${document.original_name}`"
                        @click="emit('openPdf', document)"
                        @keydown.enter="emit('openPdf', document)"
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
                                <div class="document-details">
                                    <strong>
                                        {{
                                            document.title ??
                                            document.original_name
                                        }}
                                    </strong>
                                    <span v-if="document.title">
                                        {{ document.original_name }}
                                    </span>
                                    <span
                                        v-if="document.error_message"
                                        class="document-error"
                                    >
                                        {{ document.error_message }}
                                    </span>
                                    <span v-else-if="!document.title">
                                        ID {{ document.id }}
                                    </span>
                                    <v-chip
                                        class="mobile-document-status"
                                        :color="statusColor(document.status)"
                                        size="x-small"
                                        variant="tonal"
                                    >
                                        {{ document.status_label }}
                                    </v-chip>
                                </div>
                            </div>
                        </td>
                        <td class="table-secondary">
                            {{ document.department_label || '—' }}
                        </td>
                        <td class="table-secondary">
                            {{ document.doc_type_label || '—' }}
                        </td>
                        <td>
                            <v-chip
                                :color="statusColor(document.status)"
                                :prepend-icon="statusIcon(document.status)"
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
                            {{ formatDate(document.created_at) }}
                        </td>
                        <td class="text-right">
                            <v-tooltip text="Открыть PDF">
                                <template
                                    #activator="{ props: activatorProps }"
                                >
                                    <v-btn
                                        v-bind="activatorProps"
                                        icon="mdi-eye-outline"
                                        size="small"
                                        variant="text"
                                        @click.stop="emit('openPdf', document)"
                                    />
                                </template>
                            </v-tooltip>
                            <v-menu location="bottom end">
                                <template
                                    #activator="{ props: activatorProps }"
                                >
                                    <v-btn
                                        v-bind="activatorProps"
                                        icon="mdi-dots-horizontal"
                                        size="small"
                                        variant="text"
                                        @click.stop
                                    />
                                </template>
                                <v-list density="compact">
                                    <v-list-item
                                        v-if="
                                            document.status === 'failed' &&
                                            canUpdate
                                        "
                                        prepend-icon="mdi-refresh"
                                        title="Повторить индексацию"
                                        @click="emit('retry', document)"
                                    />
                                    <v-list-item
                                        v-if="canUpdate"
                                        prepend-icon="mdi-pencil-outline"
                                        title="Редактировать"
                                        @click="emit('edit', document)"
                                    />
                                    <v-list-item
                                        prepend-icon="mdi-eye-outline"
                                        title="Просмотр"
                                        @click="emit('openDetails', document)"
                                    />
                                    <v-list-item
                                        v-if="canDelete"
                                        base-color="error"
                                        prepend-icon="mdi-delete-outline"
                                        title="Удалить"
                                        @click="emit('delete', document)"
                                    />
                                </v-list>
                            </v-menu>
                        </td>
                    </tr>
                </tbody>
            </v-table>
        </div>

        <div v-if="filteredDocumentsCount" class="documents-pagination">
            <span class="documents-pagination__summary">
                Показано {{ (page - 1) * pageSize + 1 }}–{{
                    Math.min(page * pageSize, filteredDocumentsCount)
                }}
                из {{ filteredDocumentsCount }}
            </span>
            <v-pagination
                v-if="pageCount > 1"
                :length="pageCount"
                :model-value="page"
                total-visible="7"
                @update:model-value="emit('update:page', $event)"
            />
            <v-select
                class="page-size-select"
                density="compact"
                hide-details
                :items="pageSizeOptions"
                label="На странице"
                :model-value="pageSize"
                variant="outlined"
                @update:model-value="emit('update:pageSize', $event)"
            />
        </div>
    </v-sheet>
</template>
