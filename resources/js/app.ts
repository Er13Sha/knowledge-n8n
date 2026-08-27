import '@mdi/font/css/materialdesignicons.css';
import 'vuetify/styles';
import { createApp } from 'vue';
import App from '@/app/App.vue';
import { vuetify } from '@/app/vuetify';

createApp(App).use(vuetify).mount('#app');
