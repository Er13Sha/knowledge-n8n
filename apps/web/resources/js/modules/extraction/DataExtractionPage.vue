<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { extractionApi } from './api';
import type { DocumentExtraction, ExtractionResult } from './types';
import { useDocumentExtraction } from './useDocumentExtraction';

const emit = defineEmits<{ notify: [message: string, color?: string] }>();
const fileInput = ref<HTMLInputElement | null>(null);
const selectedFile = ref<File | null>(null);
const dragActive = ref(false);
let pollInterval: number | undefined;

const notify = (message: string, color = 'success'): void =>
    emit('notify', message, color);
const {
    extractions,
    selected,
    selectedId,
    isLoading,
    isUploading,
    hasInProgress,
    load,
    upload,
    retry,
    remove,
    refreshSelected,
} = useDocumentExtraction(notify);

const accepted =
    '.pdf,.jpg,.jpeg,.png,.tif,.tiff,.bmp,.doc,.docx,.xls,.xlsx,.csv,.txt';

function chooseFile(file: File | undefined): void {
    if (!file) {
        return;
    }

    selectedFile.value = file;
}

function submit(): void {
    if (!selectedFile.value || isUploading.value) {
        return;
    }

    void upload(selectedFile.value).then((success) => {
        if (success) {
            selectedFile.value = null;
        }
    });
}

function select(item: DocumentExtraction): void {
    selectedId.value = item.id;
}

function result(item: DocumentExtraction | null): ExtractionResult | null {
    return item?.result ?? null;
}

function filledFields(item: DocumentExtraction | null) {
    return item?.result?.fields?.length
        ? item.result.fields
        : (item?.result?.key_values ?? []);
}

function fieldLabel(label: string): string {
    const normalized = label
        .trim()
        .toLocaleLowerCase('ru-RU')
        .replace(/[._-]+/g, ' ')
        .replace(/\s+/g, ' ');

    return (
        {
            иин: 'ИИН',
            'и и н': 'ИИН',
            бин: 'БИН',
            'б и н': 'БИН',
            фио: 'ФИО',
            'ф и о': 'ФИО',
            телефон: 'Телефон',
            тел: 'Телефон',
            email: 'E-mail',
            'e mail': 'E-mail',
            'электронная почта': 'E-mail',
        }[normalized] ?? label.trim()
    );
}

function fieldIcon(label: string): string {
    const normalized = fieldLabel(label).toLocaleLowerCase('ru-RU');

    if (normalized === 'иин') {
        return 'mdi-card-account-details-outline';
    }

    if (normalized === 'бин') {
        return 'mdi-domain';
    }

    if (normalized === 'фио') {
        return 'mdi-account-outline';
    }

    if (normalized.includes('телефон')) {
        return 'mdi-phone-outline';
    }

    if (normalized.includes('mail')) {
        return 'mdi-email-outline';
    }

    if (normalized.includes('адрес')) {
        return 'mdi-map-marker-outline';
    }

    if (normalized.includes('дата')) {
        return 'mdi-calendar-outline';
    }

    return 'mdi-form-textbox';
}

function formatLabel(format: string | null): string {
    return (
        (
            {
                pdf: 'PDF',
                image: 'Изображение',
                doc: 'DOC',
                docx: 'DOCX',
                xls: 'XLS',
                xlsx: 'XLSX',
                csv: 'CSV',
                txt: 'TXT',
            } as Record<string, string>
        )[format ?? ''] ?? 'Определяется'
    );
}

function safeExternalUrl(value: string): string | null {
    const candidate = value.startsWith('www.') ? `https://${value}` : value;

    try {
        const url = new URL(candidate);

        return ['http:', 'https:'].includes(url.protocol) ? url.href : null;
    } catch {
        return null;
    }
}

