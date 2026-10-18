<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashMessages from '../components/FlashMessages.vue';
import VerificationBanner from '../components/VerificationBanner.vue';

defineProps({
    // The trip editor uses the bottom of the phone screen for its own action bar.
    hideBottomNav: { type: Boolean, default: false },
});

const page = usePage();
const seller = computed(() => page.props.seller);
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
                <Link
                    v-for="item in seller?.nav ?? []"
                    :key="item.key"
                    :href="item.url"
                    class="flex min-h-11 items-center rounded-lg px-3 font-heading text-sm font-medium"
                    :class="item.active ? 'bg-gading text-sogan' : 'text-ink hover:bg-gading'"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
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
                    v-for="item in seller?.nav ?? []"
                    :key="item.key"
                    :href="item.url"
                    class="flex min-h-14 flex-1 items-center justify-center px-2 text-center font-heading text-xs font-medium"
                    :class="item.active ? 'text-sogan' : 'text-secondary'"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </div>
        </nav>

        <FlashMessages />
    </div>
</template>
