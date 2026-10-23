<script setup>
import { Head, Link } from '@inertiajs/vue3';
import MarketingLayout from '../layouts/MarketingLayout.vue';
import HeroDemo from '../components/marketing/HeroDemo.vue';
import FaqAccordion from '../components/marketing/FaqAccordion.vue';
import { FUNNEL_EVENTS } from '../lib/analytics.js';
import { useAnalytics } from '../lib/useAnalytics.js';

/**
 * Public marketing landing page at `/` (F-017). Replaces the old placeholder.
 * Every string comes from lang/id/landing.php via the `copy` prop (ADR-0007);
 * the page holds no tenant data (AC-2). The single sign-up funnel: every primary
 * CTA links to `cta.url` — /daftar for a guest, the dashboard for a signed-in
 * seller (AC-3/AC-4). Head tags are server-rendered (ADR-0024).
 */
defineProps({
    authenticated: { type: Boolean, default: false },
    cta: { type: Object, required: true },
    copy: { type: Object, required: true },
    urls: { type: Object, required: true },
});

const { track } = useAnalytics();

function trackCta(location) {
    track(FUNNEL_EVENTS.ctaClick, { location });
}

// Gold (prada) CTA — used in the hero and the closing band only (AC-5).
const goldCta = 'inline-flex min-h-12 items-center justify-center bg-prada px-6 font-heading text-base font-semibold text-ink';
const sectionWrap = 'mx-auto max-w-5xl px-4';
</script>

