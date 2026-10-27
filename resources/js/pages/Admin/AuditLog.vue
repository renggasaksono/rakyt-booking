<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AuditEventCard from '../../components/AuditEventCard.vue';
import OrganizationPicker from '../../components/OrganizationPicker.vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { activeAuditFilterCount, actorFilter, auditFilterQuery, organizationFilter } from '../../lib/adminAudit.js';
import { t } from '../../lib/text.js';

/**
 * Log audit (F-015): every audit event across organizations, newest first,
 * filterable by organization, actor and action (filters live in the URL).
 * Read-only: audit events are append-only. Phone first — events are cards,
 * the filters stack; from 1024 px the filters sit in one row. The
 * organization filter is a searchable picker over the F-012 seller lookup,
 * so not every organization is loaded.
 */
const props = defineProps({
    events: { type: Array, required: true },
    filters: { type: Object, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    selected_organization: { type: Object, default: null },
    options: { type: Object, required: true },
    pagination: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const draft = reactive({ ...props.filters });
const hasFilters = computed(() => activeAuditFilterCount(props.filters) > 0);

function visit(filters) {
    router.get(props.urls.index, auditFilterQuery(filters), { preserveScroll: false });
}

function apply() {
    visit(draft);
}

function filterUrl(filters) {
    const query = new URLSearchParams(auditFilterQuery(filters)).toString();

    return query ? `${props.urls.index}?${query}` : props.urls.index;
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
                class="mt-6 grid gap-3 rounded-lg border border-ink/10 bg-surface p-4 lg:grid-cols-[1fr_1fr_1fr_auto] lg:items-start"
                :aria-label="t(copy, 'filters.title')"
                @submit.prevent="apply"
            >
                <OrganizationPicker
                    v-model="draft.organisasi"
                    :selected="selected_organization"
                    :lookup-url="urls.organization_lookup"
                    :label="t(copy, 'filters.organization')"
                    :copy="copy.filters"
                    allow-platform
                />

                <div class="flex flex-col gap-2">
                    <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                        {{ t(copy, 'filters.actor') }}
                        <select
                            v-model="draft.aktor"
                            class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                        >
                            <option :value="null">{{ t(copy, 'filters.all_actors') }}</option>
                            <option
                                v-for="type in options.actor_types"
                                :key="type.value"
                                :value="type.value"
                            >{{ type.label }}</option>
                        </select>
                    </label>
                    <label
                        v-if="draft.aktor"
                        class="flex flex-col font-heading text-sm font-semibold text-ink"
                    >
                        {{ t(copy, 'filters.actor_id') }}
                        <input
                            v-model="draft.aktor_id"
                            type="text"
                            maxlength="100"
                            autocomplete="off"
                            class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                            aria-describedby="actor-id-hint"
                        >
                        <span
                            id="actor-id-hint"
                            class="mt-1 text-xs font-normal text-secondary"
                        >{{ t(copy, 'filters.actor_id_hint') }}</span>
                    </label>
                </div>

                <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'filters.action') }}
                    <select
                        v-model="draft.aksi"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                    >
                        <option :value="null">{{ t(copy, 'filters.all_actions') }}</option>
                        <option
                            v-for="action in options.actions"
                            :key="action.value"
                            :value="action.value"
                        >{{ action.label }}</option>
                    </select>
                </label>

                <div class="flex flex-wrap items-center gap-3 lg:mt-6">
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
                v-if="events.length === 0"
                class="mt-6 rounded-lg border border-dashed border-ink/20 bg-surface px-4 py-6 text-sm text-secondary"
            >
                {{ hasFilters ? t(copy, 'empty_filtered') : t(copy, 'empty') }}
            </p>

            <ol
                v-else
                class="mt-6 flex flex-col gap-3"
            >
                <li
                    v-for="event in events"
                    :key="event.id"
                >
                    <AuditEventCard
                        :event="event"
                        :copy="copy"
                        :organization-url="filterUrl(organizationFilter(filters, event))"
                        :actor-url="filterUrl(actorFilter(filters, event))"
                    />
                </li>
            </ol>

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
