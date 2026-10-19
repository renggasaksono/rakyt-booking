<script setup>
import { Head, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FieldError from '../../components/FieldError.vue';
import PaymentMethodFields from '../../components/PaymentMethodFields.vue';
import StickyAction from '../../components/StickyAction.vue';
import OnboardingLayout from '../../layouts/OnboardingLayout.vue';
import { saveFailureHandlers } from '../../lib/http.js';
import { paymentBlocks, paymentPayload } from '../../lib/paymentMethods.js';
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

const form = useForm({
    intent: 'publish',
    methods: paymentBlocks(props.methods),
});

const failed = ref(false);

function send(intent) {
    if (form.processing) {
        return;
    }

    failed.value = false;
    form.intent = intent;
    form.transform((data) => ({
        intent: data.intent,
        methods: paymentPayload(data.methods),
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
            <PaymentMethodFields
                v-model:methods="form.methods"
                :providers="providers"
                :limits="limits"
                :errors="form.errors"
                :copy="copy.payment"
                :highlight="highlight === 'payment_instructions'"
            />
            <p class="-mt-3 text-xs text-secondary">
                {{ t(copy, 'payment.qris_later') }}
            </p>

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