function encodingLabel(encoding: string): string {
    return (
        {
            'utf-8': 'UTF-8',
            'utf-8-sig': 'UTF-8 с BOM',
            cp1251: 'Windows-1251',
            cp866: 'DOS 866',
            'koi8-r': 'KOI8-R',
            'utf-16-le': 'UTF-16 LE',
            'utf-16-be': 'UTF-16 BE',
            'utf-32-le': 'UTF-32 LE',
            'utf-32-be': 'UTF-32 BE',
        }[encoding] ?? encoding
    );
}

function languageLabel(language: string): string {
    return (
        (
            {
                ru: 'Русский',
                en: 'English',
                mixed: 'RU / EN',
                unknown: 'Не определён',
            } as Record<string, string>
        )[language] ?? language
    );
}
function statusColor(status: DocumentExtraction['status']): string {
    return {
        pending: 'warning',
        processing: 'info',
        completed: 'success',
        failed: 'error',
    }[status];
}
function downloadSource(item: DocumentExtraction): void {
    window.open(
        extractionApi.sourceUrl(item.id),
        '_blank',
        'noopener,noreferrer',
    );
}

function downloadJson(item: DocumentExtraction): void {
    window.open(
        extractionApi.jsonUrl(item.id),
        '_blank',
        'noopener,noreferrer',
    );
}

onMounted(() => {
    void load();
    pollInterval = window.setInterval(() => {
        if (hasInProgress.value) {
            void load();
        } else {
            void refreshSelected();
        }
    }, 5000);
});
onBeforeUnmount(() => window.clearInterval(pollInterval));
</script>

