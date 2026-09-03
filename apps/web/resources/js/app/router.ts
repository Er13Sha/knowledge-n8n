import { computed, onBeforeUnmount, onMounted, ref } from 'vue';

export type AppSection =
    | 'dashboard'
    | 'knowledge'
    | 'extraction'
    | 'settings'
    | 'employees'
    | 'api-docs';

export type AppRoutePath =
    | '/dashboard'
    | '/knowledge'
    | '/extraction'
    | '/settings/profile'
    | '/settings/employees'
    | '/docs/api';

export type AppRoute = {
    path: AppRoutePath;
    section: AppSection;
    title: string;
};

export const appRoutes: readonly AppRoute[] = [
    { path: '/dashboard', section: 'dashboard', title: 'Обзор' },
    { path: '/knowledge', section: 'knowledge', title: 'База знаний' },
    { path: '/extraction', section: 'extraction', title: 'Извлечение данных' },
    { path: '/settings/profile', section: 'settings', title: 'Настройки' },
    {
        path: '/settings/employees',
        section: 'employees',
        title: 'Сотрудники и доступы',
    },
    { path: '/docs/api', section: 'api-docs', title: 'API / Swagger' },
];

export function resolveAppRoute(path: string): AppRoute {
    return (
        appRoutes.find((route) => path === route.path) ??
        (path === '/settings/employees'
            ? appRoutes[4]
            : path.startsWith('/settings')
              ? appRoutes[3]
              : path === '/extraction'
                ? appRoutes[2]
                : appRoutes[0])
    );
}

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
