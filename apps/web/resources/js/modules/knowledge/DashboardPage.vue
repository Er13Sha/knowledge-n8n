<script setup lang="ts">
import KnowledgeMetrics from '@/modules/knowledge/components/KnowledgeMetrics.vue';
import { statusColor } from '@/modules/knowledge/presentation';
import type {
    KnowledgeDocument,
    KnowledgeSearchQuality,
} from '@/modules/knowledge/types';
import { formatDate } from '@/shared/lib/formatters';

defineProps<{
    documents: KnowledgeDocument[];
    indexedDocumentsCount: number;
    processingDocumentsCount: number;
    isAdmin: boolean;
    totalStorageSize: string;
    areCoreServicesConfigured: boolean;
    modelName: string;
    searchQuality: KnowledgeSearchQuality | null;
}>();

const emit = defineEmits<{
    navigate: [path: '/dashboard' | '/knowledge' | '/settings/profile'];
}>();
</script>

<template>
    <header class="page-heading">
        <div>
            <h1>Обзор</h1>
            <p>Состояние базы знаний и сервисов</p>
        </div>
        <v-btn
            color="primary"
            prepend-icon="mdi-database-outline"
            @click="emit('navigate', '/knowledge')"
        >
            Открыть базу знаний
        </v-btn>
    </header>

    <KnowledgeMetrics
        :documents-count="documents.length"
        :indexed-documents-count="indexedDocumentsCount"
        :is-admin="isAdmin"
        :processing-documents-count="processingDocumentsCount"
        :total-storage-size="totalStorageSize"
    />

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
                            v-if="isAdmin"
                            :color="statusColor(document.status)"
                            size="small"
                            variant="tonal"
                        >
                            {{ document.status_label }}
                        </v-chip>
                    </template>
                </v-list-item>
                <div v-if="documents.length === 0" class="dashboard-empty">
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
                    <v-chip color="success" size="small" variant="tonal">
                        Доступен
                    </v-chip>
                </div>
                <div>
                    <span>n8n workflows</span>
                    <v-chip
                        :color="
                            areCoreServicesConfigured ? 'success' : 'warning'
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
                    <strong>{{ modelName || '—' }}</strong>
                </div>
            </div>
        </v-sheet>
    </div>

    <v-sheet
        v-if="isAdmin && searchQuality"
        class="admin-panel search-quality-panel"
        border
    >
        <div class="panel-header">
            <div>
                <h2>Качество AI-поиска</h2>
                <span>Активные взаимодействия за период хранения</span>
            </div>
        </div>
        <div class="quality-stats">
            <div>
                <strong>{{ searchQuality.total_interactions }}</strong>
                <span>запросов</span>
            </div>
            <div>
                <strong>{{ searchQuality.low_confidence }}</strong>
                <span>низкая уверенность</span>
            </div>
            <div>
                <strong>{{ searchQuality.citation_errors }}</strong>
                <span>ошибок цитирования</span>
            </div>
            <div>
                <strong
                    >{{ searchQuality.positive_rate ?? '—'
                    }}<template v-if="searchQuality.positive_rate !== null"
                        >%</template
                    ></strong
                >
                <span>положительных оценок</span>
            </div>
        </div>
        <div
            v-if="searchQuality.popular_questions.length"
            class="quality-questions"
        >
            <strong>Популярные вопросы</strong>
            <v-list density="compact">
                <v-list-item
                    v-for="item in searchQuality.popular_questions"
                    :key="item.question"
                    :title="item.question"
                    :subtitle="`${item.total} запросов`"
                />
            </v-list>
        </div>
    </v-sheet>
</template>
