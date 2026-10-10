<template>
    <EmptyState v-if="!bars.length" title="No XP yet" message="Earn XP by completing lessons and exercises." icon="⚡" />
    <div v-else>
        <div class="flex h-36 items-end gap-1">
            <div v-for="bar in bars" :key="bar.date" class="group relative flex-1">
                <div class="w-full rounded-t bg-accent-600/70 transition-colors group-hover:bg-accent-500" :style="{ height: bar.height + '%', minHeight: bar.xp > 0 ? '3px' : '1px' }" />
                <span class="pointer-events-none absolute -top-7 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-ink-800 px-1.5 py-0.5 text-[10px] text-ink-100 group-hover:block">
                    {{ bar.xp }} XP · {{ bar.date.slice(5) }}
                </span>
            </div>
        </div>
        <div class="mt-1.5 flex justify-between text-[10px] text-ink-400">
            <span>{{ bars[0]?.date.slice(5) }}</span>
            <span>{{ bars[bars.length - 1]?.date.slice(5) }}</span>
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'
import EmptyState from './EmptyState.vue'

const props = defineProps({ data: { type: Array, default: () => [] } })

const bars = computed(() => {
    const byDate = Object.fromEntries((props.data || []).map((d) => [d.date, Number(d.xp)]))
    const result = []
    const today = new Date()
    for (let i = 29; i >= 0; i--) {
        const d = new Date(today)
        d.setDate(today.getDate() - i)
        const key = d.toISOString().slice(0, 10)
        result.push({ date: key, xp: byDate[key] || 0 })
    }
    const max = Math.max(1, ...result.map((r) => r.xp))
    return result.map((r) => ({ ...r, height: Math.round((r.xp / max) * 100) }))
})
</script>
