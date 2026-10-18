<script setup>
import { Head, Link } from '@inertiajs/vue3';
import { ref } from 'vue';
import StickyAction from '../../components/StickyAction.vue';
import FlashMessages from '../../components/FlashMessages.vue';
import { formatDayMonth } from '../../lib/calendar.js';
import { formatRupiah } from '../../lib/money.js';
import { copyLink, shareOrCopy } from '../../lib/onboarding.js';
import { t } from '../../lib/text.js';

/**
 * Trip terbit (mockup 4f, F-001 D4–D6): the link to share, straight away.
 */
const props = defineProps({
    copy: { type: Object, required: true },
    elapsed: { type: Object, default: null },
    trip: { type: Object, required: true },
    link: { type: Object, required: true },
    calendar: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const copied = ref(false);

function showCopied(result) {
    copied.value = result === 'copied';
}

async function copyToClipboard() {
    showCopied(await copyLink(props.link.url));
}

async function share() {
    showCopied(await shareOrCopy({ url: props.link.url, text: t(props.copy, 'done.share_text') }));
}
</script>

<template>
    <Head :title="t(copy, 'done.title')" />
    <div class="min-h-screen bg-gading">
        <main class="mx-auto max-w-md px-4 pb-44 pt-8">
            <p
                class="font-heading text-lg font-semibold text-status-paid"
                role="status"
            >
                {{ elapsed ? t(copy, 'done.published_in', elapsed) : t(copy, 'done.published') }}
            </p>
            <h1 class="mt-1 font-heading text-2xl font-semibold text-ink">
                {{ t(copy, 'done.ready') }}
            </h1>

            <article class="mt-5 overflow-hidden rounded-xl border border-ink/10 bg-surface">
                <img
                    v-if="trip.cover_url"
                    :src="trip.cover_url"
                    alt=""
                    class="aspect-[16/9] w-full object-cover"
                >
                <div class="p-4">
                    <h2 class="font-heading text-base font-semibold text-ink">
                        {{ trip.name }}
                    </h2>
                    <p
                        v-if="trip.from_price"
                        class="mt-1 text-sm text-ink"
                    >
                        {{ t(copy, 'done.from_price', { price: `Rp${formatRupiah(trip.from_price)}` }) }}
                    </p>
                    <p class="mt-1 text-xs text-secondary">
                        <template v-if="trip.request_date">
                            {{ t(copy, 'done.request_date') }}
                        </template>
                        <template v-else-if="trip.next_departure">
                            {{ t(copy, 'done.departure', { date: formatDayMonth(trip.next_departure.start_date, calendar), seats: trip.next_departure.seats_left }) }}
                        </template>
                    </p>
                </div>
            </article>

            <section class="mt-5">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'done.link') }}
                </p>
                <div class="mt-1 flex items-center gap-2 rounded-lg border border-ink/20 bg-surface pl-3">
                    <span class="min-w-0 flex-1 truncate font-heading text-base text-ink">{{ link.display }}</span>
                    <button
                        type="button"
                        class="min-h-12 shrink-0 rounded-r-lg border-l border-ink/20 px-4 font-heading text-sm font-semibold text-ink"
                        @click="copyToClipboard"
                    >
                        {{ t(copy, 'done.copy') }}
                    </button>
                </div>
                <p
                    v-if="copied"
                    class="mt-1 text-xs text-status-paid"
                    aria-live="polite"
                >
                    ✓ {{ t(copy, 'done.copied') }}
                </p>
            </section>

            <section class="mt-6">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'done.later') }}
                </p>
                <ul class="mt-1 divide-y divide-ink/10 rounded-lg bg-surface">
                    <li>
                        <Link
                            :href="urls.trip"
                            class="flex min-h-12 items-center justify-between px-3 text-sm text-ink"
                        >
                            {{ t(copy, 'done.later_details') }}
                            <span aria-hidden="true">›</span>
                        </Link>
                    </li>
                    <li>
                        <Link
                            :href="urls.profile"
                            class="flex min-h-12 items-center justify-between px-3 text-sm text-ink"
                        >
                            <span>{{ t(copy, 'done.later_profile') }} <span class="text-secondary">{{ t(copy, 'done.later_profile_time') }}</span></span>
                            <span aria-hidden="true">›</span>
                        </Link>
                    </li>
                </ul>
            </section>
        </main>

        <StickyAction>
            <button
                type="button"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink"
                @click="share"
            >
                {{ t(copy, 'done.share') }}
            </button>
            <Link
                :href="urls.dashboard"
                class="mt-2 flex min-h-12 w-full items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
            >
                {{ t(copy, 'done.dashboard') }}
            </Link>
        </StickyAction>
        <FlashMessages />
    </div>
</template>
