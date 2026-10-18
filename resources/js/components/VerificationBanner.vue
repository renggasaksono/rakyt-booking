<script setup>
import { router, usePage } from '@inertiajs/vue3';
import { computed, ref } from 'vue';

/**
 * F-001 A10: non-blocking reminder for an unverified email. "Tutup" hides it
 * for this browser session; "Kirim ulang" re-sends (throttled on the server).
 */
const KEY = 'rakyt.verify-banner-dismissed';

const page = usePage();
const account = computed(() => page.props.account);

function readDismissed() {
    try {
        return window.sessionStorage.getItem(KEY) === '1';
    } catch {
        return false;
    }
}

const dismissed = ref(readDismissed());
const sending = ref(false);
const visible = computed(() => account.value && !account.value.verified && !dismissed.value);

function dismiss() {
    dismissed.value = true;

    try {
        window.sessionStorage.setItem(KEY, '1');
    } catch {
        // Storage blocked: hidden until the next page load.
    }
}

function resend() {
    router.post(account.value.urls.resend, {}, {
        preserveScroll: true,
        preserveState: true,
        onStart: () => (sending.value = true),
        onFinish: () => (sending.value = false),
    });
}
</script>

<template>
    <div
        v-if="visible"
        class="border-b border-status-check/20 bg-status-check-tint px-4 py-2"
        role="status"
    >
        <div class="mx-auto flex max-w-md flex-wrap items-center gap-x-3 gap-y-1 text-sm text-status-check lg:max-w-6xl">
            <p class="min-w-0 flex-1">
                {{ account.copy.banner }}
                <button
                    type="button"
                    class="inline-flex min-h-11 items-center font-semibold underline disabled:no-underline"
                    :disabled="sending"
                    @click="resend"
                >
                    {{ account.copy.resend }}
                </button>
            </p>
            <button
                type="button"
                class="flex min-h-11 min-w-11 items-center justify-center text-sm font-semibold underline"
                @click="dismiss"
            >
                {{ account.copy.dismiss }}
            </button>
        </div>
    </div>
</template>
