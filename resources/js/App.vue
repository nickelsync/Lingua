<template>
    <router-view />
    <ToastStack />
    <div v-if="!ui.online" class="fixed bottom-4 left-1/2 z-50 -translate-x-1/2 rounded-full border border-ink-600 bg-ink-800 px-4 py-1.5 text-xs text-ink-300 shadow-lg">
        Offline mode — progress will sync when you reconnect
    </div>
</template>

<script setup>
import ToastStack from './components/ToastStack.vue'
import { useUiStore } from './stores/ui'

const ui = useUiStore()
</script>
