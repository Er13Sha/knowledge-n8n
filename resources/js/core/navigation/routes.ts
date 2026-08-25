export type AppSection = 'dashboard' | 'knowledge' | 'settings';

export type AppRoutePath = '/dashboard' | '/knowledge' | '/settings/profile';

export type AppRoute = {
    path: AppRoutePath;
    section: AppSection;
    title: string;
};

export const appRoutes: readonly AppRoute[] = [
    { path: '/dashboard', section: 'dashboard', title: 'Обзор' },
    { path: '/knowledge', section: 'knowledge', title: 'База знаний' },
    { path: '/settings/profile', section: 'settings', title: 'Настройки' },
];

export function resolveAppRoute(path: string): AppRoute {
    return (
        appRoutes.find((route) => path === route.path) ??
        (path.startsWith('/settings') ? appRoutes[2] : appRoutes[0])
    );
}
