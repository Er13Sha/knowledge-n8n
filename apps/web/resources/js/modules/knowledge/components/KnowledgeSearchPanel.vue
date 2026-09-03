<script setup lang="ts">
import { highlightedExcerpt } from '@/modules/knowledge/presentation';
import type {
    KnowledgeDocument,
    SearchMode,
    SearchResult,
    SearchSource,
} from '@/modules/knowledge/types';

defineProps<{
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

const emit = defineEmits<{
    'update:selectedDocumentId': [value: number | null];
    'update:question': [value: string];
    search: [];
    'open-source': [source: SearchSource];
    feedback: [rating: 'positive' | 'negative'];
    'copy-answer': [];
    'update:searchMode': [value: SearchMode];
}>();

function confidenceLabel(confidence: 'high' | 'medium' | 'low'): string {
    return {
        high: 'Высокая уверенность',
        medium: 'Средняя уверенность',
        low: 'Низкая уверенность',
    }[confidence];
}

function confidenceColor(confidence: 'high' | 'medium' | 'low'): string {
    return { high: 'success', medium: 'warning', low: 'error' }[confidence];
}

function answerStatusLabel(
    status: 'grounded' | 'insufficient_evidence' | 'citation_error',
): string {
    return {
        grounded: 'Ответ подтверждён источниками',
        insufficient_evidence: 'Недостаточно данных в документах',
        citation_error: 'Ответ не удалось подтвердить цитатами',
    }[status];
}
</script>

<template>
    <v-sheet class="admin-panel assistant-panel" border>
        <div class="panel-header assistant-header">
            <div class="assistant-title">
                <div class="assistant-mark">
                    <v-icon icon="mdi-brain" size="19" />
                </div>
                <div>
                    <h2>Поиск и RAG-чат</h2>
                    <span>Ответы основаны только на доступных документах</span>
                </div>
            </div>
        </div>

        <div class="assistant-body">
            <v-btn-toggle
                :model-value="searchMode"
                class="search-mode-toggle"
                color="primary"
                divided
                mandatory
                variant="outlined"
                @update:model-value="emit('update:searchMode', $event)"
            >
                <v-btn value="rag" prepend-icon="mdi-forum-outline">
                    RAG-чат
                </v-btn>
                <v-btn value="fulltext" prepend-icon="mdi-text-search">
                    Полнотекстовый
                </v-btn>
            </v-btn-toggle>

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
                :label="searchMode === 'rag' ? 'Вопрос' : 'Поисковый запрос'"
                :placeholder="
                    searchMode === 'rag'
                        ? 'Например: какие требования указаны в документе?'
                        : 'Введите слова или точную фразу'
                "
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
                :prepend-icon="
                    searchMode === 'rag' ? 'mdi-send-outline' : 'mdi-magnify'
                "
                size="large"
                @click="emit('search')"
            >
                {{ searchMode === 'rag' ? 'Отправить вопрос' : 'Найти' }}
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
                <template v-if="searchResult.mode === 'rag'">
                    <div class="answer-heading">
                        <v-icon color="primary" icon="mdi-robot-outline" />
                        <h3>Ответ</h3>
                    </div>
                    <p class="answer-text">{{ searchResult.answer }}</p>

                    <div
                        v-if="searchResult.quality"
                        class="answer-quality"
                        :class="`answer-quality--${searchResult.quality.confidence}`"
                    >
                        <v-icon
                            :color="
                                confidenceColor(searchResult.quality.confidence)
                            "
                            icon="mdi-shield-check-outline"
                        />
                        <div>
                            <strong>{{
                                confidenceLabel(searchResult.quality.confidence)
                            }}</strong>
                            <span>{{
                                answerStatusLabel(
                                    searchResult.quality.answer_status,
                                )
                            }}</span>
                        </div>
                    </div>
                    <v-alert
                        v-if="
                            searchResult.quality &&
                            searchResult.quality.answer_status !== 'grounded'
                        "
                        class="mt-3"
                        density="compact"
                        type="warning"
                        variant="tonal"
                    >
                        Используйте найденные источники для самостоятельной
                        проверки ответа.
                    </v-alert>
                    <div class="answer-actions">
                        <v-btn
                            prepend-icon="mdi-content-copy"
                            size="small"
                            variant="text"
                            @click="emit('copy-answer')"
                        >
                            Скопировать ответ
                        </v-btn>
                        <v-btn
                            v-if="searchResult.interaction_id"
                            :color="
                                feedbackRating === 'positive'
                                    ? 'success'
                                    : undefined
                            "
                            :disabled="isSubmittingFeedback"
                            icon="mdi-thumb-up-outline"
                            size="small"
                            :variant="
                                feedbackRating === 'positive' ? 'tonal' : 'text'
                            "
                            aria-label="Полезный ответ"
                            @click="emit('feedback', 'positive')"
                        />
                        <v-btn
                            v-if="searchResult.interaction_id"
                            :color="
                                feedbackRating === 'negative'
                                    ? 'error'
                                    : undefined
                            "
                            :disabled="isSubmittingFeedback"
                            icon="mdi-thumb-down-outline"
                            size="small"
                            :variant="
                                feedbackRating === 'negative' ? 'tonal' : 'text'
                            "
                            aria-label="Неполезный ответ"
                            @click="emit('feedback', 'negative')"
                        />
                    </div>

                    <template v-if="searchResult.sources.length">
                        <div class="sources-heading">
                            Источники
                            <span>{{ searchResult.sources.length }}</span>
                        </div>
                        <div class="sources-list">
                            <button
                                v-for="source in searchResult.sources"
                                :key="`${source.document_id}-${source.page}-${source.number}`"
                                class="source-item"
                                type="button"
                                @click="emit('open-source', source)"
                            >
                                <div class="match-meta">
                                    <span class="match-document">
                                        <strong>[{{ source.number }}]</strong>
                                        <v-icon
                                            icon="mdi-file-pdf-box"
                                            size="15"
                                        />
                                        {{ source.document_name }}
                                    </span>
                                    <span>стр. {{ source.page }}</span>
                                </div>
                                <p>{{ source.excerpt }}</p>
                            </button>
                        </div>
                    </template>
                </template>

                <div class="matches-heading">
                    <div>
                        <v-icon color="primary" icon="mdi-text-search" />
                        <h3>
                            {{
                                searchResult.mode === 'rag'
                                    ? 'Использованные фрагменты'
                                    : 'Полнотекстовые совпадения'
                            }}
                        </h3>
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
                            v-else-if="match.match_type === 'hybrid'"
                            color="warning"
                            size="x-small"
                            variant="tonal"
                        >
                            Гибридный поиск
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
