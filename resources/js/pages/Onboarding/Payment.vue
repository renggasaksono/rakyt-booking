<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FieldError from '../../components/FieldError.vue';
import StickyAction from '../../components/StickyAction.vue';
import OnboardingLayout from '../../layouts/OnboardingLayout.vue';
import { saveFailureHandlers } from '../../lib/http.js';
import { t } from '../../lib/text.js';

/**
 * Langkah 4 dari 4 · Pembayaran (mockup 4e, F-001 C10–C12, D1). Accounts
 * belong to the organization; money goes straight to the seller (ADR-0003).
 */
const props = defineProps({
    methods: { type: Array, required: true },
    providers: { type: Array, required: true },
    limits: { type: Object, required: true },
    header: { type: Object, required: true },
    highlight: { type: String, default: null },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
});

let sequence = 0;
const blank = () => ({ key: ++sequence, id: null, provider: '', provider_name: '', account_number: '', account_holder: '' });

const form = useForm({
    intent: 'publish',
    methods: props.methods.length
        ? props.methods.map((method) => ({ key: ++sequence, ...method, provider_name: method.provider_name ?? '' }))
        : [blank()],
});

const failed = ref(false);
const canAdd = computed(() => form.methods.length < props.limits.methods);
const providerByValue = computed(() => Object.fromEntries(props.providers.map((provider) => [provider.value, provider])));

function errorFor(index, field) {
    return form.errors[`methods.${index}.${field}`] ?? null;
}

function numberLabel(method) {
    return providerByValue.value[method.provider]?.type === 'ewallet'
        ? t(props.copy, 'payment.account_number_ewallet')
        : t(props.copy, 'payment.account_number');
}

function onNumberInput(method, event) {
    // C10: digits only, spaces ignored.
    method.account_number = event.target.value.replace(/[^\d\s]/g, '');
    event.target.value = method.account_number;
}

function add() {
    if (canAdd.value) {
        form.methods.push(blank());
    }
}

function remove(index) {
    form.methods.splice(index, 1);
}

function send(intent) {
    if (form.processing) {
        return;
    }

    failed.value = false;
    form.intent = intent;
    form.transform((data) => ({
        intent: data.intent,
        // eslint-disable-next-line no-unused-vars
        methods: data.methods.map(({ key, ...method }) => method),
    })).post(props.urls.store, {
        preserveScroll: true,
        ...saveFailureHandlers(() => (failed.value = true)),
    });
}
</script>

