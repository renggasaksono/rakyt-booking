<script setup>
import { ref } from 'vue';
import { t } from '../../lib/text.js';

/**
 * AC-17, AC-32, AC-36: swipeable trip photos (scroll snap) with prev/next
 * controls for keyboard users and a "1/6" counter. Only the first photo loads
 * eagerly.
 */
const props = defineProps({
    photos: { type: Array, required: true },
    name: { type: String, required: true },
    copy: { type: Object, required: true },
});

const track = ref(null);
const current = ref(0);

function onScroll() {
    const el = track.value;

    if (el && el.clientWidth > 0) {
        current.value = Math.min(props.photos.length - 1, Math.max(0, Math.round(el.scrollLeft / el.clientWidth)));
    }
}

function go(step) {
    const el = track.value;

    if (!el) {
        return;
    }

    const next = Math.min(props.photos.length - 1, Math.max(0, current.value + step));
    el.scrollTo({ left: next * el.clientWidth, behavior: 'smooth' });
    current.value = next;
}
</script>

<template>
    <section
        v-if="photos.length"
        class="relative -mx-4 bg-gading"
        :aria-label="t(copy, 'trip.gallery.label')"
        aria-roledescription="carousel"
    >
        <div
            ref="track"
            class="flex aspect-[4/3] snap-x snap-mandatory overflow-x-auto overscroll-x-contain [scrollbar-width:none]"
            @scroll.passive="onScroll"
        >
            <img
                v-for="(photo, index) in photos"
                :key="index"
                :src="photo.src"
                :srcset="photo.srcset"
                sizes="(min-width: 448px) 448px, 100vw"
                width="800"
                height="600"
                :loading="index === 0 ? 'eager' : 'lazy'"
                :fetchpriority="index === 0 ? 'high' : undefined"
                decoding="async"
                :alt="t(copy, 'trip.gallery.alt', { name, n: index + 1, total: photos.length })"
                class="h-full w-full flex-none snap-center object-cover"
            >
        </div>
        <template v-if="photos.length > 1">
            <button
                type="button"
                class="absolute left-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-surface text-ink shadow disabled:hidden"
                :aria-label="t(copy, 'trip.gallery.previous')"
                :disabled="current === 0"
                @click="go(-1)"
            >
                <span aria-hidden="true">‹</span>
            </button>
            <button
                type="button"
                class="absolute right-2 top-1/2 flex h-11 w-11 -translate-y-1/2 items-center justify-center rounded-full bg-surface text-ink shadow disabled:hidden"
                :aria-label="t(copy, 'trip.gallery.next')"
                :disabled="current === photos.length - 1"
                @click="go(1)"
            >
                <span aria-hidden="true">›</span>
            </button>
        </template>
        <p
            class="absolute bottom-2 right-3 rounded-full bg-ink px-2 py-0.5 text-xs text-surface"
            aria-live="polite"
        >
            {{ t(copy, 'trip.gallery.counter', { current: current + 1, total: photos.length }) }}
        </p>
    </section>
</template>
