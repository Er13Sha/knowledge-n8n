import { computed, ref } from 'vue';
import { extractionApi } from './api';
import type { DocumentExtraction } from './types';

type Notify = (message: string, color?: string) => void;

function message(error: unknown): string {
    return error instanceof Error
        ? error.message
        : 'Не удалось выполнить запрос.';
}

export function useDocumentExtraction(notify: Notify) {
    const extractions = ref<DocumentExtraction[]>([]);
    const selectedId = ref<number | null>(null);
    const isLoading = ref(false);
    const isUploading = ref(false);
    const hasInProgress = computed(() =>
        extractions.value.some((item) =>
            ['pending', 'processing'].includes(item.status),
        ),
    );
    const selected = computed(
        () =>
            extractions.value.find((item) => item.id === selectedId.value) ??
            extractions.value[0] ??
            null,
    );

    async function load(): Promise<void> {
        isLoading.value = true;

        try {
            const response = await extractionApi.list();
            extractions.value = response.data;

            if (selectedId.value === null && extractions.value[0]) {
                selectedId.value = extractions.value[0].id;
            }
        } catch (error) {
            notify(message(error), 'error');
        } finally {
            isLoading.value = false;
        }
    }

    async function upload(file: File): Promise<boolean> {
        isUploading.value = true;

        try {
            const item = await extractionApi.upload(file);
            extractions.value = [item, ...extractions.value];
            selectedId.value = item.id;
            notify('Файл загружен и поставлен в очередь.');

            return true;
        } catch (error) {
            notify(message(error), 'error');

            return false;
        } finally {
            isUploading.value = false;
        }
    }

    async function retry(item: DocumentExtraction): Promise<void> {
        try {
            const updated = await extractionApi.retry(item.id);
            extractions.value = extractions.value.map((entry) =>
                entry.id === item.id ? updated : entry,
            );
            notify('Файл повторно поставлен в очередь.');
        } catch (error) {
            notify(message(error), 'error');
        }
    }

    async function remove(item: DocumentExtraction): Promise<void> {
        if (!window.confirm(`Удалить результат «${item.original_name}»?`)) {
            return;
        }

        try {
            await extractionApi.remove(item.id);
            extractions.value = extractions.value.filter(
                (entry) => entry.id !== item.id,
            );

            if (selectedId.value === item.id) {
                selectedId.value = extractions.value[0]?.id ?? null;
            }

            notify('Результат удалён.');
        } catch (error) {
            notify(message(error), 'error');
        }
    }

    async function refreshSelected(): Promise<void> {
        if (selectedId.value === null) {
            return;
        }

        try {
            const updated = await extractionApi.show(selectedId.value);
            extractions.value = extractions.value.map((item) =>
                item.id === updated.id ? updated : item,
            );
        } catch (error) {
            notify(message(error), 'error');
        }
    }

    return {
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
    };
}
