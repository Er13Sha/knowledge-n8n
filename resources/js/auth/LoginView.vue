<script setup lang="ts">
import { ref } from 'vue';
import { authApi } from '@/features/auth/api';
import type { AuthUser } from '@/shared/types/auth';

const emit = defineEmits<{
    authenticated: [user: AuthUser];
}>();

const email = ref('');
const password = ref('');
const remember = ref(false);
const isSubmitting = ref(false);
const errorMessage = ref('');
const passwordVisible = ref(false);

async function login(): Promise<void> {
    errorMessage.value = '';
    isSubmitting.value = true;

    try {
        emit(
            'authenticated',
            await authApi.login({
                email: email.value,
                password: password.value,
                remember: remember.value,
            }),
        );
    } catch (error) {
        errorMessage.value = (error as Error).message;
    } finally {
        isSubmitting.value = false;
    }
}
</script>

<template>
    <v-app class="login-app">
        <main class="login-layout">
            <section class="login-brand" aria-label="Knowledge System">
                <div class="login-brand__content">
                    <div class="login-logo">
                        <v-icon icon="mdi-layers-triple-outline" size="28" />
                    </div>
                    <div>
                        <strong>Knowledge</strong>
                        <span>Control center</span>
                    </div>
                </div>
                <div class="login-brand__footer">
                    Единое управление документами
                </div>
            </section>

            <section class="login-form-section">
                <form class="login-form" @submit.prevent="login">
                    <header>
                        <span class="login-eyebrow">Панель управления</span>
                        <h1>Вход в систему</h1>
                        <p>Введите данные учетной записи администратора</p>
                    </header>

                    <v-alert
                        v-if="errorMessage"
                        density="compact"
                        type="error"
                        variant="tonal"
                    >
                        {{ errorMessage }}
                    </v-alert>

                    <v-text-field
                        v-model="email"
                        autocomplete="email"
                        label="Email"
                        name="email"
                        prepend-inner-icon="mdi-email-outline"
                        required
                        type="email"
                        variant="outlined"
                    />
                    <v-text-field
                        v-model="password"
                        :append-inner-icon="
                            passwordVisible
                                ? 'mdi-eye-off-outline'
                                : 'mdi-eye-outline'
                        "
                        autocomplete="current-password"
                        label="Пароль"
                        name="password"
                        prepend-inner-icon="mdi-lock-outline"
                        required
                        :type="passwordVisible ? 'text' : 'password'"
                        variant="outlined"
                        @click:append-inner="passwordVisible = !passwordVisible"
                    />
                    <v-checkbox
                        v-model="remember"
                        color="primary"
                        density="compact"
                        hide-details
                        label="Запомнить меня"
                    />
                    <v-btn
                        block
                        color="primary"
                        :loading="isSubmitting"
                        size="large"
                        type="submit"
                    >
                        Войти
                    </v-btn>
                </form>
            </section>
        </main>
    </v-app>
</template>

<style scoped>
.login-app {
    min-height: 100vh;
    background: #f4f5f7;
}

.login-layout {
    display: grid;
    min-height: 100vh;
    grid-template-columns: minmax(280px, 38%) minmax(0, 1fr);
}

.login-brand {
    display: flex;
    flex-direction: column;
    justify-content: space-between;
    padding: 44px;
    background: #181a1f;
    color: #fff;
}

.login-brand__content {
    display: flex;
    align-items: center;
    gap: 14px;
}

.login-logo {
    display: grid;
    width: 48px;
    height: 48px;
    place-items: center;
    border-radius: 8px;
    background: #2f806c;
}

.login-brand__content strong,
.login-brand__content span {
    display: block;
}

.login-brand__content strong {
    font-size: 20px;
}

.login-brand__content span,
.login-brand__footer {
    color: #9da2ac;
    font-size: 12px;
}

.login-form-section {
    display: grid;
    place-items: center;
    padding: 32px;
}

.login-form {
    width: min(100%, 420px);
}

.login-form header {
    margin-bottom: 28px;
}

.login-eyebrow {
    color: #2f806c;
    font-size: 11px;
    font-weight: 700;
    text-transform: uppercase;
}

.login-form h1 {
    margin: 7px 0 4px;
    font-size: 28px;
}

.login-form p {
    margin: 0;
    color: #777c86;
    font-size: 13px;
}

@media (max-width: 700px) {
    .login-layout {
        grid-template-columns: 1fr;
    }

    .login-brand {
        min-height: 92px;
        padding: 20px;
    }

    .login-brand__footer {
        display: none;
    }

    .login-form-section {
        align-items: start;
        padding: 44px 20px;
    }
}
</style>
