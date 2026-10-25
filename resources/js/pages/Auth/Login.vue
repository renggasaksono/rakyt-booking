<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../components/FieldError.vue';
import PasswordInput from '../../components/PasswordInput.vue';
import StickyAction from '../../components/StickyAction.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { t } from '../../lib/text.js';

/**
 * "Masuk" (F-001 A4, A5). "Ingat saya" is checked by default.
 */
const props = defineProps({
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
    status: { type: String, default: null },
});

const form = useForm({ email: '', password: '', remember: true });

function submit() {
    if (form.processing) {
        return;
    }

    form.post(props.urls.store, { onError: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t(copy, 'login.title')" />
    <AuthLayout
        :brand="t(copy, 'brand')"
        :suffix="t(copy, 'brand_suffix')"
    >
        <h1 class="mt-6 font-heading text-2xl font-semibold text-ink">
            {{ t(copy, 'login.headline') }}
        </h1>
        <p
            v-if="status"
            class="mt-3 rounded-lg bg-status-paid-tint px-3 py-2 text-sm text-status-paid"
            role="status"
        >
            {{ status }}
        </p>

        <form
            id="login-form"
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <FieldError :message="form.errors.rate_limit" />

            <div>
                <label
                    for="email"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'login.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autocapitalize="none"
                    spellcheck="false"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="form.errors.email ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.email) || undefined"
                    aria-describedby="email-error"
                >
                <FieldError
                    id="email-error"
                    :message="form.errors.email"
                />
            </div>

            <div>
                <label
                    for="password"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'login.password') }}</label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    class="mt-1"
                    autocomplete="current-password"
                    :invalid="Boolean(form.errors.password)"
                    describedby="password-error"
                    :labels="copy.password_toggle"
                />
                <FieldError
                    id="password-error"
                    :message="form.errors.password"
                />
            </div>

            <div class="flex flex-wrap items-center justify-between gap-2">
                <label class="flex min-h-11 items-center gap-3 text-sm text-ink">
                    <input
                        v-model="form.remember"
                        type="checkbox"
                        class="h-5 w-5"
                    >
                    {{ t(copy, 'login.remember') }}
                </label>
                <Link
                    :href="urls.forgot"
                    class="inline-flex min-h-11 items-center text-sm text-sogan underline"
                >
                    {{ t(copy, 'login.forgot') }}
                </Link>
            </div>

            <p class="text-sm text-ink">
                {{ t(copy, 'login.no_account') }}
                <Link
                    :href="urls.register"
                    class="inline-flex min-h-11 items-center font-semibold text-sogan underline"
                >
                    {{ t(copy, 'login.register') }}
                </Link>
            </p>
        </form>

        <StickyAction>
            <button
                type="submit"
                form="login-form"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
                :aria-busy="form.processing"
            >
                {{ t(copy, 'login.submit') }}
            </button>
        </StickyAction>
    </AuthLayout>
</template>
