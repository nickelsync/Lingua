<template>
    <div class="mx-auto max-w-4xl">
        <h1 class="text-2xl font-bold text-ink-100">Your learning path</h1>
        <p class="mt-1 text-ink-400">Beginner to Mastery — pass each level's final exam to unlock the next.</p>

        <LoadingState v-if="loading" message="Loading levels…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <div v-else class="mt-6 space-y-4">
            <div
                v-for="level in levels"
                :key="level.id"
                class="card p-6 transition-colors"
                :class="level.unlocked ? 'hover:border-ink-500' : 'opacity-60'"
            >
                <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                    <div class="flex items-center gap-4">
                        <span
                            class="flex h-12 w-12 shrink-0 items-center justify-center rounded-full border text-sm font-bold"
                            :class="level.unlocked ? 'border-accent-600 bg-accent-600/10 text-accent-400' : 'border-ink-700 bg-ink-850 text-ink-400'"
                        >
                            {{ level.code === 'beginner' ? 'BG' : level.code.toUpperCase() }}
                        </span>
                        <div>
                            <h2 class="flex items-center gap-2 font-semibold text-ink-100">
                                {{ level.name }}
                                <span v-if="isCurrent(level)" class="rounded-full bg-accent-600 px-2 py-0.5 text-[10px] font-bold uppercase text-white">Current</span>
                                <span v-if="!level.unlocked" aria-hidden="true" class="text-ink-400">🔒</span>
                            </h2>
                            <p class="mt-0.5 max-w-md text-sm text-ink-400">{{ level.description }}</p>
                        </div>
                    </div>
                    <div class="flex shrink-0 items-center gap-3">
                        <RouterLink v-if="level.unlocked" :to="`/app/learn/level/${level.id}`" class="btn-primary">
                            {{ level.progress?.percent > 0 ? 'Continue' : 'Start' }}
                        </RouterLink>
                        <span v-else class="text-xs text-ink-400">Pass the previous exam to unlock</span>
                    </div>
                </div>
                <div v-if="level.unlocked && level.progress" class="mt-4">
                    <div class="mb-1.5 flex justify-between text-xs text-ink-400">
                        <span>{{ level.progress.lessons_completed }}/{{ level.progress.lessons_total }} lessons</span>
                        <span>{{ level.progress.percent }}%</span>
                    </div>
                    <ProgressBar :value="level.progress.percent" />
                </div>
            </div>

            <div class="card border-dashed p-6 text-center">
                <p class="font-semibold text-ink-100">🏆 English Mastery</p>
                <p class="mt-1 text-sm text-ink-400">Complete all levels and pass the C2 final exam to reach full mastery.</p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import ProgressBar from '../components/ProgressBar.vue'

const auth = useAuthStore()
const levels = ref([])
const loading = ref(true)
const error = ref(null)

function isCurrent(level) {
    return auth.user?.current_level?.id === level.id
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get('/api/levels')
        levels.value = data.levels
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
