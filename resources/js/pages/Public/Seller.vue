<script setup>
import { computed, onBeforeUnmount, ref } from 'vue';
import { Head } from '@inertiajs/vue3';
import PublicFooter from '../../components/public/PublicFooter.vue';
import TripCard from '../../components/public/TripCard.vue';
import { initials, shareOrCopy } from '../../lib/publicPage.js';
import { t } from '../../lib/text.js';

/**
 * Seller page rakyt.id/<slug> (F-002 section B, mockups 1a and 3c). Live from
 * onboarding step 1; without published trips it shows the empty state (Q3).
 */
const props = defineProps({
    title: { type: String, required: true },
    organization: { type: Object, required: true },
    trips: { type: Array, required: true },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
    urls: { type: Object, required: true },
});

// Two ink-outline buttons, drawn with box-shadow insets, not borders (docs/design
// README → Shape & outlines). They are not equal width: Chat WhatsApp fills the row
// with a 1.5px ink outline (primary); Share is content-width with a lighter 1px outline.
const buttonBase = 'inline-flex min-h-12 items-center justify-center gap-2 px-4 font-heading text-sm font-semibold text-ink';
const chatOutline = `${buttonBase} flex-1 shadow-[inset_0_0_0_1.5px_#211710]`;
const shareOutline = `${buttonBase} shadow-[inset_0_0_0_1px_rgba(33,23,16,0.25)]`;
const toast = ref(null);
const sellerInitials = computed(() => initials(props.organization.name));
let timer = null;

function showToast(message) {
    toast.value = message;
    clearTimeout(timer);
    timer = setTimeout(() => (toast.value = null), 2000);
}

function copyText(text) {
    if (navigator.clipboard?.writeText) {
        return navigator.clipboard.writeText(text);
    }

    // Older Android browsers and plain-HTTP pages have no async clipboard.
    const field = document.createElement('textarea');
    field.value = text;
    field.setAttribute('readonly', '');
    field.style.position = 'fixed';
    field.style.top = '-100vh';
    document.body.appendChild(field);
    field.select();
    const copied = document.execCommand('copy');
    field.remove();

    return copied ? Promise.resolve() : Promise.reject(new Error('copy failed'));
}

async function share() {
    const result = await shareOrCopy(
        {
            title: props.organization.name,
            text: t(props.copy, 'seller.share_text', { name: props.organization.name }),
            url: props.organization.share_url,
        },
        { share: navigator.share ? (data) => navigator.share(data) : undefined, copy: copyText },
    );

    if (result === 'copied') {
        showToast(t(props.copy, 'seller.copied'));
    } else if (result === 'failed') {
        showToast(t(props.copy, 'seller.copy_failed', { url: props.organization.share_url }));
    }
}

onBeforeUnmount(() => clearTimeout(timer));
</script>

<template>
    <Head :title="title" />
    <main class="mx-auto max-w-md px-4 pb-8 pt-6">
        <header>
            <div class="flex items-center gap-3">
                <img
                    v-if="organization.logo_url"
                    :src="organization.logo_url"
                    :alt="t(copy, 'seller.logo_alt', { name: organization.name })"
                    width="64"
                    height="64"
                    class="h-16 w-16 flex-none bg-surface object-cover"
                >
                <div
                    v-else
                    class="flex h-16 w-16 flex-none items-center justify-center bg-sogan font-heading text-xl font-semibold text-surface"
                    aria-hidden="true"
                >
                    {{ sellerInitials }}
                </div>
                <div class="min-w-0">
                    <h1 class="font-heading text-xl font-semibold leading-tight text-ink [overflow-wrap:anywhere]">
                        {{ organization.name }}
                    </h1>
                    <p class="mt-0.5 text-sm text-secondary [overflow-wrap:anywhere]">
                        {{ organization.city }}
                    </p>
                </div>
            </div>
            <p
                v-if="organization.bio"
                class="mt-3 whitespace-pre-line text-sm text-ink [overflow-wrap:anywhere]"
            >
                {{ organization.bio }}
            </p>
            <div class="mt-4 flex gap-3">
                <a
                    v-if="organization.whatsapp_url"
                    :href="organization.whatsapp_url"
                    target="_blank"
                    rel="noopener noreferrer"
                    :class="chatOutline"
                >
                    {{ t(copy, 'seller.chat_whatsapp') }}
                </a>
                <button
                    type="button"
                    :class="shareOutline"
                    @click="share"
                >
                    <span aria-hidden="true">↗</span>
                    {{ t(copy, 'seller.share') }}
                </button>
            </div>
        </header>

        <section class="mt-8">
            <h2 class="section-header">
                {{ t(copy, 'seller.trips_title') }}
                <span
                    v-if="trips.length"
                    class="font-sans text-xs font-normal normal-case tracking-normal text-secondary"
                > · {{ t(copy, 'seller.trips_count', { n: trips.length }) }}</span>
            </h2>
            <ul
                v-if="trips.length"
                class="mt-3 space-y-4"
            >
                <li
                    v-for="(trip, index) in trips"
                    :key="trip.slug"
                >
                    <TripCard
                        :trip="trip"
                        :copy="copy"
                        :calendar="calendar"
                        :today="context.today"
                        :eager="index < 2"
                    />
                </li>
            </ul>
            <div
                v-else
                class="mt-3 rounded-xl border border-ink/10 bg-surface p-4"
            >
                <p class="text-sm text-ink">
                    {{ t(copy, 'seller.empty') }}
                </p>
                <p class="mt-1 text-sm text-secondary">
                    {{ t(copy, 'seller.empty_hint') }}
                </p>
            </div>
        </section>

        <PublicFooter
            :label="t(copy, 'footer')"
            :href="urls.home"
        />
    </main>
    <div
        class="pointer-events-none fixed inset-x-0 bottom-6 z-50 flex justify-center px-4"
        aria-live="polite"
    >
        <p
            v-if="toast"
            class="rounded-full bg-ink px-4 py-2 text-sm text-surface shadow-lg [overflow-wrap:anywhere]"
            role="status"
        >
            {{ toast }}
        </p>
    </div>
</template>
