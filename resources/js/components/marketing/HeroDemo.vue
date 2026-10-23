<script setup>
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { createAutoplay, formatCountdown, remainingHold } from '../../lib/landingDemo.js';

/**
 * Interactive 3-step product demo (F-017 AC-16, AC-17): "Lihat trip → Pesan →
 * Bayar" with the 2-hour seat-hold countdown on the payment step. Autoplay
 * advances the steps, pauses for good on any manual interaction, and never runs
 * when the visitor prefers reduced motion. All pure logic is in
 * lib/landingDemo.js; this component owns the timers and markup.
 */
defineProps({
    demo: { type: Object, required: true },
});

const step = ref(0);
const paused = ref(false);
const reduced = ref(false);
const holdElapsed = ref(0);

let autoplay = null;
let stepTimer = null;
let tickTimer = null;

const countdown = computed(() => formatCountdown(remainingHold(holdElapsed.value)));

function startStepTimer() {
    stopStepTimer();

    if (autoplay?.active) {
        stepTimer = window.setInterval(() => {
            step.value = autoplay.tick(step.value);
        }, autoplay.intervalMs);
    }
}

function stopStepTimer() {
    if (stepTimer) {
        window.clearInterval(stepTimer);
        stepTimer = null;
    }
}

// Any tap on a tab is a manual interaction: stop autoplay for good (AC-17).
function goTo(index) {
    step.value = index;
    autoplay?.pause();
    paused.value = true;
    stopStepTimer();
}

function togglePlay() {
    if (paused.value) {
        autoplay?.resume();
        paused.value = autoplay?.paused ?? true;
        startStepTimer();
    } else {
        autoplay?.pause();
        paused.value = true;
        stopStepTimer();
    }
}

onMounted(() => {
    reduced.value = window.matchMedia?.('(prefers-reduced-motion: reduce)')?.matches ?? false;
    autoplay = createAutoplay({ reducedMotion: reduced.value });
    paused.value = autoplay.paused;
    startStepTimer();

    // The countdown is informative, not decorative motion; still, hold it steady
    // when the visitor prefers reduced motion.
    if (!reduced.value) {
        tickTimer = window.setInterval(() => {
            holdElapsed.value += 1;
        }, 1000);
    }
});

onBeforeUnmount(() => {
    stopStepTimer();

    if (tickTimer) {
        window.clearInterval(tickTimer);
    }
});

const cardBase = 'bg-surface p-3 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]';
</script>