<template>
    <div class="extraction-page">
        <header class="page-heading">
            <div>
                <h1>Извлечение данных</h1>
                <p>
                    Распознайте PDF, сканы, Office-файлы и таблицы в читаемый
                    структурированный вид
                </p>
            </div>
        </header>

        <v-sheet class="admin-panel extraction-upload" border>
            <div class="panel-header">
                <div>
                    <h2>Новый файл</h2>
                    <span>До 20 МБ · один файл за операцию</span>
                </div>
                <v-chip size="small" variant="tonal">PDF · Office · OCR</v-chip>
            </div>
            <div class="extraction-upload-body">
                <div class="extraction-upload-grid">
                    <div
                        class="extraction-dropzone"
                        :class="{ 'extraction-dropzone--active': dragActive }"
                        @click="fileInput?.click()"
                        @dragenter.prevent="dragActive = true"
                        @dragover.prevent="dragActive = true"
                        @dragleave.prevent="dragActive = false"
                        @drop.prevent="
                            dragActive = false;
                            chooseFile($event.dataTransfer?.files[0]);
                        "
                    >
                        <v-icon icon="mdi-cloud-upload-outline" size="38" />
                        <strong>Выберите файл или перетащите его сюда</strong>
                        <span
                            >PDF, JPG/PNG/TIFF, DOC/DOCX, XLS/XLSX, CSV,
                            TXT</span
                        >
                        <input
                            ref="fileInput"
                            class="d-none"
                            type="file"
                            :accept="accepted"
                            @change="
                                chooseFile(
                                    ($event.target as HTMLInputElement)
                                        .files?.[0],
                                )
                            "
                        />
                    </div>
                    <div class="extraction-upload-controls">
                        <div v-if="selectedFile" class="extraction-file-row">
                            <div>
                                <v-icon icon="mdi-file-outline" /><strong>{{
                                    selectedFile.name
                                }}</strong
                                ><span
                                    >{{
                                        (
                                            selectedFile.size /
                                            1024 /
                                            1024
                                        ).toFixed(2)
                                    }}
                                    МБ</span
                                >
                            </div>
                            <v-btn
                                icon="mdi-close"
                                size="small"
                                variant="text"
                                @click="selectedFile = null"
                            />
                        </div>
                        <v-btn
                            class="mt-4"
                            color="primary"
                            :disabled="!selectedFile"
                            :loading="isUploading"
                            prepend-icon="mdi-text-box-search-outline"
                            @click="submit"
                            >Извлечь данные</v-btn
                        >
                    </div>
                </div>
            </div>
        </v-sheet>

        <div class="extraction-layout">
            <v-sheet class="admin-panel extraction-history" border>
                <div class="panel-header">
                    <div>
                        <h2>История</h2>
                        <span>{{ extractions.length }} результатов</span>
                    </div>
                    <v-btn
                        :loading="isLoading"
                        icon="mdi-refresh"
                        size="small"
                        variant="text"
                        @click="load"
                    />
                </div>
                <v-progress-linear
                    v-if="isLoading"
                    color="primary"
                    indeterminate
                />
                <div
                    v-if="!extractions.length && !isLoading"
                    class="empty-state"
                >
                    <div class="empty-state__icon">
                        <v-icon icon="mdi-file-search-outline" size="30" />
                    </div>
                    <h3>Результатов пока нет</h3>
                    <p>Загрузите файл, чтобы начать извлечение.</p>
                </div>
                <v-list v-else class="extraction-list" density="compact">
                    <v-list-item
                        v-for="item in extractions"
                        :key="item.id"
                        :active="item.id === selectedId"
                        rounded="lg"
                        @click="select(item)"
                    >
                        <template #prepend
                            ><v-icon
                                :color="statusColor(item.status)"
                                :icon="
                                    item.status === 'completed'
                                        ? 'mdi-file-check-outline'
                                        : 'mdi-file-outline'
                                "
                        /></template>
                        <v-list-item-title class="text-truncate">{{
                            item.original_name
                        }}</v-list-item-title>
                        <v-list-item-subtitle
                            >{{ formatLabel(item.detected_format) }} ·
                            {{ item.human_size }}</v-list-item-subtitle
                        >
                        <template #append
                            ><v-chip
                                :color="statusColor(item.status)"
                                size="x-small"
                                variant="tonal"
                                >{{ item.status_label }}</v-chip
                            ></template
                        >
                    </v-list-item>
                </v-list>
            </v-sheet>

            <v-sheet class="admin-panel extraction-result" border>
                <template v-if="selected">
                    <div class="panel-header">
                        <div>
                            <h2>
                                {{
                                    selected.result?.title ||
                                    selected.original_name
                                }}
                            </h2>
                            <span
                                >{{ formatLabel(selected.detected_format) }} ·
                                {{ selected.human_size }}</span
                            >
                        </div>
                        <div class="panel-actions">
                            <v-btn
                                icon="mdi-file-download-outline"
                                size="small"
                                variant="text"
                                title="Скачать исходный файл"
                                @click="downloadSource(selected)"
                            /><v-btn
                                v-if="selected.result"
                                icon="mdi-code-json"
                                size="small"
                                variant="text"
                                title="Скачать JSON"
                                @click="downloadJson(selected)"
                            /><v-btn
                                icon="mdi-delete-outline"
                                size="small"
                                variant="text"
                                title="Удалить"
                                @click="remove(selected)"
                            />
                        </div>
                    </div>
                    <div class="extraction-result-body">
                        <v-progress-linear
                            v-if="
                                ['pending', 'processing'].includes(
                                    selected.status,
                                )
                            "
                            :model-value="selected.progress"
                            color="info"
                            height="6"
                            rounded
                            ><template #default
                                >{{ selected.progress }}%</template
                            ></v-progress-linear
                        >
                        <v-alert
                            v-if="selected.status === 'failed'"
                            class="mb-4"
                            color="error"
                            variant="tonal"
                            ><div>
                                {{
                                    selected.error_message ||
                                    'Не удалось обработать файл.'
                                }}
                            </div>
                            <v-btn
                                class="mt-2"
                                size="small"
                                variant="outlined"
                                @click="retry(selected)"
                                >Повторить</v-btn
                            ></v-alert
                        >
                        <div
                            v-if="
                                selected.status === 'completed' &&
                                result(selected)
                            "
                            class="extraction-cards"
                        >
                            <div class="extraction-summary">
                                <v-chip
                                    color="primary"
                                    size="small"
                                    variant="tonal"
                                    >{{
                                        result(selected)?.document_type_label
                                    }}</v-chip
                                ><v-chip size="small" variant="tonal">{{
                                    languageLabel(
                                        result(selected)?.language || 'unknown',
                                    )
                                }}</v-chip
                                ><v-chip
                                    v-if="result(selected)?.metadata.encoding"
                                    size="small"
                                    variant="tonal"
                                    >{{
                                        encodingLabel(
                                            result(selected)?.metadata
                                                .encoding || '',
                                        )
                                    }}</v-chip
                                ><v-chip
                                    v-if="result(selected)?.stats.ocr_used"
                                    color="warning"
                                    size="small"
                                    variant="tonal"
                                    >OCR</v-chip
                                ><span
                                    >{{ result(selected)?.stats.words || 0 }}
                                    слов ·
                                    {{
                                        result(selected)?.stats.characters || 0
                                    }}
                                    симв.</span
                                >
                            </div>
                            <v-alert
                                v-if="result(selected)?.snippet"
                                color="primary"
                                variant="tonal"
                                >{{ result(selected)?.snippet }}</v-alert
                            >
                            <div
                                v-if="filledFields(selected).length"
                                class="result-card result-card--fields"
                            >
                                <div class="result-card-heading">
                                    <div>
                                        <h3>Заполненные поля</h3>
                                        <span
                                            >Найдено полей:
                                            {{
                                                filledFields(selected).length
                                            }}</span
                                        >
                                    </div>
                                    <v-icon icon="mdi-form-textbox" size="20" />
                                </div>
                                <div class="extraction-fields-grid">
                                    <div
                                        v-for="field in filledFields(selected)"
                                        :key="`${field.label}-${field.value}`"
                                        class="extraction-field"
                                    >
                                        <div class="extraction-field-label">
                                            <v-icon
                                                :icon="fieldIcon(field.label)"
                                                size="16"
                                            />
                                            <span>{{
                                                fieldLabel(field.label)
                                            }}</span>
                                        </div>
                                        <strong>{{ field.value }}</strong>
                                    </div>
                                </div>
                            </div>
                            <div
                                v-if="
                                    result(selected)?.emails?.length ||
                                    Object.keys(result(selected)?.links || {})
                                        .length ||
                                    result(selected)?.urls?.length
                                "
                                class="result-card"
                            >
                                <h3>E-mail и ссылки</h3>
                                <div class="result-chips">
                                    <v-chip
                                        v-for="email in result(selected)
                                            ?.emails"
                                        :key="email"
                                        prepend-icon="mdi-email-outline"
                                        size="small"
                                        >{{ email }}</v-chip
                                    ><v-chip
                                        v-for="(link, name) in result(selected)
                                            ?.links"
                                        :key="name"
                                        :href="
                                            safeExternalUrl(link) || undefined
                                        "
                                        target="_blank"
                                        size="small"
                                        >{{ name }}</v-chip
                                    ><v-chip
                                        v-for="url in result(selected)?.urls"
                                        :key="url"
                                        :href="
                                            safeExternalUrl(url) || undefined
                                        "
                                        target="_blank"
                                        prepend-icon="mdi-link-variant"
                                        size="small"
                                        >{{ url }}</v-chip
                                    >
                                </div>
                            </div>
                            <div
                                v-if="
                                    result(selected)?.dates?.length ||
                                    result(selected)?.amounts?.length ||
                                    result(selected)?.keywords?.length
                                "
                                class="result-card"
                            >
                                <h3>Факты и ключевые слова</h3>
                                <div class="result-chips">
                                    <v-chip
                                        v-for="value in [
                                            ...(result(selected)?.dates || []),
                                            ...(result(selected)?.amounts ||
                                                []),
                                            ...(result(selected)?.keywords ||
                                                []),
                                        ]"
                                        :key="value"
                                        size="small"
                                        variant="tonal"
                                        >{{ value }}</v-chip
                                    >
                                </div>
                            </div>
                            <div
                                v-if="result(selected)?.resume"
                                class="result-card"
                            >
                                <h3>Данные резюме</h3>
                                <p v-if="result(selected)?.resume?.name">
                                    <strong>{{
                                        result(selected)?.resume?.name
                                    }}</strong>
                                </p>
                                <p
                                    v-if="
                                        result(selected)?.resume?.suggested_role
                                    "
                                >
                                    Рекомендуемая роль:
                                    <strong>{{
                                        result(selected)?.resume?.suggested_role
                                    }}</strong>
                                </p>
                                <p
                                    v-if="
                                        result(selected)?.resume
                                            ?.years_of_experience !== null
                                    "
                                >
                                    Опыт:
                                    {{
                                        result(selected)?.resume
                                            ?.years_of_experience
                                    }}
                                    лет
                                </p>
                                <div class="result-chips">
                                    <v-chip
                                        v-for="skill in result(selected)?.resume
                                            ?.skills"
                                        :key="skill"
                                        size="small"
                                        variant="tonal"
                                        >{{ skill }}</v-chip
                                    >
                                </div>
                            </div>
                            <div
                                v-for="table in result(selected)?.tables"
                                :key="table.sheet"
                                class="result-card"
                            >
                                <h3>{{ table.sheet }}</h3>
                                <v-alert
                                    v-if="table.truncated"
                                    class="mb-2"
                                    density="compact"
                                    type="info"
                                    variant="tonal"
                                    >Показаны не все строки.</v-alert
                                >
                                <div class="table-scroll">
                                    <v-table
                                        class="extraction-data-table"
                                        density="compact"
                                        ><thead>
                                            <tr>
                                                <th
                                                    v-for="(
                                                        column, index
                                                    ) in table.columns"
                                                    :key="index"
                                                >
                                                    {{
                                                        column ||
                                                        `Колонка ${index + 1}`
                                                    }}
                                                </th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <tr
                                                v-for="(
                                                    row, rowIndex
                                                ) in table.rows"
                                                :key="rowIndex"
                                            >
                                                <td
                                                    v-for="(
                                                        cell, cellIndex
                                                    ) in row"
                                                    :key="cellIndex"
                                                >
                                                    {{ cell }}
                                                </td>
                                            </tr>
                                        </tbody></v-table
                                    >
                                </div>
                            </div>
                            <div
                                v-if="
                                    Object.keys(
                                        result(selected)?.metadata || {},
                                    ).length
                                "
                                class="result-card"
                            >
                                <h3>Метаданные</h3>
                                <div
                                    v-for="(value, key) in result(selected)
                                        ?.metadata"
                                    :key="key"
                                    class="result-row"
                                >
                                    <span>{{ key }}</span
                                    ><strong>{{ value }}</strong>
                                </div>
                            </div>
                            <v-expansion-panels v-if="result(selected)?.text"
                                ><v-expansion-panel title="Извлечённый текст"
                                    ><v-expansion-panel-text>
                                        <pre
                                            class="extraction-text">{{ result(selected)?.text }}<span v-if="result(selected)?.text_truncated">\n… текст сокращён</span></pre>
                                    </v-expansion-panel-text></v-expansion-panel
                                ></v-expansion-panels
                            >
                        </div>
                    </div>
                </template>
                <div v-else class="empty-state">
                    <div class="empty-state__icon">
                        <v-icon icon="mdi-text-box-search-outline" size="30" />
                    </div>
                    <h3>Выберите результат</h3>
                    <p>Здесь появятся распознанные данные.</p>
                </div>
            </v-sheet>
        </div>
    </div>
</template>
