<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import { h } from 'vue';
import FieldError from '../../components/FieldError.vue';
import FlashMessages from '../../components/FlashMessages.vue';
import PasswordInput from '../../components/PasswordInput.vue';
import VerificationBanner from '../../components/VerificationBanner.vue';
import SellerLayout from '../../layouts/SellerLayout.vue';
import { t } from '../../lib/text.js';

/**
 * Pengaturan → Akun (F-001 A11, A12, F3). Both changes ask for the current
 * password. Past onboarding it sits in the seller layout like the other
 * Pengaturan pages; during onboarding (no seller nav yet) it keeps a bare
 * frame. "Keluar" lives on Pengaturan and in the onboarding header (A6).
 */
const props = defineProps({
    copy: { type: Object, required: true },
    account: { type: Object, required: true },
    urls: { type: Object, required: true },
    limits: { type: Object, required: true },
    backLabel: { type: String, required: true },
    inApp: { type: Boolean, required: true },
});

const BareFrame = (_, { slots }) => h('div', { class: 'min-h-screen bg-gading' }, [h(VerificationBanner), slots.default?.(), h(FlashMessages)]);

const emailForm = useForm({ email: '', current_password: '' });
const passwordForm = useForm({ current_password: '', password: '' });

function changeEmail() {
    if (!emailForm.processing) {
        emailForm.put(props.urls.email, { preserveScroll: true, onSuccess: () => emailForm.reset(), onError: () => emailForm.reset('current_password') });
    }
}

function changePassword() {
    if (!passwordForm.processing) {
        passwordForm.put(props.urls.password, { preserveScroll: true, onFinish: () => passwordForm.reset() });
    }
}
</script>

<template>
    <Head :title="t(copy, 'settings.title')" />
    <component :is="inApp ? SellerLayout : BareFrame">
        <main class="mx-auto max-w-md px-4 py-6">
            <Link
                :href="urls.back"
                class="inline-flex min-h-11 items-center text-sm text-sogan underline"
            >
                {{ backLabel }}
            </Link>
            <p class="text-[11px] font-semibold uppercase tracking-wide text-secondary">
                {{ t(copy, 'settings.kicker') }}
            </p>
            <h1 class="font-heading text-xl font-semibold text-ink">
                {{ t(copy, 'settings.title') }}
            </h1>

            <form
                class="mt-4 rounded-xl bg-surface p-4"
                novalidate
                @submit.prevent="changeEmail"
            >
                <h2 class="font-heading text-base font-semibold text-ink">
                    {{ t(copy, 'settings.email_section') }}
                </h2>
                <p class="mt-1 text-sm text-ink">
                    {{ t(copy, 'settings.current_email') }}: <span class="font-semibold">{{ account.email }}</span>
                </p>
                <p
                    class="text-xs"
                    :class="account.verified ? 'text-status-paid' : 'text-status-waiting'"
                >
                    {{ account.verified ? t(copy, 'settings.verified') : `◷ ${t(copy, 'settings.unverified')}` }}
                </p>

                <label
                    for="new-email"
                    class="mt-3 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'settings.new_email') }}</label>
                <input
                    id="new-email"
                    v-model="emailForm.email"
                    type="email"
                    autocomplete="email"
                    autocapitalize="none"
                    spellcheck="false"
                    class="mt-1 min-h-12 w-full rounded-lg border bg-surface px-3 text-base text-ink"
                    :class="emailForm.errors.email ? 'border-status-rejected' : 'border-ink/20'"
                >
                <FieldError :message="emailForm.errors.email" />

                <label
                    for="email-current-password"
                    class="mt-3 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'settings.current_password') }}</label>
                <PasswordInput
                    id="email-current-password"
                    v-model="emailForm.current_password"
                    class="mt-1"
                    autocomplete="current-password"
                    :invalid="Boolean(emailForm.errors.current_password)"
                    :labels="copy.password_toggle"
                />
                <FieldError :message="emailForm.errors.current_password ?? emailForm.errors.rate_limit" />

                <button
                    type="submit"
                    class="mt-4 min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink disabled:border-ink/30 disabled:text-secondary"
                    :disabled="emailForm.processing"
                >
                    {{ t(copy, 'settings.save_email') }}
                </button>
            </form>

            <form
                class="mt-4 rounded-xl bg-surface p-4"
                novalidate
                @submit.prevent="changePassword"
            >
                <h2 class="font-heading text-base font-semibold text-ink">
                    {{ t(copy, 'settings.password_section') }}
                </h2>
                <!-- Lets password managers pair the new password with the account. -->
                <input
                    type="email"
                    autocomplete="username"
                    :value="account.email"
                    class="sr-only"
                    tabindex="-1"
                    aria-hidden="true"
                    readonly
                >
                <label
                    for="password-current"
                    class="mt-3 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'settings.current_password') }}</label>
                <PasswordInput
                    id="password-current"
                    v-model="passwordForm.current_password"
                    class="mt-1"
                    autocomplete="current-password"
                    :invalid="Boolean(passwordForm.errors.current_password)"
                    :labels="copy.password_toggle"
                />
                <FieldError :message="passwordForm.errors.current_password" />

                <label
                    for="password-new"
                    class="mt-3 block font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'settings.new_password') }}</label>
                <PasswordInput
                    id="password-new"
                    v-model="passwordForm.password"
                    class="mt-1"
                    autocomplete="new-password"
                    :invalid="Boolean(passwordForm.errors.password)"
                    describedby="password-new-hint"
                    :labels="copy.password_toggle"
                />
                <p
                    id="password-new-hint"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'register.password_hint') }}
                </p>
                <FieldError :message="passwordForm.errors.password ?? passwordForm.errors.rate_limit" />

                <button
                    type="submit"
                    class="mt-4 min-h-12 w-full rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink disabled:border-ink/30 disabled:text-secondary"
                    :disabled="passwordForm.processing"
                >
                    {{ t(copy, 'settings.save_password') }}
                </button>
            </form>
        </main>
    </component>
</template>
