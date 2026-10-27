<script setup>
import { computed, onBeforeUnmount, ref, watch } from 'vue';
import { PLATFORM } from '../lib/adminAudit.js';
import { lookupUrl as sellerLookupUrl, organizationLabel } from '../lib/adminSellers.js';
import { sendJson } from '../lib/http.js';
import { t } from '../lib/text.js';

/**
 * Admin organization filter (F-015, reused by F-013): type to search the
 * F-012 seller lookup, tap a result; "Ganti" clears it. Only the chosen
 * organization comes from the server, never the whole list. The audit view
 * also offers "Platform (tanpa organisasi)" (`allowPlatform`).
 *
 * v-model is the organization id as a string, `platform`, or null.
 */
const props = defineProps({
    modelValue: { type: String, default: null },
    selected: { type: Object, default: null },
    lookupUrl: { type: String, required: true },
    label: { type: String, required: true },
    // admin.audit.filters: organization_search, _hint, _none, _error, _change, platform.
    copy: { type: Object, required: true },
    allowPlatform: { type: Boolean, default: false },
});

const emit = defineEmits(['update:modelValue']);

const chosen = ref(props.selected);
const term = ref('');
const matches = ref([]);
const lookup = ref('idle'); // idle | loading | done | error
let lookupTimer = null;
let lookupSeq = 0;

const chosenLabel = computed(() => {
    if (props.allowPlatform && props.modelValue === PLATFORM) {
        return t(props.copy, 'platform');
    }

    return props.modelValue && chosen.value ? organizationLabel(chosen.value) : null;
});

watch(term, (value) => {
    clearTimeout(lookupTimer);
    const url = sellerLookupUrl(props.lookupUrl, value);
    const seq = ++lookupSeq;

    if (url === null) {
        matches.value = [];
        lookup.value = 'idle';

        return;
    }

    lookup.value = 'loading';
    lookupTimer = setTimeout(async () => {
        try {
            const body = await sendJson('GET', url);

            if (seq === lookupSeq) {
                matches.value = body.organizations;
                lookup.value = 'done';
            }
        } catch {
            if (seq === lookupSeq) {
                matches.value = [];
                lookup.value = 'error';
            }
        }
    }, 250);
});

onBeforeUnmount(() => clearTimeout(lookupTimer));

function choose(organization) {
    chosen.value = organization;
    term.value = '';
    emit('update:modelValue', organization ? organization.id : PLATFORM);
}

function clear() {
    chosen.value = null;
    emit('update:modelValue', null);
}
</script>

<template>
    <fieldset class="flex flex-col">
        <legend class="font-heading text-sm font-semibold text-ink">
            {{ label }}
        </legend>

        <div
            v-if="chosenLabel"
            class="mt-1 flex min-h-12 items-center justify-between gap-2 rounded-lg border border-ink/20 bg-gading px-3"
        >
            <span class="min-w-0 break-words text-base text-ink">{{ chosenLabel }}</span>
            <button
                type="button"
                class="inline-flex min-h-11 shrink-0 items-center font-heading text-sm font-medium text-sogan underline"
                @click="clear"
            >
                {{ t(copy, 'organization_change') }}
            </button>
        </div>

        <template v-else>
            <input
                v-model="term"
                type="search"
                maxlength="100"
                autocomplete="off"
                :aria-label="t(copy, 'organization_search')"
                :placeholder="t(copy, 'organization_search')"
                class="mt-1 min-h-12 rounded-lg border border-ink/20 bg-surface px-3 text-base font-normal"
                aria-describedby="organization-hint"
                aria-controls="organization-matches"
            >
            <p
                id="organization-hint"
                class="mt-1 text-xs text-secondary"
                aria-live="polite"
            >
                <template v-if="lookup === 'error'">
                    {{ t(copy, 'organization_error') }}
                </template>
                <template v-else-if="lookup === 'done' && matches.length === 0">
                    {{ t(copy, 'organization_none') }}
                </template>
                <template v-else-if="lookup === 'idle'">
                    {{ t(copy, 'organization_hint') }}
                </template>
            </p>
            <ul
                id="organization-matches"
                class="mt-1 flex flex-col"
            >
                <li
                    v-for="organization in matches"
                    :key="organization.id"
                >
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center rounded-lg px-2 text-left text-sm text-ink hover:bg-gading"
                        @click="choose(organization)"
                    >
                        {{ organizationLabel(organization) }}
                    </button>
                </li>
                <li v-if="allowPlatform">
                    <button
                        type="button"
                        class="flex min-h-11 w-full items-center rounded-lg px-2 text-left text-sm text-secondary hover:bg-gading"
                        @click="choose(null)"
                    >
                        {{ t(copy, 'platform') }}
                    </button>
                </li>
            </ul>
        </template>
    </fieldset>
</template>
