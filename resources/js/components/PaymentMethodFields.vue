<script setup>
import { computed } from 'vue';
import { accountNumberInput, blankPaymentBlock } from '../lib/paymentMethods.js';
import { t } from '../lib/text.js';
import FieldError from './FieldError.vue';

/**
 * Bank / e-wallet blocks (F-001 C10–C11): onboarding step 4 and
 * Pengaturan → Rekening (F5). `copy` is lang/id/onboarding.php → payment.
 */
const props = defineProps({
    providers: { type: Array, required: true },
    limits: { type: Object, required: true },
    errors: { type: Object, required: true },
    copy: { type: Object, required: true },
    highlight: { type: Boolean, default: false },
});

const methods = defineModel('methods', { type: Array, required: true });

const canAdd = computed(() => methods.value.length < props.limits.methods);
const providerByValue = computed(() => Object.fromEntries(props.providers.map((provider) => [provider.value, provider])));

function errorFor(index, field) {
    return props.errors[`methods.${index}.${field}`] ?? null;
}

function numberLabel(method) {
    return providerByValue.value[method.provider]?.type === 'ewallet'
        ? t(props.copy, 'account_number_ewallet')
        : t(props.copy, 'account_number');
}

function onNumberInput(method, event) {
    method.account_number = accountNumberInput(event.target.value);
    event.target.value = method.account_number;
}

function add() {
    if (canAdd.value) {
        methods.value.push(blankPaymentBlock());
    }
}

function remove(index) {
    methods.value.splice(index, 1);
}
</script>

<template>
    <div class="flex flex-col gap-4">
        <fieldset
            v-for="(method, index) in methods"
            :key="method.key"
            class="rounded-xl border bg-surface p-4"
            :class="highlight ? 'border-status-rejected' : 'border-ink/10'"
        >
            <legend class="sr-only">
                {{ t(copy, 'account_n', { n: index + 1 }) }}
            </legend>
            <div class="flex items-center justify-between">
                <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                    {{ t(copy, 'account_n', { n: index + 1 }) }}
                </p>
                <button
                    v-if="methods.length > 1"
                    type="button"
                    class="min-h-11 px-2 text-sm text-status-rejected underline"
                    @click="remove(index)"
                >
                    {{ t(copy, 'remove') }}
                </button>
            </div>

            <label
                :for="`provider-${method.key}`"
                class="mt-2 block font-heading text-sm font-semibold text-ink"
            >{{ t(copy, 'provider') }}</label>
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
                    {{ t(copy, 'provider_placeholder') }}
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
                >{{ t(copy, 'provider_name') }}</label>
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
            >{{ t(copy, 'account_holder') }}</label>
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
                {{ t(copy, 'account_holder_hint') }}
            </p>
            <FieldError :message="errorFor(index, 'account_holder')" />
        </fieldset>

        <FieldError :message="errors.methods" />

        <div>
            <button
                v-if="canAdd"
                type="button"
                class="min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-sm font-semibold text-ink"
                @click="add"
            >
                {{ t(copy, 'add') }}
            </button>
            <p
                v-else
                class="text-xs text-secondary"
            >
                {{ t(copy, 'max', { max: limits.methods }) }}
            </p>
        </div>
    </div>
</template>
