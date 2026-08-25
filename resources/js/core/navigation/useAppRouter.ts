import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { resolveAppRoute } from '@/core/navigation/routes';
import type { AppRoutePath } from '@/core/navigation/routes';

export function useAppRouter() {
    const currentPath = ref(window.location.pathname);
    const currentRoute = computed(() => resolveAppRoute(currentPath.value));

    function navigate(path: AppRoutePath): void {
        if (window.location.pathname !== path) {
            window.history.pushState({}, '', path);
        }

        currentPath.value = path;
    }

    function handlePopState(): void {
        currentPath.value = window.location.pathname;
    }

    onMounted(() => window.addEventListener('popstate', handlePopState));
    onBeforeUnmount(() =>
        window.removeEventListener('popstate', handlePopState),
    );

    return { currentPath, currentRoute, navigate };
}
