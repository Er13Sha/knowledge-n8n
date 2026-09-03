import { createVuetify } from 'vuetify';
import * as components from 'vuetify/components';
import * as directives from 'vuetify/directives';

export const vuetify = createVuetify({
    components,
    directives,
    theme: {
        defaultTheme: 'light',
        themes: {
            light: {
                dark: false,
                colors: {
                    primary: '#1e6b5c',
                    secondary: '#335c67',
                    surface: '#ffffff',
                    background: '#f6f8fa',
                    error: '#b42318',
                    warning: '#b54708',
                    success: '#16794c',
                },
            },
        },
    },
});
