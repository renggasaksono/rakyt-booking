<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AccountStatusPill from '../../../components/AccountStatusPill.vue';
import AdminLayout from '../../../layouts/AdminLayout.vue';
import { sellerFilterQuery } from '../../../lib/adminSellers.js';
import { t } from '../../../lib/text.js';

/**
 * Penjual (F-012): every seller organization, newest registration first,
 * searchable by name, slug or WhatsApp number and filterable by account
 * status (filters live in the URL). Phone first — sellers are cards, the
 * filters stack; from 1024 px the filters sit in one row.
 */
const props = defineProps({
    sellers: { type: Array, required: true },
    filters: { type: Object, required: true },
    options: { type: Object, required: true },
    pagination: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const draft = reactive({ ...props.filters });
const hasFilters = computed(() => Object.keys(sellerFilterQuery(props.filters)).length > 0);

function apply() {
    router.get(props.urls.index, sellerFilterQuery(draft), { preserveScroll: false });
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <AdminLayout>
        <main class="mx-auto max-w-4xl px-4 py-8">
            <h1 class="font-heading text-2xl font-semibold text-ink">
                {{ t(copy, 'heading') }}
            </h1>
            <p class="mt-2 text-sm text-secondary">
                {{ t(copy, 'intro') }}
            </p>

            <form
                class="mt-6 grid gap-3 rounded-lg border border-ink/10 bg-surface p-4 lg:grid-cols-[2fr_1fr_auto] lg:items-end"
                role="search"
                :aria-label="t(copy, 'filters.title')"
                @submit.prevent="apply"
            >
                <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'filters.search') }}
                    <input
                        v-model="draft.cari"
                        type="search"
                        maxlength="100"
                        autocomplete="off"
                        :placeholder="t(copy, 'filters.search_placeholder')"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-3 text-base font-normal"
                    >
                </label>

                <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'filters.status') }}
                    <select
                        v-model="draft.status"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                    >
                        <option :value="null">{{ t(copy, 'filters.all_statuses') }}</option>
                        <option
                            v-for="status in options.statuses"
                            :key="status.value"
                            :value="status.value"
                        >{{ status.label }}</option>
                    </select>
                </label>

                <div class="flex flex-wrap items-center gap-3">
                    <button
                        type="submit"
                        class="min-h-12 rounded-lg bg-ink px-5 font-heading text-sm font-semibold text-surface"
                    >
                        {{ t(copy, 'filters.apply') }}
                    </button>
                    <Link
                        v-if="hasFilters"
                        :href="urls.index"
                        class="inline-flex min-h-11 items-center font-heading text-sm font-medium text-sogan underline"
                    >
                        {{ t(copy, 'filters.reset') }}
                    </Link>
                </div>
            </form>

            <p
                v-if="sellers.length === 0"
                class="mt-6 rounded-lg border border-dashed border-ink/20 bg-surface px-4 py-6 text-sm text-secondary"
            >
                {{ hasFilters ? t(copy, 'empty_filtered') : t(copy, 'empty') }}
            </p>

            <ul
                v-else
                class="mt-6 flex flex-col gap-3"
            >
                <li
                    v-for="seller in sellers"
                    :key="seller.id"
                >
                    <Link
                        :href="seller.url"
                        class="block rounded-lg border border-ink/10 bg-surface p-4 hover:border-ink/30"
                    >
                        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                            <h2 class="font-heading text-base font-semibold text-ink">
                                {{ seller.name }}
                            </h2>
                            <AccountStatusPill
                                :status="seller.status"
                                :label="seller.status_label"
                            />
                        </div>
                        <p class="mt-0.5 font-mono text-xs text-secondary">
                            {{ seller.slug }}
                        </p>
                        <p class="mt-2 flex flex-wrap gap-x-3 gap-y-1 text-sm text-ink">
                            <span>{{ seller.whatsapp }}</span>
                            <span>{{ seller.city }}</span>
                            <span class="text-secondary">{{ t(copy, 'registered', { date: seller.registered_label }) }}</span>
                        </p>
                    </Link>
                </li>
            </ul>

            <nav
                v-if="pagination.prev_url || pagination.next_url"
                class="mt-6 flex items-center justify-between gap-3"
                :aria-label="t(copy, 'page', { page: pagination.page })"
            >
                <Link
                    v-if="pagination.prev_url"
                    :href="pagination.prev_url"
                    class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    ← {{ t(copy, 'prev') }}
                </Link>
                <span v-else />
                <Link
                    v-if="pagination.next_url"
                    :href="pagination.next_url"
                    class="inline-flex min-h-11 items-center rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                >
                    {{ t(copy, 'next') }} →
                </Link>
            </nav>
        </main>
    </AdminLayout>
</template>
