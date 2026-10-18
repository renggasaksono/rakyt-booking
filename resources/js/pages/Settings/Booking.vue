<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { t } from '../../lib/text.js';

/**
 * Pengaturan → Pemesanan: the default "Tutup pemesanan" for new departures (F-003 Q6).
 */
const props = defineProps({
    settings: { type: Object, required: true },
    options: { type: Array, required: true },
    urls: { type: Object, required: true },
    copy: { type: Object, required: true },
    backLabel: { type: String, required: true },
});

const form = useForm({ default_booking_deadline_preset: props.settings.default_booking_deadline_preset });

function submit() {
    form.put(props.urls.update, { preserveScroll: true });
}
</script>

<template>
    <Head :title="t(copy, 'settings.booking_title')" />
    <SellerLayout>
        <div class="mx-auto max-w-md px-4 py-6">
            <Link
                :href="urls.back"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ backLabel }}
            </Link>
            <p class="text-xs font-semibold uppercase tracking-wide text-secondary">
                {{ t(copy, 'settings.title') }}
            </p>
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'settings.booking_title') }}
            </h1>

            <form
                class="mt-4 rounded-xl bg-surface p-4"
                @submit.prevent="submit"
            >
                <fieldset>
                    <legend class="font-heading text-sm font-semibold text-ink">
                        {{ t(copy, 'settings.default_deadline') }}
                    </legend>
                    <p class="mb-2 text-xs text-secondary">
                        {{ t(copy, 'settings.default_deadline_hint') }}
                    </p>
                    <label
                        v-for="option in options"
                        :key="option"
                        class="flex min-h-11 items-center gap-3 text-sm text-ink"
                    >
                        <input
                            v-model="form.default_booking_deadline_preset"
                            type="radio"
                            name="default_booking_deadline_preset"
                            class="h-5 w-5"
                            :value="option"
                        >
                        {{ t(copy, `deadline.presets.${option}`) }}
                    </label>
                </fieldset>
                <p
                    v-if="form.errors.default_booking_deadline_preset"
                    class="mt-1 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ form.errors.default_booking_deadline_preset }}
                </p>
                <button
                    type="submit"
                    class="mt-4 min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                >
                    {{ t(copy, 'settings.save') }}
                </button>
            </form>
        </div>
    </SellerLayout>
</template>
