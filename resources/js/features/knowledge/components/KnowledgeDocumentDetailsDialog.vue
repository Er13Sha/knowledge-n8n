<script setup lang="ts">
import type { KnowledgeDocument } from '@/features/knowledge/types';

defineProps<{
    modelValue: boolean;
    document: KnowledgeDocument | null;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    openPdf: [];
}>();

function formatApprovalDate(value: string | null): string {
    if (!value) {
        return '—';
    }

    return new Intl.DateTimeFormat('ru-RU', {
        day: '2-digit',
        month: 'long',
        year: 'numeric',
        timeZone: 'UTC',
    }).format(new Date(`${value}T00:00:00Z`));
}
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="620"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card class="upload-dialog" rounded="lg">
            <v-card-title class="dialog-title">
                <div>
                    <strong>Просмотр документа</strong>
                    <span v-if="document">{{ document.original_name }}</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="emit('update:modelValue', false)"
                />
            </v-card-title>
            <v-divider />
            <v-card-text v-if="document" class="dialog-body">
                <div class="document-info-grid">
                    <div class="document-info-item">
                        <span>Название документа</span>
                        <strong>{{ document.title || '—' }}</strong>
                    </div>
                    <div class="document-info-item">
                        <span>Департамент</span>
                        <strong>{{ document.department_label || '—' }}</strong>
                    </div>
                    <div class="document-info-item">
                        <span>Тип документа</span>
                        <strong>{{ document.doc_type_label || '—' }}</strong>
                    </div>
                    <div class="document-info-item">
                        <span>Дата одобрения</span>
                        <strong>{{
                            formatApprovalDate(document.approved_at)
                        }}</strong>
                    </div>
                    <div class="document-info-item">
                        <span>Пользователь</span>
                        <strong>{{ document.user_name }}</strong>
                    </div>
                    <div class="document-info-item">
                        <span>Прикреплённый файл</span>
                        <strong>{{ document.original_name }}</strong>
                    </div>
                    <div class="document-info-item">
                        <span>Размер</span>
                        <strong>{{ document.human_size }}</strong>
                    </div>
                </div>
                <v-alert
                    v-if="document.error_message"
                    class="mt-4"
                    density="compact"
                    type="error"
                    variant="tonal"
                >
                    {{ document.error_message }}
                </v-alert>
            </v-card-text>
            <v-card-actions class="dialog-actions">
                <v-btn variant="text" @click="emit('update:modelValue', false)">
                    Закрыть
                </v-btn>
                <v-btn
                    color="primary"
                    prepend-icon="mdi-file-pdf-box"
                    @click="emit('openPdf')"
                >
                    Открыть PDF
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
