<template>
    <div class="flex flex-col items-center justify-center gap-4 py-16 text-center">
        <div class="flex h-12 w-12 items-center justify-center rounded-full border border-red-800 bg-red-900/30 text-xl text-red-300">!</div>
        <div>
            <p class="font-medium text-ink-100">{{ title }}</p>
            <p class="mt-1 max-w-sm text-sm text-ink-400">{{ message }}</p>
        </div>
        <button v-if="retry" class="btn-secondary" @click="retry">Try again</button>
    </div>
</template>

<script setup>
defineProps({
    title: { type: String, default: 'Something went wrong' },
    message: { type: String, default: 'Please try again.' },
    retry: { type: Function, default: null },
})
</script>