<template>
    <Head :title="t(copy, 'payment.title')" />
    <OnboardingLayout
        :header="header"
        :copy="copy"
        :back-url="urls.back"
    >
        <h1 class="font-heading text-xl font-semibold text-ink">
            {{ t(copy, 'payment.title') }}
        </h1>
        <p class="mt-2 rounded-lg bg-status-paid-tint px-3 py-2 text-sm text-status-paid">
            {{ t(copy, 'payment.note') }}
        </p>

        <p
            v-if="$page.props.errors?.publish"
            class="mt-3 rounded-lg bg-status-rejected-tint px-3 py-2 text-sm text-status-rejected"
            role="alert"
        >
            {{ $page.props.errors.publish }}
        </p>

        <form
            id="payment-form"
            class="mt-5 flex flex-col gap-4"
            novalidate
            @submit.prevent="send('publish')"
        >
            <fieldset
                v-for="(method, index) in form.methods"
                :key="method.key"
                class="rounded-xl border bg-surface p-4"
                :class="highlight === 'payment_instructions' ? 'border-status-rejected' : 'border-ink/10'"
            >
                <legend class="sr-only">
                    {{ t(copy, 'payment.account_n', { n: index + 1 }) }}
                </legend>
                <div class="flex items-center justify-between">
                    <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                        {{ t(copy, 'payment.account_n', { n: index + 1 }) }}
                    </p>
                    <button
                        v-if="form.methods.length > 1"
                        type="button"
                        class="min-h-11 px-2 text-sm text-status-rejected underline"
                        @click="remove(index)"
                    >
                        {{ t(copy, 'payment.remove') }}
                    </button>
                </div>

                <label
                    :for="`provider-${method.key}`"
                    class="mt-2 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'payment.provider') }}</label>
                <select
                    :id="`provider-${method.key}`"
                    v-model="method.provider"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="errorFor(index, 'provider') ? 'border-status-rejected' : 'border-ink/20'"
                >
                    <option
                        value=""
                        disabled
                    >
                        {{ t(copy, 'payment.provider_placeholder') }}
                    </option>
                    <option
                        v-for="provider in providers"
                        :key="provider.value"
                        :value="provider.value"
                    >
                        {{ provider.label }}
                    </option>
                </select>
                <FieldError :message="errorFor(index, 'provider')" />

                <template v-if="providerByValue[method.provider]?.asks_name">
                    <label
                        :for="`provider-name-${method.key}`"
                        class="mt-3 block font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'payment.provider_name') }}</label>
                    <input
                        :id="`provider-name-${method.key}`"
                        v-model="method.provider_name"
                        type="text"
                        :maxlength="limits.provider_name"
                        class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                        :class="errorFor(index, 'provider_name') ? 'border-status-rejected' : 'border-ink/20'"
                    >
                    <FieldError :message="errorFor(index, 'provider_name')" />
                </template>

                <label
                    :for="`number-${method.key}`"
                    class="mt-3 block font-heading text-sm font-semibold text-ink"
                >{{ numberLabel(method) }}</label>
                <input
                    :id="`number-${method.key}`"
                    :value="method.account_number"
                    type="text"
                    inputmode="numeric"
                    autocomplete="off"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base tracking-wide text-ink"
                    :class="errorFor(index, 'account_number') ? 'border-status-rejected' : 'border-ink/20'"
                    @input="onNumberInput(method, $event)"
                >
                <FieldError :message="errorFor(index, 'account_number')" />

                <label
                    :for="`holder-${method.key}`"
                    class="mt-3 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'payment.account_holder') }}</label>
                <input
                    :id="`holder-${method.key}`"
                    v-model="method.account_holder"
                    type="text"
                    autocomplete="name"
                    :maxlength="limits.holder"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="errorFor(index, 'account_holder') ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-describedby="`holder-hint-${method.key}`"
                >
                <p
                    :id="`holder-hint-${method.key}`"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'payment.account_holder_hint') }}
                </p>
                <FieldError :message="errorFor(index, 'account_holder')" />
            </fieldset>

            <FieldError :message="form.errors.methods" />

            <div>
                <button
                    v-if="canAdd"
                    type="button"
                    class="min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                    @click="add"
                >
                    {{ t(copy, 'payment.add') }}
                </button>
                <p
                    v-else
                    class="text-xs text-secondary"
                >
                    {{ t(copy, 'payment.max', { max: limits.methods }) }}
                </p>
                <p class="mt-1 text-xs text-secondary">
                    {{ t(copy, 'payment.qris_later') }}
                </p>
            </div>

            <FieldError :message="failed ? t(copy, 'save_failed') : null" />
        </form>

        <template #action>
            <StickyAction>
                <button
                    type="submit"
                    form="payment-form"
                    class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                    :aria-busy="form.processing && form.intent === 'publish'"
                >
                    {{ form.processing && form.intent === 'publish' ? t(copy, 'saving') : t(copy, 'payment.publish') }}
                </button>
                <p class="mt-1 text-center text-xs text-secondary">
                    {{ t(copy, 'payment.draft_before') }}
                    <button
                        type="button"
                        class="inline-flex min-h-11 items-center font-semibold text-sogan underline disabled:text-secondary"
                        :disabled="form.processing"
                        @click="send('draft')"
                    >
                        {{ t(copy, 'payment.draft') }}
                    </button>
                    {{ t(copy, 'payment.draft_after') }}
                </p>
            </StickyAction>
        </template>
    </OnboardingLayout>
</template>
