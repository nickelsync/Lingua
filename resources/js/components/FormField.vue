<template>
    <div>
        <label v-if="label" :for="id" class="label">{{ label }}</label>
        <slot />
        <p v-if="error" class="mt-1 text-xs text-red-400">{{ error }}</p>
        <p v-else-if="hint" class="mt-1 text-xs text-ink-400">{{ hint }}</p>
    </div>
</template>

<script setup>
defineProps({ label: String, error: String, hint: String, id: String })
</script>
