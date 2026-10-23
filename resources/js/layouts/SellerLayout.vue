<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashMessages from '../components/FlashMessages.vue';
import ProductMark from '../components/ProductMark.vue';
import VerificationBanner from '../components/VerificationBanner.vue';

/**
 * Seller app layout (F-007 AC-2–AC-4): six-item side nav from 1024 px, a
 * four-item bottom nav below it; Pesanan and Pembayaran carry the Perlu
 * tindakan badge. Halaman publik opens the seller page in a new tab. The side
 * nav is the ink panel of mockups 1e/2f with the "Rakyt | Booking" lockup.
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
        <aside class="hidden w-60 shrink-0 flex-col bg-ink px-5 py-7 lg:sticky lg:top-0 lg:flex lg:h-screen lg:overflow-y-auto">
            <div class="px-3">
                <ProductMark
                    :brand="seller?.brand ?? ''"
                    :suffix="seller?.brand_suffix"
                    tone="reverse"
                />
            </div>
            <nav
                class="mt-8 flex flex-col gap-0.5"
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
                        class="flex min-h-11 items-center justify-between gap-2 px-3 font-heading text-sm text-sand hover:bg-gading/10 hover:text-gading"
                    >
                        {{ item.label }}
                        <span aria-hidden="true">{{ seller.external_mark }}</span>
                        <span class="sr-only">{{ seller.new_tab }}</span>
                    </a>
                    <Link
                        v-else
                        :href="item.url"
                        class="flex min-h-11 items-center justify-between gap-2 px-3 font-heading text-sm"
                        :class="item.active ? 'bg-sogan font-semibold text-gading' : 'text-sand hover:bg-gading/10 hover:text-gading'"
                        :aria-current="item.active ? 'page' : undefined"
                    >
                        {{ item.label }}
                        <!-- 1e/2f: a plain count; Pembayaran's is the gold-soft accent (gading on the Sogan fill, where gold-soft is 3.9 : 1). -->
                        <span
                            v-if="item.badge"
                            class="min-w-6 text-right text-sm"
                            :class="item.key === 'payments' ? ['font-semibold', item.active ? 'text-gading' : 'text-gold-soft'] : ''"
                            :aria-label="item.badge.label"
                        >{{ item.badge.count }}</span>
                    </Link>
                </template>
            </nav>
            <div
                v-if="seller?.organization"
                class="mt-auto px-3 pt-8"
            >
                <p class="truncate font-heading text-sm font-semibold text-gading">
                    {{ seller.organization.name }}
                </p>
                <p class="mt-1 text-xs text-sand">
                    {{ seller.organization.plan_label }}
                </p>
            </div>
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
            class="fixed inset-x-0 bottom-0 z-30 bg-ink pb-[env(safe-area-inset-bottom)] lg:hidden"
            :aria-label="seller?.menu_label"
        >
            <!-- Same ink panel as the side nav: sand items, the current one on a Sogan fill. -->
            <div class="mx-auto flex max-w-md">
                <Link
                    v-for="item in phoneNav"
                    :key="item.key"
                    :href="item.url"
                    class="relative flex min-h-14 flex-1 items-center justify-center gap-1 px-2 text-center font-heading text-xs"
                    :class="item.active ? 'bg-sogan font-semibold text-gading' : 'font-medium text-sand'"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ item.phone_label ?? item.label }}
                    <!-- Pembayaran is not on the phone bar, so Pesanan's count takes its gold-soft accent. -->
                    <span
                        v-if="item.badge"
                        class="min-w-4 text-center text-xs font-semibold"
                        :class="item.active ? 'text-gading' : 'text-gold-soft'"
                        :aria-label="item.badge.label"
                    >{{ item.badge.count }}</span>
                </Link>
            </div>
        </nav>

        <FlashMessages />
    </div>
</template>
