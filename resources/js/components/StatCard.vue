<template>
    <div class="card p-4">
        <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ label }}</p>
        <p class="mt-1 text-2xl font-bold text-ink-100">{{ value }}</p>
        <p v-if="sub" class="mt-0.5 text-xs text-ink-400">{{ sub }}</p>
    </div>
</template>

<script setup>
defineProps({ label: String, value: [String, Number], sub: String })
</script>
