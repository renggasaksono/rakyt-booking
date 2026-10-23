<script setup>
import { ref } from 'vue';
import { copyLink, shareOrCopy } from '../lib/onboarding.js';
import { t } from '../lib/text.js';

/**
 * "Link booking Anda" (F-007 AC-14): F-001 D5's share behaviour — native share
 * sheet, else copy + "Link disalin". Buttons are the 2f secondary size, smaller
 * than the page's gold "+ Trip baru" (AC-5): Salin link in the 1.5px ink
 * outline, Bagikan in the quiet 1px one.
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
    <section class="border border-ink/10 bg-surface p-5">
        <h2
            v-if="showTitle"
            class="font-heading text-[11px] font-semibold uppercase tracking-[0.16em] text-sogan"
        >
            {{ t(copy, 'title') }}
        </h2>
        <p class="mt-2 break-all font-heading text-lg font-semibold text-ink">
            {{ url.replace(/^https?:\/\//, '') }}
        </p>
        <div class="mt-4 flex flex-col gap-2 sm:flex-row sm:flex-wrap">
            <button
                type="button"
                class="min-h-11 px-3.5 font-heading text-[13px] font-semibold text-ink ring-[1.5px] ring-ink ring-inset disabled:text-secondary lg:min-h-9"
                :disabled="busy !== null"
                :aria-busy="busy === 'copy' ? 'true' : 'false'"
                @click="run('copy')"
            >
                {{ busy === 'copy' ? '…' : t(copy, 'copy') }}
            </button>
            <button
                type="button"
                class="min-h-11 px-3.5 text-[13px] text-ink ring-1 ring-ink/25 ring-inset disabled:text-secondary lg:min-h-9"
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
