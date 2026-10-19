<script setup>
import { ref } from 'vue';
import { copyLink, shareOrCopy } from '../lib/onboarding.js';
import { t } from '../lib/text.js';

/**
 * "Link booking Anda" (F-007 AC-14): F-001 D5's share behaviour — native share
 * sheet, else copy + "Link disalin" — with ink-outline buttons (the page's
 * gold button is "+ Trip baru", AC-5).
 */
const props = defineProps({
    url: { type: String, required: true },
    viewUrl: { type: String, default: null },
    copy: { type: Object, required: true },
    showTitle: { type: Boolean, default: true },
});

const state = ref(null);
const busy = ref(null);

async function run(kind) {
    if (busy.value) {
        return;
    }

    busy.value = kind;
    const result = kind === 'share'
        ? await shareOrCopy({ url: props.url, text: t(props.copy, 'share_text') })
        : await copyLink(props.url);
    busy.value = null;
    state.value = result === 'copied' ? 'copied' : (result === 'failed' ? 'failed' : null);
}
</script>

<template>
    <section class="rounded-xl border border-ink/10 bg-surface p-4">
        <h2
            v-if="showTitle"
            class="font-heading text-base font-semibold text-ink"
        >
            {{ t(copy, 'title') }}
        </h2>
        <p class="mt-1 break-all font-heading text-base text-ink">
            {{ url.replace(/^https?:\/\//, '') }}
        </p>
        <div class="mt-3 flex flex-col gap-2 sm:flex-row">
            <button
                type="button"
                class="min-h-11 flex-1 rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink disabled:text-secondary"
                :disabled="busy !== null"
                :aria-busy="busy === 'copy' ? 'true' : 'false'"
                @click="run('copy')"
            >
                {{ busy === 'copy' ? '…' : t(copy, 'copy') }}
            </button>
            <button
                type="button"
                class="min-h-11 flex-1 rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink disabled:text-secondary"
                :disabled="busy !== null"
                :aria-busy="busy === 'share' ? 'true' : 'false'"
                @click="run('share')"
            >
                {{ busy === 'share' ? '…' : t(copy, 'share') }}
            </button>
        </div>
        <p
            class="mt-2 text-xs"
            :class="state === 'failed' ? 'text-status-rejected' : 'text-status-paid'"
            aria-live="polite"
        >
            <template v-if="state === 'copied'">
                ✓ {{ t(copy, 'copied') }}
            </template>
            <template v-else-if="state === 'failed'">
                {{ t(copy, 'failed') }}
            </template>
        </p>
        <a
            v-if="viewUrl"
            :href="viewUrl"
            target="_blank"
            rel="noopener"
            class="mt-1 inline-flex min-h-11 items-center text-sm text-sogan underline"
        >
            {{ t(copy, 'view') }} <span aria-hidden="true">&nbsp;↗</span>
        </a>
    </section>
</template>
