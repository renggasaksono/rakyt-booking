import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import { pageTitle } from './lib/title.js';

createInertiaApp({
    title: pageTitle,
    // Lazy: a public page loads the shared runtime and its own chunk only (ADR-0024, F-002 AC-33).
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue');
        return pages[`./pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#6E4527',
    },
});
