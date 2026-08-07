<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import { apiRequest } from '@/api';
import LoginView from '@/auth/LoginView.vue';
import KnowledgeApp from '@/knowledge/KnowledgeApp.vue';

export type AuthUser = {
    id: number;
    name: string;
    email: string;
    email_verified_at: string | null;
};

const user = ref<AuthUser | null>(null);
const isLoading = ref(true);

function showLogin(): void {
    user.value = null;

    if (window.location.pathname !== '/login') {
        window.history.replaceState({}, '', '/login');
    }
}

function handleAuthenticated(authenticatedUser: AuthUser): void {
    user.value = authenticatedUser;

    if (['/', '/login'].includes(window.location.pathname)) {
        window.history.replaceState({}, '', '/dashboard');
    }
}

function updateUser(updatedUser: AuthUser): void {
    user.value = updatedUser;
}

async function loadUser(): Promise<void> {
    try {
        const response = await apiRequest<{ data: AuthUser }>('/api/auth/user');
        handleAuthenticated(response.data);
    } catch {
        showLogin();
    } finally {
        isLoading.value = false;
    }
}

async function logout(): Promise<void> {
    await apiRequest('/api/auth/logout', { method: 'POST' });
    showLogin();
}

onMounted(() => {
    window.addEventListener('auth:unauthorized', showLogin);
    void loadUser();
});

onBeforeUnmount(() => {
    window.removeEventListener('auth:unauthorized', showLogin);
});
</script>

<template>
    <v-app v-if="isLoading" class="app-loading">
        <v-progress-circular color="primary" indeterminate size="36" />
    </v-app>
    <LoginView v-else-if="!user" @authenticated="handleAuthenticated" />
    <KnowledgeApp
        v-else
        :user="user"
        @logout="logout"
        @user-updated="updateUser"
    />
</template>

<style>
.app-loading {
    display: grid;
    min-height: 100vh;
    place-items: center;
    background: #f4f5f7;
}
</style>
