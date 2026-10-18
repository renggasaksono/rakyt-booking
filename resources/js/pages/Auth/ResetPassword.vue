<script setup>
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../components/FieldError.vue';
import PasswordInput from '../../components/PasswordInput.vue';
import StickyAction from '../../components/StickyAction.vue';
import AuthLayout from '../../layouts/AuthLayout.vue';
import { t } from '../../lib/text.js';

/**
 * New password from the emailed link (F-001 A8, A9). An expired, used or
 * tampered link shows "Link sudah tidak berlaku" with a way to ask again.
 */
const props = defineProps({
    copy: { type: Object, required: true },
    valid: { type: Boolean, required: true },
    token: { type: String, default: '' },
    email: { type: String, default: '' },
    urls: { type: Object, required: true },
    limits: { type: Object, required: true },
});

const form = useForm({ token: props.token, email: props.email, password: '' });

function submit() {
    if (!form.processing) {
        form.post(props.urls.store, { onError: () => form.reset('password') });
    }
}
</script>

<template>
    <Head :title="t(copy, 'reset.title')" />
    <AuthLayout :brand="t(copy, 'brand')">
        <h1 class="mt-6 font-heading text-2xl font-semibold text-ink">
            {{ t(copy, 'reset.headline') }}
        </h1>

        <template v-if="!valid || form.errors.token">
            <p
                class="mt-4 rounded-lg bg-status-rejected-tint px-3 py-2 text-sm text-status-rejected"
                role="alert"
            >
                ✕ {{ t(copy, 'reset.invalid') }}
            </p>
            <Link
                :href="urls.request"
                class="mt-4 inline-flex min-h-12 items-center justify-center rounded-lg border-2 border-ink px-4 font-heading text-base font-semibold text-ink"
            >
                {{ t(copy, 'reset.request_new') }}
            </Link>
        </template>

        <template v-else>
            <form
                id="reset-form"
                class="mt-6 flex flex-col gap-4"
                novalidate
                @submit.prevent="submit"
            >
                <FieldError :message="form.errors.rate_limit" />
                <div>
                    <label
                        for="email"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'reset.email') }}</label>
                    <input
                        id="email"
                        :value="form.email"
                        type="email"
                        autocomplete="username"
                        readonly
                        class="mt-1 min-h-12 w-full rounded-lg border border-ink/10 bg-gading px-3 text-base text-secondary"
                    >
                </div>
                <div>
                    <label
                        for="password"
                        class="font-heading text-sm font-semibold text-ink"
                    >{{ t(copy, 'reset.password') }}</label>
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
                        {{ t(copy, 'reset.password_hint') }}
                    </p>
                    <FieldError
                        id="password-error"
                        :message="form.errors.password"
                    />
                </div>
            </form>

            <StickyAction>
                <button
                    type="submit"
                    form="reset-form"
                    class="min-h-12 w-full rounded-lg bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                    :disabled="form.processing"
                >
                    {{ t(copy, 'reset.submit') }}
                </button>
            </StickyAction>
        </template>
    </AuthLayout>
</template>
