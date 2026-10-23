<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../components/FieldError.vue';
import StickyAction from '../../components/StickyAction.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { t } from '../../lib/text.js';

/**
 * Admin TOTP challenge (F-010, ADR-0011). Shown after the admin signs in with
 * email + password, before any /admin page is reachable.
 */
const props = defineProps({
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const form = useForm({ code: '' });

function submit() {
    if (form.processing) {
        return;
    }

    form.post(props.urls.verify, { onError: () => form.reset('code') });
}
</script>

<template>
    <Head :title="t(copy, 'two_factor.title')" />
    <AuthLayout :brand="t(copy, 'brand')">
        <h1 class="mt-6 font-heading text-2xl font-semibold text-ink">
            {{ t(copy, 'two_factor.heading') }}
        </h1>
        <p class="mt-3 text-sm text-secondary">
            {{ t(copy, 'two_factor.intro') }}
        </p>

        <form
            id="admin-2fa-form"
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <FieldError :message="form.errors.rate_limit" />

            <div>
                <label
                    for="code"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'two_factor.code') }}</label>
                <input
                    id="code"
                    v-model="form.code"
                    type="text"
                    inputmode="numeric"
                    autocomplete="one-time-code"
                    autofocus
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base tracking-widest text-ink"
                    :class="form.errors.code ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.code) || undefined"
                    aria-describedby="code-error"
                >
                <FieldError
                    id="code-error"
                    :message="form.errors.code"
                />
            </div>

            <Link
                :href="urls.logout"
                method="post"
                as="button"
                type="button"
                class="inline-flex min-h-11 items-center self-start text-sm text-sogan underline"
            >
                {{ t(copy, 'two_factor.logout') }}
            </Link>
        </form>

        <StickyAction>
            <button
                type="submit"
                form="admin-2fa-form"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
                :aria-busy="form.processing"
            >
                {{ t(copy, 'two_factor.submit') }}
            </button>
        </StickyAction>
    </AuthLayout>
</template>