<template>
    <figure
        class="w-full"
        :aria-label="demo.aria"
    >
        <!-- Tabs double as the step indicator and as manual controls. -->
        <div class="flex items-center gap-1 bg-ink/5 p-1">
            <button
                v-for="(label, index) in demo.tabs"
                :key="index"
                type="button"
                class="flex-1 px-2 py-2 font-heading text-xs font-semibold"
                :class="step === index ? 'bg-ink text-surface' : 'text-secondary'"
                :aria-current="step === index"
                @click="goTo(index)"
            >
                {{ index + 1 }}. {{ label }}
            </button>
        </div>

        <div class="mt-2 bg-gading p-3 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.18)]">
            <!-- Device chrome -->
            <div class="mb-2 flex items-center justify-between text-[11px] text-secondary">
                <span>{{ demo.seller.link }}</span>
                <span class="font-heading font-semibold text-ink">{{ demo.seller.name }}</span>
            </div>

            <!-- Step 1 — Lihat trip -->
            <div
                v-show="step === 0"
                :class="cardBase"
            >
                <p class="font-heading text-sm font-semibold text-ink">
                    {{ demo.step1.trip_name }}
                    <span class="ml-1 bg-prada/20 px-1.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-ink">{{ demo.step1.trip_tag }}</span>
                </p>
                <p class="mt-1 text-xs text-secondary">
                    {{ demo.step1.includes }}
                </p>
                <p class="section-header mt-3">
                    {{ demo.step1.pick }}
                </p>
                <ul class="mt-2 space-y-1.5">
                    <li
                        v-for="(dep, i) in demo.step1.departures"
                        :key="i"
                        class="flex items-center justify-between bg-gading px-2.5 py-2 text-xs shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]"
                        :class="{ 'opacity-50': dep.state === 'full' }"
                    >
                        <span class="text-ink">{{ dep.date }}</span>
                        <span :class="dep.state === 'full' ? 'text-status-cancelled' : 'text-status-paid'">{{ dep.seats }}</span>
                    </li>
                </ul>
                <div class="mt-3 flex items-center justify-between">
                    <div>
                        <p class="font-heading text-base font-semibold text-ink">
                            {{ demo.step1.price }}
                        </p>
                        <p class="text-[11px] text-secondary">
                            {{ demo.step1.price_note }}
                        </p>
                    </div>
                    <span class="bg-ink px-3 py-2 font-heading text-xs font-semibold text-surface">{{ demo.step1.cta }}</span>
                </div>
            </div>

            <!-- Step 2 — Pesan -->
            <div
                v-show="step === 1"
                :class="cardBase"
            >
                <p class="font-heading text-sm font-semibold text-ink">
                    {{ demo.step2.title }}
                </p>
                <p class="section-header mt-3">
                    {{ demo.step2.section }}
                </p>
                <dl class="mt-2 space-y-1.5 text-xs">
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">
                            {{ demo.step2.name_label }}
                        </dt>
                        <dd class="text-ink">
                            {{ demo.step2.name_value }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">
                            {{ demo.step2.wa_label }}
                        </dt>
                        <dd class="text-ink">
                            {{ demo.step2.wa_value }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">
                            {{ demo.step2.participants_label }}
                        </dt>
                        <dd class="text-ink">
                            {{ demo.step2.participants_value }}
                        </dd>
                    </div>
                </dl>
                <p class="mt-3 flex items-start gap-1.5 bg-status-waiting-tint px-2.5 py-2 text-[11px] text-status-waiting">
                    <span aria-hidden="true">◷</span>
                    <span>{{ demo.step2.hold }}</span>
                </p>
                <div class="mt-3 flex items-center justify-between">
                    <div>
                        <p class="text-[11px] text-secondary">
                            {{ demo.step2.total_label }}
                        </p>
                        <p class="font-heading text-base font-semibold text-ink">
                            {{ demo.step2.total_value }}
                        </p>
                    </div>
                    <span class="bg-ink px-3 py-2 font-heading text-xs font-semibold text-surface">{{ demo.step2.cta }}</span>
                </div>
            </div>

            <!-- Step 3 — Bayar -->
            <div
                v-show="step === 2"
                :class="cardBase"
            >
                <p class="font-heading text-sm font-semibold text-ink">
                    {{ demo.step3.title }}
                </p>
                <div class="mt-2 flex items-center justify-between">
                    <span class="text-xs text-secondary">{{ demo.step3.subtitle }}</span>
                    <span class="flex items-center gap-1 bg-status-waiting-tint px-2 py-1 text-[11px] text-status-waiting">
                        <span aria-hidden="true">◷</span> {{ demo.step3.status }}
                    </span>
                </div>
                <ol class="mt-3 flex items-center gap-1 text-[10px]">
                    <li
                        v-for="(s, i) in demo.step3.steps"
                        :key="i"
                        class="flex-1 px-1.5 py-1 text-center font-semibold"
                        :class="i === 0 ? 'bg-ink text-surface' : 'bg-ink/5 text-secondary'"
                    >
                        {{ i + 1 }} · {{ s }}
                    </li>
                </ol>
                <dl class="mt-3 space-y-1.5 text-xs">
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">
                            {{ demo.step3.bank_label }}
                        </dt>
                        <dd class="text-ink">
                            {{ demo.step3.bank_value }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">
                            {{ demo.step3.holder_label }}
                        </dt>
                        <dd class="text-ink">
                            {{ demo.step3.holder_value }}
                        </dd>
                    </div>
                    <div class="flex justify-between gap-3">
                        <dt class="text-secondary">
                            {{ demo.step3.amount_label }}
                        </dt>
                        <dd class="font-semibold text-ink">
                            {{ demo.step3.amount_value }}
                        </dd>
                    </div>
                </dl>
                <div class="mt-3 flex items-center justify-between bg-gading px-2.5 py-2 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]">
                    <span class="text-[11px] text-secondary">{{ demo.step3.countdown_label }}</span>
                    <span
                        class="font-heading text-sm font-semibold tabular-nums text-status-waiting"
                        aria-live="off"
                    >{{ countdown }}</span>
                </div>
                <p class="mt-2 text-[11px] text-secondary">
                    {{ demo.step3.note }}
                </p>
            </div>
        </div>

        <figcaption class="mt-2 flex items-center justify-between">
            <button
                v-if="!reduced"
                type="button"
                class="inline-flex min-h-9 items-center px-2 text-xs font-semibold text-secondary hover:text-ink"
                @click="togglePlay"
            >
                {{ paused ? demo.play : demo.pause }}
            </button>
            <span
                v-else
                aria-hidden="true"
            />
            <span class="flex gap-1">
                <span
                    v-for="(label, index) in demo.tabs"
                    :key="index"
                    class="h-1.5 w-1.5"
                    :class="step === index ? 'bg-ink' : 'bg-ink/20'"
                    aria-hidden="true"
                />
            </span>
        </figcaption>
    </figure>
</template>
