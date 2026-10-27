<script setup>
import { Link } from '@inertiajs/vue3';
import { t } from '../lib/text.js';

/**
 * One audit event (F-015), as AuditEventPresenter shapes it. The audit view
 * links the organization and actor to "show only …" filters; the seller
 * detail (F-012) shows them as text.
 */
defineProps({
    event: { type: Object, required: true },
    copy: { type: Object, required: true },
    organizationUrl: { type: String, default: null },
    actorUrl: { type: String, default: null },
    headingLevel: { type: String, default: 'h2' },
});

function organizationText(event, copy) {
    return event.organization ? (event.organization.name ?? `#${event.organization.id}`) : t(copy, 'event.no_organization');
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
    <div class="rounded-lg border border-ink/10 bg-surface p-4">
        <div class="flex flex-wrap items-baseline justify-between gap-x-4 gap-y-1">
            <component
                :is="headingLevel"
                class="font-heading text-base font-semibold text-ink"
            >
                {{ event.action_label }}
            </component>
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
                    v-if="organizationUrl"
                    :href="organizationUrl"
                    class="inline-flex min-h-11 items-center text-sogan underline sm:min-h-0"
                    :title="t(copy, 'event.filter_by', { name: event.organization?.name ?? t(copy, 'event.no_organization') })"
                >
                    {{ organizationText(event, copy) }}
                </Link>
                <span
                    v-else
                    class="text-ink"
                >{{ organizationText(event, copy) }}</span>
            </dd>

            <dt class="font-heading font-semibold text-ink">
                {{ t(copy, 'event.actor') }}
            </dt>
            <dd>
                <Link
                    v-if="actorUrl"
                    :href="actorUrl"
                    class="inline-flex min-h-11 items-center break-all text-sogan underline sm:min-h-0"
                    :title="t(copy, 'event.filter_by', { name: actorText(event.actor) })"
                >
                    {{ actorText(event.actor) }}
                </Link>
                <span
                    v-else
                    class="break-all text-ink"
                >{{ actorText(event.actor) }}</span>
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
            <component
                :is="headingLevel === 'h2' ? 'h3' : 'h4'"
                class="font-heading text-sm font-semibold text-ink"
            >
                {{ t(copy, 'event.changes') }}
            </component>
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
    </div>
</template>
