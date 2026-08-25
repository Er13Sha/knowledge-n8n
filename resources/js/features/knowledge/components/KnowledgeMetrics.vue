<script setup lang="ts">
defineProps<{
    documentsCount: number;
    indexedDocumentsCount: number;
    processingDocumentsCount: number;
    totalStorageSize: string;
}>();
</script>

<template>
    <section class="metrics-grid" aria-label="Сводка">
        <v-sheet class="metric-tile" border>
            <div class="metric-icon metric-icon--neutral">
                <v-icon icon="mdi-file-document-multiple-outline" />
            </div>
            <div>
                <span>Всего документов</span>
                <strong>{{ documentsCount }}</strong>
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
</template>
