<template>
    <Teleport to="body">
        <Transition name="modal">
            <div v-if="open" class="fixed inset-0 z-50 flex items-center justify-center p-4" @keydown.esc="$emit('close')">
                <div class="absolute inset-0 bg-black/70" @click="$emit('close')" />
                <div class="card relative max-h-[85vh] w-full overflow-y-auto p-6 shadow-2xl" :class="wide ? 'max-w-2xl' : 'max-w-md'" role="dialog" aria-modal="true">
                    <div class="mb-4 flex items-start justify-between gap-4">
                        <h3 class="text-lg font-semibold text-ink-100">{{ title }}</h3>
                        <button class="text-ink-400 hover:text-ink-200" aria-label="Close" @click="$emit('close')">✕</button>
                    </div>
                    <slot />
                </div>
            </div>
        </Transition>
    </Teleport>
</template>

<script setup>
defineProps({
    open: Boolean,
    title: String,
    wide: Boolean,
})
defineEmits(['close'])
</script>

<style scoped>
.modal-enter-active, .modal-leave-active { transition: opacity 0.2s ease; }
.modal-enter-from, .modal-leave-to { opacity: 0; }
</style>
