import { createApp, h } from 'vue';
import { createInertiaApp, router } from '@inertiajs/vue3';
import { pageTitle } from './lib/title.js';
import { bootAnalytics } from './lib/useAnalytics.js';

createInertiaApp({
    title: pageTitle,
    // Lazy: a public page loads the shared runtime and its own chunk only (ADR-0024, F-002 AC-33).
    resolve: (name) => {
        const pages = import.meta.glob('./pages/**/*.vue');
        return pages[`./pages/${name}.vue`]();
    },
    setup({ el, App, props, plugin }) {
        // Landing sign-up-funnel analytics (F-017): boots once on a funnel page
        // that carries a measurement id, then forwards flashed funnel events.
        bootAnalytics(props.initialPage.props.analytics);
        router.on('navigate', (event) => bootAnalytics(event.detail.page.props.analytics));

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .mount(el);
    },
    progress: {
        color: '#6E4527',
    },
});
