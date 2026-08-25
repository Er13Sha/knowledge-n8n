<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import type {
    KnowledgeDocumentFormData,
    KnowledgeMeta,
} from '@/features/knowledge/types';

const props = defineProps<{
    modelValue: boolean;
    meta: KnowledgeMeta | null;
    isUploading: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    submit: [file: File, metadata: KnowledgeDocumentFormData];
}>();

const selectedFile = ref<File | File[] | null>(null);
const departmentId = ref('');
const documentTitle = ref('');
const documentType = ref('');
const approvedAt = ref('');

const canUpload = computed(() =>
    Boolean(
        fileToUpload() &&
        departmentId.value &&
        documentTitle.value.trim() &&
        documentType.value &&
        approvedAt.value,
    ),
);

function fileToUpload(): File | null {
    if (Array.isArray(selectedFile.value)) {
        return selectedFile.value[0] ?? null;
    }

    return selectedFile.value;
}

function submit(): void {
    const file = fileToUpload();

    if (!file) {
        return;
    }

    emit('submit', file, {
        departmentId: departmentId.value,
        title: documentTitle.value.trim(),
        documentType: documentType.value,
        approvedAt: approvedAt.value,
    });
}

function reset(): void {
    selectedFile.value = null;
    departmentId.value = '';
    documentTitle.value = '';
    documentType.value = '';
    approvedAt.value = '';
}

watch(
    () => props.modelValue,
    (isOpen) => {
        if (!isOpen) {
            reset();
        }
    },
);
</script>

<template>
    <v-dialog
        :model-value="modelValue"
        max-width="540"
        @update:model-value="emit('update:modelValue', $event)"
    >
        <v-card class="upload-dialog" rounded="lg">
            <v-card-title class="dialog-title">
                <div>
                    <strong>Добавить документ</strong>
                    <span>PDF до {{ meta?.upload.max_pdf_mb ?? 50 }} МБ</span>
                </div>
                <v-btn
                    icon="mdi-close"
                    size="small"
                    variant="text"
                    @click="emit('update:modelValue', false)"
                />
            </v-card-title>
            <v-divider />
            <v-card-text class="dialog-body">
                <v-select
                    v-model="departmentId"
                    :items="meta?.form.departments ?? []"
                    item-title="title"
                    item-value="value"
                    label="Департамент"
                    required
                    variant="outlined"
                />
                <v-text-field
                    v-model="documentTitle"
                    label="Название документа"
                    required
                    variant="outlined"
                />
                <v-select
                    v-model="documentType"
                    :items="meta?.form.document_types ?? []"
                    item-title="title"
                    item-value="value"
                    label="Тип документа"
                    required
                    variant="outlined"
                />
                <v-text-field
                    v-model="approvedAt"
                    label="Дата одобрения"
                    required
                    type="date"
                    variant="outlined"
                />
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
                <v-btn variant="text" @click="emit('update:modelValue', false)">
                    Отмена
                </v-btn>
                <v-btn
                    color="primary"
                    :disabled="!canUpload"
                    :loading="isUploading"
                    prepend-icon="mdi-upload"
                    @click="submit"
                >
                    Загрузить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
