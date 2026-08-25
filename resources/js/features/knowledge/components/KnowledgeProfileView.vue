<script setup lang="ts">
import { ref } from 'vue';
import { settingsApi } from '@/features/settings/api';
import type { AuthUser } from '@/shared/types/auth';

const props = defineProps<{
    user: AuthUser;
}>();

const emit = defineEmits<{
    userUpdated: [user: AuthUser];
    notify: [message: string, color?: string];
}>();

const profileName = ref(props.user.name);
const profileEmail = ref(props.user.email);
const currentPassword = ref('');
const newPassword = ref('');
const passwordConfirmation = ref('');
const isSavingProfile = ref(false);
const isSavingPassword = ref(false);

async function saveProfile(): Promise<void> {
    isSavingProfile.value = true;

    try {
        emit(
            'userUpdated',
            await settingsApi.updateProfile(
                profileName.value,
                profileEmail.value,
            ),
        );
        emit('notify', 'Профиль обновлён.');
    } catch (error) {
        emit('notify', (error as Error).message, 'error');
    } finally {
        isSavingProfile.value = false;
    }
}

async function savePassword(): Promise<void> {
    isSavingPassword.value = true;

    try {
        await settingsApi.updatePassword(
            currentPassword.value,
            newPassword.value,
            passwordConfirmation.value,
        );

        currentPassword.value = '';
        newPassword.value = '';
        passwordConfirmation.value = '';
        emit('notify', 'Пароль обновлён.');
    } catch (error) {
        emit('notify', (error as Error).message, 'error');
    } finally {
        isSavingPassword.value = false;
    }
}
</script>

<template>
    <header class="page-heading">
        <div>
            <h1>Настройки</h1>
            <p>Профиль и безопасность учетной записи</p>
        </div>
    </header>

    <div class="settings-grid">
        <v-sheet class="admin-panel settings-panel" border>
            <div class="panel-header">
                <div>
                    <h2>Профиль</h2>
                    <span>Данные администратора</span>
                </div>
            </div>
            <form class="settings-form" @submit.prevent="saveProfile">
                <v-text-field
                    v-model="profileName"
                    autocomplete="name"
                    label="Имя"
                    required
                    variant="outlined"
                />
                <v-text-field
                    v-model="profileEmail"
                    autocomplete="email"
                    label="Email"
                    required
                    type="email"
                    variant="outlined"
                />
                <v-btn color="primary" :loading="isSavingProfile" type="submit">
                    Сохранить профиль
                </v-btn>
            </form>
        </v-sheet>

        <v-sheet class="admin-panel settings-panel" border>
            <div class="panel-header">
                <div>
                    <h2>Пароль</h2>
                    <span>Обновление данных для входа</span>
                </div>
            </div>
            <form class="settings-form" @submit.prevent="savePassword">
                <v-text-field
                    v-model="currentPassword"
                    autocomplete="current-password"
                    label="Текущий пароль"
                    required
                    type="password"
                    variant="outlined"
                />
                <v-text-field
                    v-model="newPassword"
                    autocomplete="new-password"
                    label="Новый пароль"
                    required
                    type="password"
                    variant="outlined"
                />
                <v-text-field
                    v-model="passwordConfirmation"
                    autocomplete="new-password"
                    label="Повторите новый пароль"
                    required
                    type="password"
                    variant="outlined"
                />
                <v-btn
                    color="primary"
                    :loading="isSavingPassword"
                    type="submit"
                >
                    Обновить пароль
                </v-btn>
            </form>
        </v-sheet>
    </div>
</template>
