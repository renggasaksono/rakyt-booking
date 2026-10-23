<script setup>
import MarketingNav from '../components/marketing/MarketingNav.vue';
import MarketingFooter from '../components/marketing/MarketingFooter.vue';
import ConsentPrompt from '../components/marketing/ConsentPrompt.vue';

/**
 * Shell for the public marketing pages (F-017): sticky nav, the page content in
 * a <main> landmark, the footer, and the GA opt-in prompt. Mirrors the
 * components/public/ pattern. Holds no tenant data.
 */
defineProps({
    nav: { type: Object, required: true },
    footer: { type: Object, required: true },
    consent: { type: Object, required: true },
    cta: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const emit = defineEmits(['cta']);
</script>

<template>
    <div class="flex min-h-screen flex-col bg-gading text-ink">
        <MarketingNav
            :nav="nav"
            :cta="cta"
            :login-url="urls.login"
            @cta="emit('cta', $event)"
        />
        <main
            id="atas"
            class="flex-1"
        >
            <slot />
        </main>
        <MarketingFooter
            :footer="footer"
            :urls="urls"
        />
        <ConsentPrompt
            :consent="consent"
            :privacy-url="urls.privacy"
        />
    </div>
</template>
