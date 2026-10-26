<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { computed } from 'vue';
import FieldError from '../../components/FieldError.vue';
import PasswordInput from '../../components/PasswordInput.vue';
import StickyAction from '../../components/StickyAction.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { t } from '../../lib/text.js';

/**
 * "Daftar" (F-001 A1–A3; layout and headline from mockup 4a, email +
 * password per ADR-0012). No step header (C1).
 */
const props = defineProps({
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
    limits: { type: Object, required: true },
});

const form = useForm({ name: '', email: '', password: '', terms: false });

const emailTaken = computed(() => form.errors.email === t(props.copy, 'register.email_taken'));

function submit() {
    if (form.processing) {
        return;
    }

    // A2: everything but the password stays filled in after an error.
    form.post(props.urls.store, { onError: () => form.reset('password') });
}
</script>

<template>
    <Head :title="t(copy, 'register.title')" />
    <AuthLayout
        :brand="t(copy, 'brand')"
        :suffix="t(copy, 'brand_suffix')"
    >
        <h1 class="mt-6 font-heading text-2xl font-semibold leading-tight text-ink">
            {{ t(copy, 'register.headline') }}
        </h1>
        <p class="mt-2 text-base text-secondary">
            {{ t(copy, 'register.subline') }}
        </p>

        <form
            id="register-form"
            class="mt-6 flex flex-col gap-4"
            novalidate
            @submit.prevent="submit"
        >
            <FieldError
                id="rate-limit"
                :message="form.errors.rate_limit"
            />

            <div>
                <label
                    for="name"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'register.name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    :maxlength="limits.name_max"
                    :placeholder="t(copy, 'register.name_placeholder')"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="form.errors.name ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.name) || undefined"
                    aria-describedby="name-error"
                >
                <FieldError
                    id="name-error"
                    :message="form.errors.name"
                />
            </div>

            <div>
                <label
                    for="email"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'register.email') }}</label>
                <input
                    id="email"
                    v-model="form.email"
                    type="email"
                    autocomplete="email"
                    autocapitalize="none"
                    spellcheck="false"
                    :placeholder="t(copy, 'register.email_placeholder')"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="form.errors.email ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.email) || undefined"
                    aria-describedby="email-error"
                >
                <p
                    v-if="emailTaken"
                    id="email-error"
                    class="mt-1 text-xs text-status-rejected"
                    role="alert"
                >
                    {{ form.errors.email }}
                    <Link
                        :href="urls.login"
                        class="inline-flex min-h-11 items-center font-semibold underline"
                    >
                        {{ t(copy, 'register.email_taken_login') }}
                    </Link>
                    ·
                    <Link
                        :href="urls.reset"
                        class="inline-flex min-h-11 items-center font-semibold underline"
                    >
                        {{ t(copy, 'register.email_taken_reset') }}
                    </Link>
                </p>
                <FieldError
                    v-else
                    id="email-error"
                    :message="form.errors.email"
                />
            </div>

            <div>
                <label
                    for="password"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'register.password') }}</label>
                <PasswordInput
                    id="password"
                    v-model="form.password"
                    class="mt-1"
                    autocomplete="new-password"
                    :invalid="Boolean(form.errors.password)"
                    describedby="password-hint password-error"
                    :labels="copy.password_toggle"
                />
                <p
                    id="password-hint"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'register.password_hint') }}
                </p>
                <FieldError
                    id="password-error"
                    :message="form.errors.password"
                />
            </div>

            <div>
                <label class="flex min-h-11 items-start gap-3 text-sm text-ink">
                    <input
                        v-model="form.terms"
                        type="checkbox"
                        required
                        class="mt-0.5 h-5 w-5 shrink-0"
                        :aria-invalid="Boolean(form.errors.terms) || undefined"
                        aria-describedby="terms-error"
                    >
                    <span>
                        {{ t(copy, 'register.terms_before') }}
                        <a
                            :href="urls.terms"
                            target="_blank"
                            rel="noopener"
                            class="text-sogan underline"
                        >{{ t(copy, 'register.terms') }}</a>
                        {{ t(copy, 'register.terms_and') }}
                        <a
                            :href="urls.privacy"
                            target="_blank"
                            rel="noopener"
                            class="text-sogan underline"
                        >{{ t(copy, 'register.privacy') }}</a>
                    </span>
                </label>
                <FieldError
                    id="terms-error"
                    :message="form.errors.terms"
                />
            </div>

            <p class="text-sm text-secondary">
                {{ t(copy, 'register.free') }}
            </p>
            <p class="text-sm text-ink">
                {{ t(copy, 'register.have_account') }}
                <Link
                    :href="urls.login"
                    class="inline-flex min-h-11 items-center font-semibold text-sogan underline"
                >
                    {{ t(copy, 'register.login') }}
                </Link>
            </p>
        </form>

        <StickyAction>
            <button
                type="submit"
                form="register-form"
                class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
                :aria-busy="form.processing"
            >
                {{ t(copy, 'register.submit') }}
            </button>
        </StickyAction>
    </AuthLayout>
</template>
