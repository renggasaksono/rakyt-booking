<script setup>
import { Link, usePage } from '@inertiajs/vue3';
import { computed } from 'vue';
import FlashMessages from '../components/FlashMessages.vue';

/**
 * Platform admin shell (F-010, ADR-0011). A separate boundary from the seller
 * app: side nav from 1024 px, its items from the shared `admin` prop. F-011–F-015
 * hang their pages inside the default slot.
 */
const page = usePage();
const admin = computed(() => page.props.admin);
const nav = computed(() => admin.value?.nav ?? []);
</script>

<template>
    <div class="min-h-screen bg-gading lg:flex">
        <aside class="shrink-0 border-ink/10 bg-surface px-4 py-6 lg:w-60 lg:border-r">
            <p class="px-3 font-heading text-xl font-semibold text-sogan">
                {{ admin?.brand }}
            </p>
            <nav
                class="mt-6 flex flex-col gap-1"
                :aria-label="admin?.menu_label"
            >
                <Link
                    v-for="item in nav"
                    :key="item.key"
                    :href="item.url"
                    class="flex min-h-11 items-center rounded-lg px-3 font-heading text-sm font-medium"
                    :class="item.active ? 'bg-gading text-sogan' : 'text-ink hover:bg-gading'"
                    :aria-current="item.active ? 'page' : undefined"
                >
                    {{ item.label }}
                </Link>
            </nav>

            <div
                v-if="admin"
                class="mt-6 border-t border-ink/10 px-3 pt-4"
            >
                <p class="text-xs text-secondary">
                    {{ admin.signed_in_as }}
                </p>
                <Link
                    :href="admin.logout.url"
                    method="post"
                    as="button"
                    class="mt-2 inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                >
                    {{ admin.logout.label }}
                </Link>
            </div>
        </aside>

        <div class="min-w-0 flex-1">
            <slot />
        </div>

        <FlashMessages />
    </div>
</template>
