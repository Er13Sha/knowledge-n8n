<script setup lang="ts">
import { ref, watch } from 'vue';
import type {
    KnowledgeDocument,
    KnowledgeDocumentFormData,
    KnowledgeMeta,
} from '@/modules/knowledge/types';

const props = defineProps<{
    modelValue: boolean;
    document: KnowledgeDocument | null;
    meta: KnowledgeMeta | null;
    isSaving: boolean;
}>();

const emit = defineEmits<{
    'update:modelValue': [value: boolean];
    save: [metadata: KnowledgeDocumentFormData];
}>();

const departmentId = ref('');
const title = ref('');
const documentType = ref('');
const approvedAt = ref('');

function syncForm(): void {
    departmentId.value = props.document?.department_id ?? '';
    title.value = props.document?.title ?? '';
    documentType.value = props.document?.doc_type ?? '';
    approvedAt.value = props.document?.approved_at ?? '';
}

function save(): void {
    emit('save', {
        departmentId: departmentId.value,
        title: title.value.trim(),
        documentType: documentType.value,
        approvedAt: approvedAt.value,
    });
}

watch(
    [() => props.modelValue, () => props.document],
    () => {
        if (props.modelValue) {
            syncForm();
        }
    },
    { immediate: true },
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
                    <strong>Редактировать документ</strong>
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
            <v-card-text class="dialog-body">
                <v-select
                    v-model="departmentId"
                    :items="meta?.form.departments ?? []"
                    item-title="title"
                    item-value="value"
                    label="Отдел"
                    required
                    variant="outlined"
                />
                <v-text-field
                    v-model="title"
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
            </v-card-text>
            <v-card-actions class="dialog-actions">
                <v-btn variant="text" @click="emit('update:modelValue', false)">
                    Отмена
                </v-btn>
                <v-btn color="primary" :loading="isSaving" @click="save">
                    Сохранить
                </v-btn>
            </v-card-actions>
        </v-card>
    </v-dialog>
</template>
