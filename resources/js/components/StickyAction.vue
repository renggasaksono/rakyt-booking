<template>
    <!-- The one gold CTA stays reachable above the keyboard and the home bar (F-001 P1). -->
    <div class="fixed inset-x-0 bottom-0 z-30 border-t border-ink/10 bg-surface px-4 pb-[max(0.75rem,env(safe-area-inset-bottom))] pt-3">
        <div class="mx-auto max-w-md">
            <slot />
        </div>
    </div>
</template>
