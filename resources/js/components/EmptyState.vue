<template>
    <div class="flex flex-col items-center justify-center gap-3 py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-ink-700 bg-ink-800 text-xl">{{ icon }}</div>
        <div>
            <p class="font-medium text-ink-200">{{ title }}</p>
            <p class="mt-1 max-w-sm text-sm text-ink-400">{{ message }}</p>
        </div>
        <slot />
    </div>
</template>

<script setup>
defineProps({
    icon: { type: String, default: '○' },
    title: { type: String, default: 'Nothing here yet' },
    message: { type: String, default: '' },
})
</script>
