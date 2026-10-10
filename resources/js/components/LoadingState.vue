<template>
    <div class="flex flex-col items-center justify-center gap-3 py-16 text-ink-400" role="status">
        <svg class="h-7 w-7 animate-spin text-accent-500" viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4" />
            <path class="opacity-90" fill="currentColor" d="M4 12a8 8 0 018-8v3a5 5 0 00-5 5H4z" />
        </svg>
        <p class="text-sm">{{ message }}</p>
    </div>
</template>

<script setup>
defineProps({ message: { type: String, default: 'Loading…' } })
</script>
