<template>
    <div class="mx-auto max-w-2xl">
        <LoadingState v-if="loading" message="Loading your result…" />
        <ErrorState v-else-if="error" :message="error" />
        <template v-else-if="result">
            <div class="card p-8 text-center">
                <p class="text-sm font-medium uppercase tracking-wide text-ink-400">Placement result</p>
                <p class="mt-4 text-5xl font-bold text-accent-400">{{ result.level?.name }}</p>
                <p class="mt-2 text-ink-300">Score: {{ result.score }}%</p>
                <p v-if="result.level?.description" class="mx-auto mt-4 max-w-md text-sm text-ink-400">{{ result.level.description }}</p>
            </div>

            <div v-if="result.breakdown?.length" class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Skill breakdown</h2>
                <div class="space-y-3">
                    <div v-for="skill in result.breakdown" :key="skill.skill">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="capitalize text-ink-300">{{ skill.skill }}</span>
                            <span class="text-ink-400">{{ skill.correct }}/{{ skill.total }} · {{ skill.percent }}%</span>
                        </div>
                        <ProgressBar :value="skill.percent" />
                    </div>
                </div>
            </div>

            <div class="card mt-6 p-6">
                <h2 class="mb-2 font-semibold text-ink-100">Where do you want to start?</h2>
                <p class="mb-4 text-sm text-ink-400">Your assessed level and everything below it are unlocked. Starting one level lower is a good choice if you want to reinforce fundamentals first.</p>
                <div class="flex flex-col gap-3 sm:flex-row">
                    <button class="btn-primary flex-1" @click="go()">Start at {{ result.level?.name }}</button>
                    <RouterLink to="/app/learn" class="btn-secondary flex-1 text-center">Browse all levels</RouterLink>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import ProgressBar from '../components/ProgressBar.vue'

const router = useRouter()
const result = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
    try {
        result.value = await api.get('/api/placement/result')
    } catch (e) {
        error.value = e.status === 404 ? 'You have not completed the placement test yet.' : e.message
    } finally {
        loading.value = false
    }
})

function go() {
    router.push('/app/learn')
}
</script>
