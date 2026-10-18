<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import FieldError from '../../components/FieldError.vue';
import PaymentMethodFields from '../../components/PaymentMethodFields.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { saveFailureHandlers } from '../../lib/http.js';
import { paymentBlocks, paymentPayload } from '../../lib/paymentMethods.js';
import { t } from '../../lib/text.js';

/**
 * Pengaturan → Rekening (F-001 F5): the bank / e-wallet accounts customers
 * transfer to, with the onboarding step 4 fields. Money goes straight to the
 * seller (ADR-0003).
 */
const props = defineProps({
    methods: { type: Array, required: true },
    providers: { type: Array, required: true },
    limits: { type: Object, required: true },
    urls: { type: Object, required: true },
    backLabel: { type: String, required: true },
    copy: { type: Object, required: true },
    fields: { type: Object, required: true },
});

const form = useForm({ methods: paymentBlocks(props.methods) });
const failed = ref(false);

function submit() {
    if (form.processing) {
        return;
    }

    failed.value = false;
    form.transform((data) => ({ methods: paymentPayload(data.methods) })).put(props.urls.update, {
        preserveScroll: true,
        // Saved accounts come back with their ids, so a second save updates them.
        onSuccess: (page) => (form.methods = paymentBlocks(page.props.methods)),
        ...saveFailureHandlers(() => (failed.value = true)),
    });
}
</script>

<template>
    <Head :title="t(copy, 'title')" />
    <SellerLayout>
        <div class="mx-auto max-w-md px-4 py-6">
            <Link
                :href="urls.back"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ backLabel }}
            </Link>
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                {{ t(copy, 'kicker') }}
            </p>
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'title') }}
            </h1>
            <p class="mt-2 rounded-lg bg-status-paid-tint px-3 py-2 text-sm text-status-paid">
                {{ t(copy, 'note') }}
            </p>

            <form
                class="mt-4"
                novalidate
                @submit.prevent="submit"
            >
                <PaymentMethodFields
                    v-model:methods="form.methods"
                    :providers="providers"
                    :limits="limits"
                    :errors="form.errors"
                    :copy="fields"
                />
                <FieldError :message="failed ? t(copy, 'save_failed') : null" />
                <button
                    type="submit"
                    class="mt-4 min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                    :aria-busy="form.processing"
                >
                    {{ form.processing ? t(copy, 'saving') : t(copy, 'save') }}
                </button>
            </form>
        </div>
    </SellerLayout>
</template>
