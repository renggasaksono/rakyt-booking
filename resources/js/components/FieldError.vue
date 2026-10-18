<script setup>
defineProps({
    id: { type: String, default: undefined },
    message: { type: String, default: null },
});
</script>

<template>
    <p
        v-if="message"
        :id="id"
        class="mt-1 text-xs text-status-rejected"
        role="alert"
    >
        {{ message }}
    </p>
</template>
