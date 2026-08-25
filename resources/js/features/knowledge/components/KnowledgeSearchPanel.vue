<script setup lang="ts">
import { highlightedExcerpt } from '@/features/knowledge/presentation';
import type {
    KnowledgeDocument,
    SearchResult,
} from '@/features/knowledge/types';

defineProps<{
    searchableDocuments: KnowledgeDocument[];
    searchScopeOptions: Array<{
        id: number | null;
        original_name: string;
    }>;
    selectedDocumentId: number | null;
    question: string;
    isSearching: boolean;
    searchResult: SearchResult | null;
}>();

const emit = defineEmits<{
    'update:selectedDocumentId': [value: number | null];
    'update:question': [value: string];
    search: [];
}>();
</script>

<template>
    <v-sheet class="admin-panel assistant-panel" border>
        <div class="panel-header assistant-header">
            <div class="assistant-title">
                <div class="assistant-mark">
                    <v-icon icon="mdi-brain" size="19" />
                </div>
                <div>
                    <h2>Поиск по документам</h2>
                    <span>Точные слова и смысловые фрагменты</span>
                </div>
            </div>
        </div>

        <div class="assistant-body">
            <v-select
                :model-value="selectedDocumentId"
                :disabled="searchableDocuments.length === 0"
                hide-details
                item-title="original_name"
                item-value="id"
                :items="searchScopeOptions"
                label="Область поиска"
                variant="outlined"
                @update:model-value="emit('update:selectedDocumentId', $event)"
            />

            <v-textarea
                :model-value="question"
                auto-grow
                hide-details
                label="Предложения"
                placeholder="Например: какие требования указаны в документе?"
                rows="4"
                variant="outlined"
                @update:model-value="emit('update:question', $event)"
                @keydown.ctrl.enter="emit('search')"
            />

            <v-btn
                block
                color="primary"
                :disabled="searchableDocuments.length === 0 || !question.trim()"
                :loading="isSearching"
                prepend-icon="mdi-magnify"
                size="large"
                @click="emit('search')"
            >
                Найти в документах
            </v-btn>

            <div
                v-if="searchableDocuments.length === 0"
                class="assistant-notice"
            >
                <v-icon icon="mdi-information-outline" />
                <span>Поиск станет доступен после индексации документа.</span>
            </div>

            <v-skeleton-loader
                v-if="isSearching"
                class="answer-skeleton"
                type="paragraph, paragraph"
            />

            <section v-else-if="searchResult" class="answer-block">
                <div class="matches-heading">
                    <div>
                        <v-icon color="primary" icon="mdi-text-search" />
                        <h3>Совпадения в документах</h3>
                    </div>
                    <span>{{ searchResult.matches.length }}</span>
                </div>

                <div v-if="searchResult.matches.length" class="matches-list">
                    <article
                        v-for="(match, index) in searchResult.matches"
                        :key="`${match.document_id}-${match.page}-${index}`"
                        class="match-item"
                    >
                        <div class="match-meta">
                            <span class="match-document">
                                <v-icon icon="mdi-file-pdf-box" size="15" />
                                {{ match.document_name }}
                            </span>
                            <span>стр. {{ match.page }}</span>
                        </div>
                        <p>
                            <template
                                v-for="(part, partIndex) in highlightedExcerpt(
                                    match,
                                )"
                                :key="partIndex"
                            >
                                <mark v-if="part.highlighted">{{
                                    part.text
                                }}</mark>
                                <template v-else>{{ part.text }}</template>
                            </template>
                        </p>
                        <v-chip
                            v-if="match.match_type === 'semantic'"
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
</template>
