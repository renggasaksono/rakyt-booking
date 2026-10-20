<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed, ref } from 'vue';
import FieldError from '../../components/FieldError.vue';
import PaymentMethodFields from '../../components/PaymentMethodFields.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { saveFailureHandlers } from '../../lib/http.js';
import { paymentBlocks, paymentPayload } from '../../lib/paymentMethods.js';
import { t } from '../../lib/text.js';

/**
 * Pengaturan → Pembayaran (F-006, supersedes F-001's Rekening): bank / e-wallet
 * accounts and the QRIS image customers transfer to. Money goes straight to the
 * seller (ADR-0003). Changes are blocked for 24 hours after account recovery
 * (ADR-0018) — the form is read-only then (AC-25).
 */
const props = defineProps({
    methods: { type: Array, required: true },
    qris: { type: Object, required: true },
    providers: { type: Array, required: true },
    limits: { type: Object, required: true },
    urls: { type: Object, required: true },
    lockedUntil: { type: String, default: null },
    context: { type: Object, required: true },
    backLabel: { type: String, required: true },
    copy: { type: Object, required: true },
    fields: { type: Object, required: true },
});

const locked = computed(
    () => props.lockedUntil !== null && new Date(props.lockedUntil) > new Date(props.context.now),
);

const lockLabel = computed(() => {
    if (!locked.value) {
        return '';
    }
    const date = new Intl.DateTimeFormat('id-ID', {
        timeZone: props.context.timezone,
        day: 'numeric',
        month: 'short',
        hour: '2-digit',
        minute: '2-digit',
    }).format(new Date(props.lockedUntil));

    return t(props.copy, 'locked').replace(':time', date);
});

const form = useForm({ methods: paymentBlocks(props.methods), qris: null, remove_qris: false });
const failed = ref(false);
const fileInput = ref(null);
const newPreview = ref(null);
const hasQris = computed(() => (props.qris.exists && !form.remove_qris) || newPreview.value !== null);
const qrisSrc = computed(() => newPreview.value ?? props.qris.image_url);

function pickQris(event) {
    const file = event.target.files?.[0] ?? null;
    form.qris = file;
    form.remove_qris = false;
    newPreview.value = file ? URL.createObjectURL(file) : null;
}

function removeQris() {
    form.qris = null;
    newPreview.value = null;
    form.remove_qris = true;
    if (fileInput.value) {
        fileInput.value.value = '';
    }
}

function submit() {
    if (form.processing || locked.value) {
        return;
    }

    failed.value = false;
    form.transform((data) => ({
        methods: paymentPayload(data.methods),
        qris: data.qris,
        remove_qris: data.remove_qris ? 1 : 0,
    })).post(props.urls.update, {
        preserveScroll: true,
        forceFormData: true,
        onSuccess: (page) => {
            form.methods = paymentBlocks(page.props.methods);
            form.qris = null;
            form.remove_qris = false;
            newPreview.value = null;
        },
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

            <p
                v-if="locked"
                class="mt-3 rounded-lg bg-status-waiting-tint px-3 py-2 text-sm text-status-waiting"
                role="status"
            >
                {{ lockLabel }}
            </p>

            <form
                class="mt-4"
                novalidate
                @submit.prevent="submit"
            >
                <fieldset
                    :disabled="locked"
                    class="border-0 p-0"
                >
                    <h2 class="font-heading text-base font-semibold text-ink">
                        {{ t(copy, 'bank_title') }}
                    </h2>
                    <PaymentMethodFields
                        v-model:methods="form.methods"
                        class="mt-2"
                        :providers="providers"
                        :limits="limits"
                        :errors="form.errors"
                        :copy="fields"
                    />
                    <FieldError :message="form.errors.methods" />

                    <h2 class="mt-6 font-heading text-base font-semibold text-ink">
                        {{ t(copy, 'qris_title') }}
                    </h2>
                    <p class="mt-1 text-sm text-secondary">
                        {{ t(copy, 'qris_hint') }}
                    </p>

                    <div
                        v-if="hasQris"
                        class="mt-3"
                    >
                        <img
                            :src="qrisSrc"
                            :alt="t(copy, 'qris_preview_alt')"
                            class="h-40 w-40 rounded-lg border border-ink/10 object-contain"
                        >
                        <div class="mt-2 flex gap-3">
                            <button
                                type="button"
                                class="min-h-11 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
                                @click="fileInput?.click()"
                            >
                                {{ t(copy, 'qris_replace') }}
                            </button>
                            <button
                                type="button"
                                class="min-h-11 rounded-lg border-2 border-status-rejected px-3 font-heading text-sm font-semibold text-status-rejected"
                                @click="removeQris"
                            >
                                {{ t(copy, 'qris_remove') }}
                            </button>
                        </div>
                    </div>
                    <button
                        v-else
                        type="button"
                        class="mt-3 min-h-11 rounded-lg border-2 border-ink px-3 font-heading text-sm font-semibold text-ink"
                        @click="fileInput?.click()"
                    >
                        {{ t(copy, 'qris_upload') }}
                    </button>
                    <input
                        ref="fileInput"
                        type="file"
                        accept="image/*"
                        class="sr-only"
                        @change="pickQris"
                    >
                    <FieldError :message="form.errors.qris" />
                </fieldset>

                <FieldError :message="failed ? t(copy, 'save_failed') : null" />
                <button
                    type="submit"
                    class="mt-4 min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing || locked"
                    :aria-busy="form.processing"
                >
                    {{ form.processing ? t(copy, 'saving') : t(copy, 'save') }}
                </button>
            </form>
        </div>
    </SellerLayout>
</template>
