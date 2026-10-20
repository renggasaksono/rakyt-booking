<script setup>
import { computed, nextTick, ref, watch } from 'vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import FieldError from '../../components/FieldError.vue';
import PublicFooter from '../../components/public/PublicFooter.vue';
import StickyAction from '../../components/StickyAction.vue';
import Stepper from '../../components/Stepper.vue';
import { clampParticipants, dateLine, meetingLine, sendBooking, totalAmount } from '../../lib/booking.js';
import { formatRupiah } from '../../lib/money.js';
import { formatNational, nationalDigits } from '../../lib/phone.js';
import { t } from '../../lib/text.js';

/**
 * Booking form rakyt.id/<slug>/<trip>/pesan (F-005 section A, mockup 1c).
 * Exactly one gold button: "Kirim pesanan", or "Pilih tanggal lain →" when
 * the date can no longer be booked (AC-3, AC-9, AC-42).
 */
const props = defineProps({
    title: { type: String, required: true },
    organization: { type: Object, required: true },
    trip: { type: Object, required: true },
    departure: { type: Object, required: true },
    meeting: { type: Object, required: true },
    // Prop names are the server's (F-005 Data & rules → Public fields).
    // eslint-disable-next-line vue/prop-name-casing
    submission_key: { type: String, required: true },
    // eslint-disable-next-line vue/prop-name-casing
    hold_minutes: { type: Number, required: true },
    context: { type: Object, required: true },
    copy: { type: Object, required: true },
    calendar: { type: Object, required: true },
    urls: { type: Object, required: true },
    errors: { type: Object, default: () => ({}) },
});

const notesMax = 500;
const notesCounterFrom = 400;

const form = useForm({
    keberangkatan: props.departure.public_id,
    name: '',
    phone: '',
    participants: 1,
    notes: '',
    submission_key: props.submission_key,
    price: props.departure.price,
});

const failed = ref(false);
const bookable = computed(() => props.departure.state === 'bookable');
const seatsLeft = computed(() => props.departure.seats_left ?? 1);
const rupiah = (amount) => `${t(props.copy, 'currency')}${formatRupiah(amount)}`;
const dates = computed(() => dateLine(props.departure.start_date, props.departure.end_date, props.context.today, props.calendar));
const meetingText = computed(() => meetingLine(props.meeting));
const total = computed(() => totalAmount(form.participants ?? 1, props.departure.price));
const holdHours = computed(() => Math.round(props.hold_minutes / 60));

// AC-13, AC-14: after a refusal the page reloads with the server's price and
// seats; the next submit uses them.
watch(() => props.departure.price, (price) => (form.price = price));
watch(seatsLeft, (max) => (form.participants = clampParticipants(form.participants ?? 1, max)));

function onPhoneInput(event) {
    form.phone = event.target.value.replace(/[^\d\s+-]/g, '');
}

function onPhoneBlur() {
    const digits = nationalDigits(form.phone);
    form.phone = digits === '' ? form.phone.trim() : formatNational(digits);
}

// AC-10: the page scrolls to the first field with an error.
async function focusFirstError() {
    await nextTick();
    const field = document.querySelector('[aria-invalid="true"]');

    field?.scrollIntoView({ block: 'center', behavior: 'smooth' });
    field?.focus({ preventScroll: true });
}

function submit() {
    failed.value = false;
    sendBooking(form, props.urls.store, {
        bookable: bookable.value,
        phoneDigits: nationalDigits,
        onFail: () => (failed.value = true),
        onError: focusFirstError,
    });
}
</script>

