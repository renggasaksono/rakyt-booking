<script setup>
import { Head, Link, router } from '@inertiajs/vue3';
import { computed, reactive } from 'vue';
import AdminLayout from '../../layouts/AdminLayout.vue';
import { activeAuditFilterCount, actorFilter, auditFilterQuery, organizationFilter, PLATFORM } from '../../lib/adminAudit.js';
import { t } from '../../lib/text.js';

/**
 * Log audit (F-015): every audit event across organizations, newest first,
 * filterable by organization, actor and action (filters live in the URL).
 * Read-only: audit events are append-only. Phone first — events are cards,
 * the filters stack; from 1024 px the filters sit in one row.
 */
const props = defineProps({
    events: { type: Array, required: true },
    filters: { type: Object, required: true },
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

function actorText(actor) {
    const who = actor.name ?? actor.id;

    return who ? `${actor.type_label} · ${who}` : actor.type_label;
}

function subjectText(subject) {
    return subject.name ? `${subject.type_label} #${subject.id} · ${subject.name}` : `${subject.type_label} #${subject.id}`;
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
                <label class="flex flex-col font-heading text-sm font-semibold text-ink">
                    {{ t(copy, 'filters.organization') }}
                    <select
                        v-model="draft.organisasi"
                        class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-2 text-base font-normal"
                    >
                        <option :value="null">{{ t(copy, 'filters.all_organizations') }}</option>
                        <option :value="PLATFORM">{{ t(copy, 'filters.platform') }}</option>
                        <option
                            v-for="organization in options.organizations"
                            :key="organization.id"
                            :value="organization.id"
                        >{{ organization.name }} ({{ organization.slug }})</option>
                    </select>
                </label>

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
                    class="rounded-lg border border-ink/10 bg-surface p-4"
                >
                    <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
                        <h2 class="font-heading text-base font-semibold text-ink">
                            {{ event.action_label }}
                        </h2>
                        <time
                            :datetime="event.at"
                            class="text-sm text-secondary"
                        >{{ event.at_label }}</time>
                    </div>
                    <p class="mt-0.5 font-mono text-xs text-secondary">
                        {{ event.action }} · #{{ event.id }}
                    </p>

                    <dl class="mt-3 grid gap-x-4 gap-y-2 text-sm sm:grid-cols-[8rem_1fr]">
                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'event.organization') }}
                        </dt>
                        <dd>
                            <Link
                                :href="filterUrl(organizationFilter(filters, event))"
                                class="inline-flex min-h-11 items-center text-sogan underline sm:min-h-0"
                                :title="t(copy, 'event.filter_by', { name: event.organization?.name ?? t(copy, 'event.no_organization') })"
                            >
                                {{ event.organization ? (event.organization.name ?? `#${event.organization.id}`) : t(copy, 'event.no_organization') }}
                            </Link>
                        </dd>

                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'event.actor') }}
                        </dt>
                        <dd>
                            <Link
                                :href="filterUrl(actorFilter(filters, event))"
                                class="inline-flex min-h-11 items-center break-all text-sogan underline sm:min-h-0"
                                :title="t(copy, 'event.filter_by', { name: actorText(event.actor) })"
                            >
                                {{ actorText(event.actor) }}
                            </Link>
                            <span
                                v-if="event.ip"
                                class="ml-2 text-xs text-secondary"
                            >{{ t(copy, 'event.ip') }} {{ event.ip }}</span>
                        </dd>

                        <dt class="font-heading font-semibold text-ink">
                            {{ t(copy, 'event.subject') }}
                        </dt>
                        <dd class="break-all text-ink">
                            {{ subjectText(event.subject) }}
                        </dd>

                        <template v-if="event.reason">
                            <dt class="font-heading font-semibold text-ink">
                                {{ t(copy, 'event.reason') }}
                            </dt>
                            <dd class="whitespace-pre-line text-ink">
                                {{ event.reason }}
                            </dd>
                        </template>
                    </dl>

                    <div
                        v-if="event.changes.length"
                        class="mt-3"
                    >
                        <h3 class="font-heading text-sm font-semibold text-ink">
                            {{ t(copy, 'event.changes') }}
                        </h3>
                        <ul class="mt-1 flex flex-col gap-1 text-sm">
                            <li
                                v-for="change in event.changes"
                                :key="change.field"
                                class="break-all"
                            >
                                <span class="font-mono text-xs text-secondary">{{ change.field }}</span>:
                                <span class="text-secondary line-through">{{ change.before ?? t(copy, 'event.empty_value') }}</span>
                                →
                                <span class="text-ink">{{ change.after ?? t(copy, 'event.empty_value') }}</span>
                            </li>
                        </ul>
                    </div>
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
