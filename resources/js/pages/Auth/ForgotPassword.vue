<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../components/FieldError.vue';
import StickyAction from '../../components/StickyAction.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { t } from '../../lib/text.js';

/**
 * "Lupa password?" (F-001 A7): the same answer whether or not the email has an account.
 */
const props = defineProps({
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
    status: { type: String, default: null },
});

const form = useForm({ email: '' });

function submit() {
    if (!form.processing) {
        form.post(props.urls.store, { preserveScroll: true });
    }
}
</script>

<template>
    <Head :title="t(copy, 'forgot.title')" />
    <AuthLayout :brand="t(copy, 'brand')">
        <h1 class="mt-6 font-heading text-2xl font-semibold text-ink">
            {{ t(copy, 'forgot.headline') }}
        </h1>
        <p class="mt-2 text-base text-secondary">
            {{ t(copy, 'forgot.intro') }}
        </p>
        <p
            v-if="status"
            class="mt-4 rounded-lg bg-status-paid-tint px-3 py-2 text-sm text-status-paid"
            role="status"
        >
            {{ status }}
        </p>

        <form
            id="forgot-form"
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <FieldError :message="form.errors.rate_limit" />
            <div>
                <label
                    for="email"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'forgot.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autocapitalize="none"
                    spellcheck="false"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="form.errors.email ? 'border-status-rejected' : 'border-ink/20'"
                    aria-describedby="email-error"
                >
                <FieldError
                    id="email-error"
                    :message="form.errors.email"
                />
            </div>
            <Link
                :href="urls.login"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ t(copy, 'forgot.back') }}
            </Link>
        </form>

        <StickyAction>
            <button
                type="submit"
                form="forgot-form"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
            >
                {{ t(copy, 'forgot.submit') }}
            </button>
        </StickyAction>
    </AuthLayout>
</template>