<template>
    <Head :title="title" />
    <main
        class="mx-auto max-w-md px-4"
        :class="bookable ? 'pb-72' : 'pb-32'"
    >
        <nav class="flex h-14 items-center">
            <Link
                :href="urls.trip"
                class="-ml-3 inline-flex min-h-11 min-w-11 items-center justify-center rounded-lg text-xl text-ink"
                :aria-label="t(copy, 'form.back', { trip: trip.name })"
            >
                <span aria-hidden="true">←</span>
            </Link>
            <span class="ml-1 truncate text-sm text-secondary">{{ organization.name }}</span>
        </nav>

        <header>
            <p class="font-heading text-[11px] font-semibold uppercase tracking-[.14em] text-sogan">
                {{ t(copy, 'form.kicker') }}
            </p>
            <h1 class="mt-1 font-heading text-2xl font-semibold leading-tight text-ink">
                {{ t(copy, 'form.title') }}
            </h1>
        </header>

        <section class="mt-4 border border-ink/15 bg-surface p-3">
            <p class="font-heading text-base font-semibold text-ink [overflow-wrap:anywhere]">
                {{ trip.name }}
            </p>
            <p class="mt-0.5 text-sm text-secondary [overflow-wrap:anywhere]">
                {{ [dates, meetingText].filter(Boolean).join(' · ') }}
            </p>
        </section>

        <section
            v-if="!bookable"
            class="mt-6"
            role="status"
        >
            <p class="text-base text-ink">
                {{ t(copy, 'form.unavailable') }}
            </p>
            <p class="mt-2 inline-flex items-center gap-1 rounded-full bg-status-cancelled-tint px-2 py-0.5 text-xs font-semibold text-status-cancelled">
                <span aria-hidden="true">{{ departure.state === 'full' ? '◼' : '○' }}</span>
                {{ t(copy, departure.state === 'full' ? 'form.reasons.full' : 'form.reasons.closed') }}
            </p>
        </section>

        <form
            v-else
            id="booking-form"
            class="mt-6 flex flex-col gap-5"
            novalidate
            @submit.prevent="submit"
        >
            <div>
                <label
                    for="name"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'form.name') }}</label>
                <input
                    id="name"
                    v-model="form.name"
                    type="text"
                    autocomplete="name"
                    maxlength="80"
                    class="mt-1 min-h-12 w-full border bg-surface px-3 text-base text-ink focus:outline-none focus:shadow-[inset_0_0_0_2px_#6e4527]"
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
                    for="phone"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'form.phone') }}</label>
                <div
                    class="mt-1 flex min-h-12 items-stretch overflow-hidden border bg-surface focus-within:shadow-[inset_0_0_0_2px_#6e4527]"
                    :class="form.errors.phone ? 'border-status-rejected' : 'border-ink/20'"
                >
                    <span
                        class="flex items-center border-r border-ink/15 px-3 text-base text-secondary"
                        aria-hidden="true"
                    >{{ t(copy, 'form.phone_prefix') }}</span>
                    <input
                        id="phone"
                        :value="form.phone"
                        type="tel"
                        inputmode="tel"
                        autocomplete="tel-national"
                        class="min-w-0 flex-1 bg-transparent px-3 text-base text-ink focus:outline-none"
                        :aria-invalid="Boolean(form.errors.phone) || undefined"
                        aria-describedby="phone-help phone-error"
                        @input="onPhoneInput"
                        @blur="onPhoneBlur"
                    >
                </div>
                <p
                    id="phone-help"
                    class="mt-1 text-xs text-secondary"
                >
                    {{ t(copy, 'form.phone_help') }}
                </p>
                <FieldError
                    id="phone-error"
                    :message="form.errors.phone"
                />
            </div>

            <div>
                <label
                    for="participants"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'form.participants') }}</label>
                <div class="mt-1 flex items-center gap-3">
                    <Stepper
                        id="participants"
                        v-model="form.participants"
                        :min="1"
                        :max="seatsLeft"
                        :decrease-label="t(copy, 'form.decrease')"
                        :increase-label="t(copy, 'form.increase')"
                        describedby="participants-help participants-error"
                        :aria-invalid="Boolean(form.errors.participants) || undefined"
                    />
                    <p
                        id="participants-help"
                        class="text-xs text-secondary"
                    >
                        {{ t(copy, 'form.participants_help', { n: seatsLeft }) }}
                    </p>
                </div>
                <FieldError
                    id="participants-error"
                    :message="form.errors.participants"
                />
            </div>

            <div>
                <label
                    for="notes"
                    class="font-heading text-sm font-semibold text-ink"
                >{{ t(copy, 'form.notes') }}</label>
                <textarea
                    id="notes"
                    v-model="form.notes"
                    rows="3"
                    :maxlength="notesMax"
                    class="mt-1 w-full border bg-surface px-3 py-2 text-base text-ink focus:outline-none focus:shadow-[inset_0_0_0_2px_#6e4527]"
                    :class="form.errors.notes ? 'border-status-rejected' : 'border-ink/20'"
                    :aria-invalid="Boolean(form.errors.notes) || undefined"
                    aria-describedby="notes-counter notes-error"
                />
                <p
                    v-if="form.notes.length >= notesCounterFrom"
                    id="notes-counter"
                    class="mt-1 text-right text-xs text-secondary"
                    aria-live="polite"
                >
                    {{ t(copy, 'form.notes_counter', { n: form.notes.length, max: notesMax }) }}
                </p>
                <FieldError
                    id="notes-error"
                    :message="form.errors.notes"
                />
            </div>

            <FieldError :message="form.errors.price" />
            <FieldError :message="form.errors.rate_limit" />
            <FieldError :message="form.errors.departure" />
            <FieldError :message="failed ? t(copy, 'form.network_error') : null" />
        </form>

        <PublicFooter
            :label="t(copy, 'footer')"
            :href="urls.home"
        />
    </main>

    <StickyAction>
        <template v-if="bookable">
            <div class="flex items-baseline justify-between gap-3">
                <p class="text-xs text-secondary">
                    {{ t(copy, 'form.line', { n: form.participants ?? 1, price: rupiah(departure.price) }) }}
                </p>
                <p class="font-heading text-lg font-semibold text-ink">
                    <span class="sr-only">{{ t(copy, 'form.total') }}</span>
                    {{ rupiah(total) }}
                </p>
            </div>
            <p class="mt-1 text-xs text-ink">
                {{ t(copy, 'form.hold', { hours: holdHours }) }}
            </p>
            <button
                type="submit"
                form="booking-form"
                class="mt-2 min-h-12 w-full bg-prada px-4 font-heading text-base font-semibold text-ink disabled:bg-prada/40"
                :disabled="form.processing"
                :aria-busy="form.processing"
            >
                {{ form.processing ? t(copy, 'form.sending') : t(copy, 'form.submit') }}
            </button>
            <p class="mt-2 text-center text-xs text-secondary">
                {{ t(copy, 'form.privacy', { name: organization.name }) }}
                <a
                    :href="urls.privacy"
                    class="-my-3 inline-block py-3 text-ink underline underline-offset-2"
                >{{ t(copy, 'form.privacy_link') }}</a>
            </p>
        </template>
        <a
            v-else
            :href="urls.trip"
            class="inline-flex min-h-12 w-full items-center justify-center bg-prada px-4 font-heading text-base font-semibold text-ink"
        >
            {{ t(copy, 'form.other_date') }}
        </a>
    </StickyAction>
</template>
