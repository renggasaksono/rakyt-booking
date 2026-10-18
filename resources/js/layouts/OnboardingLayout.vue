<script setup>
import { Link, router, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashMessages from '../components/FlashMessages.vue';
import VerificationBanner from '../components/VerificationBanner.vue';
import { t } from '../lib/text.js';

/**
 * Guided first trip (mockups 4b–4e). Header: back control, "Langkah n dari 4 ·
 * <nama>" and "±m menit lagi" (C1, C13).
 */
const props = defineProps({
    header: { type: Object, required: true },
    copy: { type: Object, required: true },
    backUrl: { type: String, default: null },
});

const page = usePage();
const account = computed(() => page.props.account);
const progress = computed(() => `${(props.header.step / 4) * 100}%`);

function logout() {
    router.post(account.value.urls.logout);
}
</script>

<template>
    <div class="min-h-screen bg-gading">
        <VerificationBanner />
        <header class="mx-auto max-w-md px-4 pt-3">
            <div class="flex items-center justify-between">
                <Link
                    v-if="backUrl"
                    :href="backUrl"
                    class="-ml-2 inline-flex min-h-11 min-w-11 items-center px-2 text-sm text-sogan underline"
                >
                    ← {{ t(copy, 'back') }}
                </Link>
                <span v-else />
                <button
                    v-if="account"
                    type="button"
                    class="inline-flex min-h-11 items-center px-2 text-sm text-secondary underline"
                    @click="logout"
                >
                    {{ account.copy.logout }}
                </button>
            </div>
            <div class="mt-1 flex items-baseline justify-between gap-3">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'step', { n: header.step, name: header.name }) }}
                </p>
                <p class="shrink-0 text-xs text-secondary">
                    {{ t(copy, 'minutes_left', { m: header.minutes_left }) }}
                </p>
            </div>
            <div
                class="mt-2 h-1.5 overflow-hidden rounded-full bg-ink/10"
                role="presentation"
            >
                <div
                    class="h-full rounded-full bg-sogan"
                    :style="{ width: progress }"
                />
            </div>
        </header>
        <main class="mx-auto max-w-md px-4 pb-40 pt-5">
            <slot />
        </main>
        <slot name="action" />
        <FlashMessages />
    </div>
</template>
