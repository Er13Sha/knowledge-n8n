<script setup lang="ts">
import SwaggerUI from 'swagger-ui';
import 'swagger-ui/dist/swagger-ui.css';
import { onMounted, ref } from 'vue';

const swaggerContainer = ref<HTMLDivElement | null>(null);

onMounted(() => {
    if (!swaggerContainer.value) {
        return;
    }

    const csrfToken =
        document.querySelector<HTMLMetaElement>('meta[name="csrf-token"]')
            ?.content ?? '';

    SwaggerUI({
        domNode: swaggerContainer.value,
        url: '/docs/openapi.json',
        deepLinking: true,
        displayRequestDuration: true,
        persistAuthorization: true,
        tryItOutEnabled: true,
        validatorUrl: 'none',
        withCredentials: true,
        requestInterceptor(request) {
            request.credentials = 'same-origin';

            if (csrfToken) {
                request.headers['X-CSRF-TOKEN'] = csrfToken;
            }

            return request;
        },
    });
});
</script>

<template>
    <section class="swagger-page">
        <div ref="swaggerContainer" />
    </section>
</template>

<style scoped>
.swagger-page {
    min-height: calc(100vh - 112px);
    overflow: hidden;
    border: 1px solid #e3e5e8;
    border-radius: 10px;
    background: #fff;
}

.swagger-page :deep(.swagger-ui .topbar) {
    display: none;
}

.swagger-page :deep(.swagger-ui .wrapper) {
    max-width: none;
    padding: 24px;
}
</style>
