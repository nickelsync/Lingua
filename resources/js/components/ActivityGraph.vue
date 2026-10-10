<template>
    <div>
        <div class="grid grid-flow-col grid-rows-7 gap-1" :style="{ gridTemplateColumns: `repeat(${weeks}, 1fr)` }">
            <div
                v-for="(day, i) in cells"
                :key="i"
                class="aspect-square rounded-[3px]"
                :class="intensityClass(day)"
                :title="day ? `${day.date}: ${day.xp} XP` : ''"
            />
        </div>
        <div class="mt-2 flex items-center justify-end gap-1.5 text-[10px] text-ink-400">
            Less
            <span class="h-2.5 w-2.5 rounded-[3px] bg-ink-800" />
            <span class="h-2.5 w-2.5 rounded-[3px] bg-accent-600/30" />
            <span class="h-2.5 w-2.5 rounded-[3px] bg-accent-600/60" />
            <span class="h-2.5 w-2.5 rounded-[3px] bg-accent-500" />
            More
        </div>
    </div>
</template>

<script setup>
import { computed } from 'vue'

const props = defineProps({ data: { type: Array, default: () => [] } })
const weeks = 12

const cells = computed(() => {
    const byDate = Object.fromEntries((props.data || []).map((d) => [d.local_date, d]))
    const result = []
    const today = new Date()
    const start = new Date(today)
    start.setDate(start.getDate() - (weeks * 7 - 1) - today.getDay())
    for (let i = 0; i < weeks * 7; i++) {
        const d = new Date(start)
        d.setDate(start.getDate() + i)
        if (d > today) { result.push(null); continue }
        const key = d.toISOString().slice(0, 10)
        const entry = byDate[key]
        result.push({ date: key, xp: entry ? Number(entry.xp) : 0 })
    }
    return result
})

function intensityClass(day) {
    if (!day) return 'bg-transparent'
    if (day.xp === 0) return 'bg-ink-800'
    if (day.xp < 20) return 'bg-accent-600/30'
    if (day.xp < 60) return 'bg-accent-600/60'
    return 'bg-accent-500'
}
</script>
