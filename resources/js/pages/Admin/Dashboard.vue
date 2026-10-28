<script setup>
import { Head, Link } from '@inertiajs/vue3';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { formatCount, statStyle } from '../../lib/adminOverview.js';
import { t } from '../../lib/text.js';

/**
 * Admin overview (F-011): five live counts across every seller, each a link
 * to its filtered list in Penjual (F-012) or Pesanan (F-013). Suspended
 * sellers are counted apart from active ones. Phone first — one column of
 * cards, two from 640 px, three from 1024 px. Read-only: no primary action.
 */
defineProps({
    stats: { type: Object, required: true },
    copy: { type: Object, required: true },
});

const bookingStats = ['bookings', 'awaiting_payment', 'needs_review'];
const sellerStatuses = ['active', 'suspended'];
</script>

<template>
    <Head :title="t(copy, 'dashboard.title')" />
    <AdminLayout>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <h1 class="font-heading text-2xl font-semibold text-ink">
                {{ t(copy, 'dashboard.heading') }}
            </h1>
            <p class="mt-2 text-sm text-secondary">
                {{ t(copy, 'dashboard.intro') }}
            </p>

            <section
                class="mt-6"
                aria-labelledby="overview-sellers"
            >
                <h2
                    id="overview-sellers"
                    class="font-heading text-base font-semibold text-ink"
                >
                    {{ t(copy, 'dashboard.sellers_section') }}
                </h2>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <div class="rounded-lg border border-ink/10 bg-surface p-4">
                        <Link
                            :href="stats.sellers.url"
                            class="block min-h-11 rounded-lg hover:text-sogan"
                        >
                            <span class="block font-heading text-3xl font-semibold text-ink">
                                {{ formatCount(stats.sellers.count) }}
                            </span>
                            <span class="mt-1 block font-heading text-sm font-medium text-ink underline">
                                {{ t(copy, 'dashboard.stats.sellers') }}
                            </span>
                            <span class="mt-1 block text-xs text-secondary">
                                {{ t(copy, 'dashboard.stats.sellers_hint') }}
                            </span>
                        </Link>
                        <div class="mt-3 flex flex-wrap gap-2">
                            <Link
                                v-for="status in sellerStatuses"
                                :key="status"
                                :href="stats.sellers[status].url"
                                class="inline-flex min-h-11 items-center gap-1.5 rounded-lg px-3 font-heading text-sm font-medium"
                                :class="statStyle(status).className"
                            >
                                <span aria-hidden="true">{{ statStyle(status).glyph }}</span>
                                {{ t(copy, `dashboard.stats.${status}`) }}
                                <span class="font-semibold">{{ formatCount(stats.sellers[status].count) }}</span>
                            </Link>
                        </div>
                    </div>

                    <Link
                        :href="stats.published_trips.url"
                        class="block min-h-11 rounded-lg border border-ink/10 bg-surface p-4 hover:border-ink/30"
                    >
                        <span class="block font-heading text-3xl font-semibold text-ink">
                            {{ formatCount(stats.published_trips.count) }}
                        </span>
                        <span class="mt-1 block font-heading text-sm font-medium text-ink underline">
                            {{ t(copy, 'dashboard.stats.published_trips') }}
                        </span>
                        <span class="mt-1 block text-xs text-secondary">
                            {{ t(copy, 'dashboard.stats.published_trips_hint') }}
                        </span>
                    </Link>
                </div>
            </section>

            <section
                class="mt-8"
                aria-labelledby="overview-bookings"
            >
                <h2
                    id="overview-bookings"
                    class="font-heading text-base font-semibold text-ink"
                >
                    {{ t(copy, 'dashboard.bookings_section') }}
                </h2>
                <div class="mt-3 grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
                    <Link
                        v-for="key in bookingStats"
                        :key="key"
                        :href="stats[key].url"
                        class="block min-h-11 rounded-lg border border-ink/10 bg-surface p-4 hover:border-ink/30"
                    >
                        <span
                            class="inline-flex items-center gap-2 rounded-lg font-heading text-3xl font-semibold"
                            :class="statStyle(key) ? ['px-2', statStyle(key).className] : 'text-ink'"
                        >
                            <span
                                v-if="statStyle(key)"
                                aria-hidden="true"
                                class="text-xl"
                            >{{ statStyle(key).glyph }}</span>
                            {{ formatCount(stats[key].count) }}
                        </span>
                        <span class="mt-1 block font-heading text-sm font-medium text-ink underline">
                            {{ t(copy, `dashboard.stats.${key}`) }}
                        </span>
                        <span class="mt-1 block text-xs text-secondary">
                            {{ t(copy, `dashboard.stats.${key}_hint`) }}
                        </span>
                    </Link>
                </div>
            </section>
        </main>
    </AdminLayout>
</template>
