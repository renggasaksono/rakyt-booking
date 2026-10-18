<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { t } from '../../lib/text.js';

/** Pengaturan (F-001 F3): business profile, account and booking defaults. */
const props = defineProps({
    copy: { type: Object, required: true },
    items: { type: Array, required: true },
    urls: { type: Object, required: true },
    logoutLabel: { type: String, required: true },
});

function logout() {
    router.post(props.urls.logout);
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <SellerLayout>
        <div class="mx-auto max-w-md px-4 py-6">
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'title') }}
            </h1>
            <ul class="mt-4 divide-y divide-ink/10 rounded-xl bg-surface">
                <li
                    v-for="item in items"
                    :key="item.key"
                >
                    <Link
                        :href="item.url"
                        class="flex min-h-14 items-center justify-between gap-3 px-4 py-2"
                    >
                        <span>
                            <span class="block font-heading text-sm font-semibold text-ink">{{ t(copy, item.key) }}</span>
                            <span class="block text-xs text-secondary">{{ t(copy, `${item.key}_hint`) }}</span>
                        </span>
                        <span
                            aria-hidden="true"
                            class="text-secondary"
                        >›</span>
                    </Link>
                </li>
            </ul>
            <button
                type="button"
                class="mt-6 min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
                @click="logout"
            >
                {{ logoutLabel }}
            </button>
        </div>
    </SellerLayout>
</template>
