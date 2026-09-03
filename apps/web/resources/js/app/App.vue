<script setup lang="ts">
import { onBeforeUnmount, onMounted, ref } from 'vue';
import AuthenticatedLayout from '@/app/AuthenticatedLayout.vue';
import { authApi } from '@/modules/auth/api';
import LoginPage from '@/modules/auth/LoginPage.vue';
import type { AuthUser } from '@/shared/types/auth';

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
        handleAuthenticated(await authApi.currentUser());
    } catch {
        showLogin();
    } finally {
        isLoading.value = false;
    }
}

async function logout(): Promise<void> {
    await authApi.logout();
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
    <LoginPage v-else-if="!user" @authenticated="handleAuthenticated" />
    <AuthenticatedLayout
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
