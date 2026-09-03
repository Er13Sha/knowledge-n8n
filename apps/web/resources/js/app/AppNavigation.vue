<script setup lang="ts">
import type { AppRoutePath } from '@/app/router';
import type { AuthUser } from '@/shared/types/auth';

defineProps<{
    navigationOpen: boolean;
    mdAndUp: boolean;
    currentSection: string;
    pageTitle: string;
    user: AuthUser;
    userInitial: string;
    areCoreServicesConfigured: boolean;
    modelName: string;
    isLoadingDocuments: boolean;
    canManageEmployees: boolean;
}>();

const emit = defineEmits<{
    'update:navigationOpen': [value: boolean];
    navigate: [path: AppRoutePath];
    refresh: [];
    logout: [];
}>();

function navigate(path: AppRoutePath): void {
    emit('navigate', path);
}
</script>

<template>
    <v-navigation-drawer
        :model-value="navigationOpen"
        class="admin-sidebar"
        color="#181a1f"
        :permanent="mdAndUp"
        width="252"
        @update:model-value="emit('update:navigationOpen', $event)"
    >
        <div class="sidebar-brand">
            <div class="brand-mark">
                <v-icon icon="mdi-layers-triple-outline" size="22" />
            </div>
            <div class="min-w-0">
                <div class="brand-name">Knowledge</div>
                <div class="brand-caption">Control center</div>
            </div>
        </div>

        <v-list class="sidebar-nav" density="compact" nav>
            <v-list-subheader>УПРАВЛЕНИЕ</v-list-subheader>
            <v-list-item
                :active="currentSection === 'dashboard'"
                color="white"
                prepend-icon="mdi-view-dashboard-outline"
                title="Обзор"
                @click="navigate('/dashboard')"
            />
            <v-list-item
                :active="currentSection === 'knowledge'"
                color="white"
                prepend-icon="mdi-database-outline"
                title="База знаний"
                @click="navigate('/knowledge')"
            />
            <v-list-item
                :active="currentSection === 'extraction'"
                color="white"
                prepend-icon="mdi-file-search-outline"
                title="Извлечение данных"
                @click="navigate('/extraction')"
            />
            <v-list-item
                v-if="canManageEmployees"
                :active="currentSection === 'employees'"
                color="white"
                prepend-icon="mdi-account-group-outline"
                title="Сотрудники и доступы"
                @click="navigate('/settings/employees')"
            />
            <v-list-item
                v-if="canManageEmployees"
                :active="currentSection === 'api-docs'"
                color="white"
                prepend-icon="mdi-api"
                title="API / Swagger"
                @click="navigate('/docs/api')"
            />
        </v-list>

        <template #append>
            <div class="service-summary">
                <div class="service-summary__header">
                    <span>Система</span>
                    <span
                        class="health-dot"
                        :class="{
                            'health-dot--online': areCoreServicesConfigured,
                        }"
                    />
                </div>
                <div class="service-summary__row">
                    <span>n8n</span>
                    <span>
                        {{
                            areCoreServicesConfigured ? 'Подключён' : 'Проверка'
                        }}
                    </span>
                </div>
                <div class="service-summary__row">
                    <span>Модель</span>
                    <span class="text-truncate">{{ modelName || '—' }}</span>
                </div>
            </div>
        </template>
    </v-navigation-drawer>

    <v-app-bar class="admin-toolbar" color="white" elevation="0">
        <v-btn
            v-if="!mdAndUp"
            icon="mdi-menu"
            title="Открыть меню"
            variant="text"
            @click="emit('update:navigationOpen', !navigationOpen)"
        />
        <div class="toolbar-path">
            <span>Управление</span>
            <v-icon icon="mdi-chevron-right" size="16" />
            <strong>{{ pageTitle }}</strong>
        </div>
        <v-spacer />
        <v-tooltip text="Обновить данные">
            <template #activator="{ props: activatorProps }">
                <v-btn
                    v-bind="activatorProps"
                    :loading="isLoadingDocuments"
                    icon="mdi-refresh"
                    size="small"
                    variant="text"
                    @click="emit('refresh')"
                />
            </template>
        </v-tooltip>
        <v-divider class="toolbar-divider" vertical />
        <v-avatar class="admin-avatar" size="32">{{ userInitial }}</v-avatar>
        <div v-if="mdAndUp" class="admin-identity">
            <strong>{{ user.name }}</strong>
            <span>{{ user.email }}</span>
        </div>
        <v-menu location="bottom end">
            <template #activator="{ props: activatorProps }">
                <v-btn
                    v-bind="activatorProps"
                    icon="mdi-chevron-down"
                    size="small"
                    variant="text"
                />
            </template>
            <v-list density="compact">
                <v-list-item
                    prepend-icon="mdi-account-outline"
                    title="Настройки профиля"
                    @click="navigate('/settings/profile')"
                />
                <v-list-item
                    prepend-icon="mdi-logout"
                    title="Выйти"
                    @click="emit('logout')"
                />
            </v-list>
        </v-menu>
    </v-app-bar>
</template>
