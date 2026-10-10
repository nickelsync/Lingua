<template>
    <div class="h-2 w-full overflow-hidden rounded-full bg-ink-800" role="progressbar" :aria-valuenow="clamped" aria-valuemin="0" aria-valuemax="100">
        <div class="progress-bar-fill h-full rounded-full" :class="barClass" :style="{ width: clamped + '%' }" />
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({
    value: { type: Number, default: 0 },
    color: { type: String, default: 'accent' },
})
const clamped = computed(() => Math.min(100, Math.max(0, Math.round(props.value || 0))))
const barClass = computed(() => (props.color === 'accent' ? 'bg-accent-500' : props.color === 'red' ? 'bg-red-500' : 'bg-ink-400'))
</script>
