<template>
    <div class="fixed right-4 top-4 z-[60] flex w-80 max-w-[calc(100vw-2rem)] flex-col gap-2" role="status" aria-live="polite">
        <TransitionGroup name="toast">
            <div
                v-for="toast in ui.toasts"
                :key="toast.id"
                class="flex items-start gap-3 rounded-lg border p-3 text-sm shadow-lg"
                :class="{
                    'border-ink-600 bg-ink-800 text-ink-200': toast.type === 'info',
                    'border-accent-600/50 bg-ink-800 text-accent-400': toast.type === 'success',
                    'border-red-800 bg-ink-800 text-red-300': toast.type === 'error',
                }"
            >
                <span class="flex-1">{{ toast.message }}</span>
                <button class="text-ink-400 hover:text-ink-200" aria-label="Dismiss" @click="ui.dismiss(toast.id)">✕</button>
            </div>
        </TransitionGroup>
    </div>
</template>

<script setup>
import { useUiStore } from '../stores/ui'

const ui = useUiStore()
</script>

<style scoped>
.toast-enter-active, .toast-leave-active { transition: all 0.25s ease; }
.toast-enter-from { opacity: 0; transform: translateX(16px); }
.toast-leave-to { opacity: 0; transform: translateX(16px); }
</style>