<template>
    <Head :title="copy.head.title" />

    <MarketingLayout
        :nav="copy.nav"
        :footer="copy.footer"
        :consent="copy.consent"
        :cta="cta"
        :urls="urls"
        @cta="trackCta"
    >
        <!-- Hero -->
        <section :class="[sectionWrap, 'pb-10 pt-10 md:pt-16']">
            <div class="grid items-center gap-10 md:grid-cols-2">
                <div>
                    <h1 class="font-heading text-4xl font-semibold leading-[1.05] tracking-[-0.03em] text-ink md:text-5xl">
                        {{ copy.hero.headline }}
                    </h1>
                    <p class="mt-4 text-lg leading-relaxed text-secondary">
                        {{ copy.hero.subheadline }}
                    </p>
                    <div class="mt-6">
                        <Link
                            :href="cta.url"
                            :class="goldCta"
                            @click="trackCta('hero')"
                        >
                            {{ authenticated ? copy.hero.cta_authenticated : copy.hero.cta }}
                        </Link>
                    </div>
                    <ul class="mt-6 space-y-1.5">
                        <li
                            v-for="(bullet, i) in copy.hero.bullets"
                            :key="i"
                            class="flex items-center gap-2 text-sm text-ink"
                        >
                            <span
                                class="text-status-paid"
                                aria-hidden="true"
                            >✓</span>
                            {{ bullet }}
                        </li>
                    </ul>
                </div>
                <div class="mx-auto w-full max-w-sm">
                    <HeroDemo :demo="copy.demo" />
                </div>
            </div>
        </section>

        <!-- Value proposition -->
        <section class="bg-surface py-12">
            <div :class="sectionWrap">
                <p class="section-header">
                    {{ copy.value.kicker }}
                </p>
                <h2 class="mt-3 max-w-2xl font-heading text-2xl font-semibold tracking-[-0.02em] text-ink md:text-3xl">
                    {{ copy.value.headline }}
                </h2>
                <div class="mt-8 grid gap-6 md:grid-cols-3">
                    <div
                        v-for="(item, i) in copy.value.items"
                        :key="i"
                        class="bg-gading p-5 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]"
                    >
                        <p class="text-[13px] text-secondary line-through decoration-status-rejected/60">
                            {{ item.myth }}
                        </p>
                        <h3 class="mt-2 font-heading text-[19px] font-semibold tracking-[-0.01em] text-ink">
                            {{ item.title }}
                        </h3>
                        <p class="mt-1.5 text-[15px] leading-relaxed text-secondary">
                            {{ item.body }}
                        </p>
                    </div>
                </div>
            </div>
        </section>

        <!-- Cara kerja -->
        <section
            id="cara-kerja"
            class="py-12"
        >
            <div :class="sectionWrap">
                <p class="section-header">
                    {{ copy.how.kicker }}
                </p>
                <h2 class="mt-3 font-heading text-2xl font-semibold tracking-[-0.02em] text-ink md:text-3xl">
                    {{ copy.how.headline }}
                </h2>
                <p class="mt-2 max-w-2xl text-[15px] leading-relaxed text-secondary">
                    {{ copy.how.lede }}
                </p>
                <ol class="mt-8 grid gap-4 sm:grid-cols-2 lg:grid-cols-5">
                    <li
                        v-for="(stepItem, i) in copy.how.steps"
                        :key="i"
                        class="bg-surface p-4 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]"
                    >
                        <span class="font-heading text-[32px] font-semibold leading-none tracking-[-0.03em] text-sogan">{{ String(stepItem.n).padStart(2, '0') }}</span>
                        <h3 class="mt-3 font-heading text-base font-semibold text-ink">
                            {{ stepItem.title }}
                        </h3>
                        <p class="mt-1 text-[13px] leading-relaxed text-secondary">
                            {{ stepItem.body }}
                        </p>
                    </li>
                </ol>
                <div class="mt-8">
                    <p class="section-header">
                        {{ copy.how.loop_kicker }}
                    </p>
                    <ul class="mt-3 flex flex-wrap items-center gap-x-2 gap-y-2 text-sm text-ink">
                        <template
                            v-for="(loopItem, i) in copy.how.loop"
                            :key="i"
                        >
                            <li class="bg-gading px-3 py-1.5 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]">
                                {{ loopItem }}
                            </li>
                            <li
                                v-if="i < copy.how.loop.length - 1"
                                class="text-secondary"
                                aria-hidden="true"
                            >
                                →
                            </li>
                        </template>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Features -->
        <section
            id="fitur"
            class="bg-surface py-12"
        >
            <div :class="sectionWrap">
                <p class="section-header">
                    {{ copy.features.kicker }}
                </p>
                <div class="mt-8 grid gap-8 md:grid-cols-2">
                    <div
                        v-for="(column, key) in { seller: copy.features.seller, customer: copy.features.customer }"
                        :key="key"
                    >
                        <h2 class="font-heading text-xl font-semibold text-ink">
                            {{ column.title }}
                        </h2>
                        <ul class="mt-4 space-y-4">
                            <li
                                v-for="(item, i) in column.items"
                                :key="i"
                                class="bg-gading p-4 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]"
                            >
                                <h3 class="font-heading text-sm font-semibold text-ink">
                                    {{ item.title }}
                                </h3>
                                <p class="mt-1 text-sm text-secondary">
                                    {{ item.body }}
                                </p>
                            </li>
                        </ul>
                    </div>
                </div>

                <!-- Use cases -->
                <div class="mt-10">
                    <p class="section-header">
                        {{ copy.usecases.title }}
                    </p>
                    <ul class="mt-4 grid gap-3 sm:grid-cols-2 lg:grid-cols-4">
                        <li
                            v-for="(item, i) in copy.usecases.items"
                            :key="i"
                            class="bg-gading p-4 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]"
                        >
                            <div class="flex items-center gap-2">
                                <h3 class="font-heading text-sm font-semibold text-ink">
                                    {{ item.title }}
                                </h3>
                                <span
                                    v-if="item.soon"
                                    class="bg-ink/10 px-1.5 py-0.5 text-[11px] font-semibold uppercase tracking-wide text-secondary"
                                >{{ copy.usecases.soon_label }}</span>
                            </div>
                            <p class="mt-1 text-xs text-secondary">
                                {{ item.body }}
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- Seller-direct payment — gold-tint ground (Cerah) -->
        <section class="bg-[var(--color-payment-ground,#f7e7ce)] py-12">
            <div :class="sectionWrap">
                <div class="grid gap-8 md:grid-cols-2">
                    <div>
                        <p class="section-header">
                            {{ copy.payment.kicker }}
                        </p>
                        <h2 class="mt-3 font-heading text-2xl font-semibold tracking-[-0.02em] text-ink md:text-3xl">
                            {{ copy.payment.headline }}
                        </h2>
                        <p class="mt-3 text-[15px] leading-relaxed text-secondary">
                            {{ copy.payment.body }}
                        </p>
                    </div>
                    <ul class="space-y-4">
                        <li
                            v-for="(point, i) in copy.payment.points"
                            :key="i"
                            class="bg-surface p-4 shadow-[inset_0_0_0_1px_rgba(33,23,16,0.12)]"
                        >
                            <h3 class="font-heading text-base font-semibold text-ink">
                                {{ point.title }}
                            </h3>
                            <p class="mt-1 text-[15px] leading-relaxed text-secondary">
                                {{ point.body }}
                            </p>
                        </li>
                    </ul>
                </div>
            </div>
        </section>

        <!-- FAQ -->
        <section
            id="tanya-jawab"
            class="bg-surface py-12"
        >
            <div :class="[sectionWrap, 'max-w-3xl']">
                <p class="section-header">
                    {{ copy.faq.kicker }}
                </p>
                <h2 class="mt-3 font-heading text-2xl font-semibold tracking-[-0.02em] text-ink md:text-3xl">
                    {{ copy.faq.headline }}
                </h2>
                <FaqAccordion :items="copy.faq.items" />
            </div>
        </section>

        <!-- Closing CTA — full-bleed sogan-brown band with cream text (Cerah) -->
        <section class="bg-[var(--color-sogan-deep,#9c5b2c)] py-16">
            <div :class="[sectionWrap, 'max-w-2xl text-center']">
                <p class="inline-block border-b-2 border-gading/40 pb-2 font-heading text-[11px] font-semibold uppercase tracking-[0.16em] text-gading/80">
                    {{ copy.closing.kicker }}
                </p>
                <h2 class="mt-4 font-heading text-2xl font-semibold tracking-[-0.02em] text-gading md:text-4xl">
                    {{ copy.closing.headline }}
                </h2>
                <p class="mt-3 text-base leading-relaxed text-gading/80">
                    {{ copy.closing.body }}
                </p>
                <div class="mt-6 flex justify-center">
                    <Link
                        :href="cta.url"
                        :class="goldCta"
                        @click="trackCta('closing')"
                    >
                        {{ authenticated ? copy.closing.cta_authenticated : copy.closing.cta }}
                    </Link>
                </div>
            </div>
        </section>
    </MarketingLayout>
</template>
