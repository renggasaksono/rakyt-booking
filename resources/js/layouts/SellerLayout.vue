<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashMessages from '../components/FlashMessages.vue';
import VerificationBanner from '../components/VerificationBanner.vue';

/**
 * Seller app layout (F-007 AC-2–AC-4): six-item side nav from 1024 px, a
 * four-item bottom nav below it; Pesanan and Pembayaran carry the Perlu
 * tindakan badge. Halaman publik opens the seller page in a new tab.
 */
defineProps({
    // The trip editor uses the bottom of the phone screen for its own action bar.
    hideBottomNav: { type: Boolean, default: false },
});

const page = usePage();
const seller = computed(() => page.props.seller);
const nav = computed(() => seller.value?.nav ?? []);
const phoneNav = computed(() => nav.value.filter((item) => item.phone));
</script>

<template>
    <div class="min-h-screen bg-gading lg:flex">
        <aside class="hidden w-60 shrink-0 border-r border-ink/10 bg-surface px-4 py-6 lg:block">
            <p class="px-3 font-heading text-xl font-semibold text-ink">
                {{ seller?.brand }}
            </p>
            <nav
                class="mt-6 flex flex-col gap-1"
                :aria-label="seller?.menu_label"
            >
                <template
                    v-for="item in nav"
                    :key="item.key"
                >
                    <a
                        v-if="item.external"
                        :href="item.url"
                        target="_blank"
                        rel="noopener"
                        class="flex min-h-11 items-center justify-between gap-2 rounded-lg px-3 font-heading text-sm font-medium text-ink hover:bg-gading"
                    >
                        {{ item.label }}
                        <span aria-hidden="true">{{ seller.external_mark }}</span>
                        <span class="sr-only">{{ seller.new_tab }}</span>
                    </a>
                    <Link
                        v-else
                        :href="item.url"
                        class="flex min-h-11 items-center justify-between gap-2 rounded-lg px-3 font-heading text-sm font-medium"
                        :class="item.active ? 'bg-gading text-sogan' : 'text-ink hover:bg-gading'"
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        {{ item.label }}
                        <span
                            v-if="item.badge"
                            class="min-w-6 rounded-full bg-status-waiting-tint px-2 text-center text-xs font-semibold text-status-waiting"
                            :aria-label="item.badge.label"
                        >{{ item.badge.count }}</span>
                    </Link>
                </template>
            </nav>
        </aside>

        <div
            class="min-w-0 flex-1"
            :class="hideBottomNav ? '' : 'pb-20 lg:pb-0'"
        >
            <VerificationBanner />
            <slot />
        </div>

        <nav
            v-if="!hideBottomNav"
            class="fixed inset-x-0 bottom-0 z-30 border-t border-ink/10 bg-surface lg:hidden"
            :aria-label="seller?.menu_label"
        >
            <div class="mx-auto flex max-w-md">
                <Link
                    v-for="item in phoneNav"
                    :key="item.key"
                    :href="item.url"
                    class="relative flex min-h-14 flex-1 items-center justify-center gap-1 px-2 text-center font-heading text-xs font-medium"
                    :class="item.active ? 'text-sogan' : 'text-secondary'"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ item.phone_label ?? item.label }}
                    <span
                        v-if="item.badge"
                        class="min-w-5 rounded-full bg-status-waiting-tint px-1.5 text-center text-xs font-semibold text-status-waiting"
                        :aria-label="item.badge.label"
                    >{{ item.badge.count }}</span>
                </Link>
            </div>
        </nav>

        <FlashMessages />
    </div>
</template>
