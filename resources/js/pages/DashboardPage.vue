<template>
    <div class="mx-auto max-w-5xl">
        <LoadingState v-if="loading" message="Loading your dashboard…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <template v-else-if="data">
            <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
                <div>
                    <h1 class="text-2xl font-bold text-ink-100">Hi, {{ auth.user?.display_name }} 👋</h1>
                    <p class="mt-1 text-ink-400">
                        <template v-if="data.level">You're working through <span class="font-medium text-accent-400">{{ data.level.name }}</span>.</template>
                        <template v-else>Let's get started with your English journey.</template>
                    </p>
                </div>
                <RouterLink v-if="!auth.user?.placement_done" to="/app/placement" class="btn-secondary self-start">📋 Take placement test</RouterLink>
            </div>

            <!-- Continue learning -->
            <div v-if="data.continue" class="card mt-6 flex flex-col justify-between gap-4 border-accent-600/40 p-6 sm:flex-row sm:items-center">
                <div>
                    <p class="text-xs font-medium uppercase tracking-wide text-accent-400">Continue learning</p>
                    <h2 class="mt-1 text-lg font-semibold text-ink-100">{{ data.continue.lesson.title }}</h2>
                    <p class="text-sm text-ink-400">{{ data.continue.course.title }} · {{ data.continue.module.title }} · ~{{ data.continue.lesson.estimated_minutes }} min</p>
                </div>
                <RouterLink :to="`/app/lesson/${data.continue.lesson.slug}`" class="btn-primary shrink-0">Continue →</RouterLink>
            </div>
            <div v-else class="card mt-6 p-6 text-center">
                <p class="text-ink-300">No lesson in progress.</p>
                <RouterLink to="/app/learn" class="btn-primary mt-3">Browse lessons</RouterLink>
            </div>

            <!-- Stats row -->
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Streak" :value="`🔥 ${data.streak}`" :sub="data.streak === 1 ? 'day' : 'days'" />
                <StatCard label="Total XP" :value="data.xp_total" :sub="`Level ${data.account_level}`" />
                <StatCard label="Lessons completed" :value="data.lessons_completed" />
                <StatCard label="Reviews due" :value="data.due_reviews" sub="flashcards" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <!-- Level progress -->
                <div class="card p-6">
                    <div class="mb-4 flex items-center justify-between">
                        <h2 class="font-semibold text-ink-100">Level progress</h2>
                        <RouterLink to="/app/learn" class="text-sm text-accent-400 hover:underline">View all →</RouterLink>
                    </div>
                    <template v-if="data.level_progress">
                        <div class="mb-2 flex justify-between text-sm">
                            <span class="text-ink-300">{{ data.level.name }}</span>
                            <span class="font-medium text-ink-100">{{ data.level_progress.percent }}%</span>
                        </div>
                        <ProgressBar :value="data.level_progress.percent" />
                        <p class="mt-2 text-xs text-ink-400">{{ data.level_progress.lessons_completed }}/{{ data.level_progress.lessons_total }} lessons</p>
                    </template>
                    <p v-else class="text-sm text-ink-400">No level assigned yet.</p>

                    <div v-if="data.skills?.length" class="mt-5 space-y-3">
                        <div v-for="skill in data.skills" :key="skill.skill">
                            <div class="mb-1 flex justify-between text-xs">
                                <span class="capitalize text-ink-300">{{ skill.skill }}</span>
                                <span class="text-ink-400">{{ skill.percent }}%</span>
                            </div>
                            <ProgressBar :value="skill.percent" />
                        </div>
                    </div>
                </div>

                <!-- Activity graph -->
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">Last 12 weeks</h2>
                    <ActivityGraph :data="data.activity_graph" />
                    <div v-if="data.achievements?.length" class="mt-5 border-t border-ink-800 pt-4">
                        <h3 class="mb-3 text-sm font-medium text-ink-300">Recent achievements</h3>
                        <div class="flex flex-wrap gap-2">
                            <span v-for="a in data.achievements" :key="a.name" class="flex items-center gap-1.5 rounded-full border border-ink-700 bg-ink-850 px-3 py-1 text-xs text-ink-200" :title="a.earned_at">
                                <span aria-hidden="true">{{ a.icon }}</span> {{ a.name }}
                            </span>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Recent activity -->
            <div class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Recent activity</h2>
                <EmptyState v-if="!data.recent_activity?.length" title="No activity yet" message="Complete your first lesson to see your history here." icon="◷" />
                <ul v-else class="divide-y divide-ink-800">
                    <li v-for="(a, i) in data.recent_activity" :key="i" class="flex items-center justify-between py-2.5 text-sm">
                        <span class="text-ink-200"><span class="mr-2 capitalize text-ink-400">{{ a.type.replace('_', ' ') }}</span>{{ a.subject }}</span>
                        <span class="shrink-0 text-xs text-ink-400">+{{ a.xp }} XP · {{ a.local_date }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import StatCard from '../components/StatCard.vue'
import ProgressBar from '../components/ProgressBar.vue'
import ActivityGraph from '../components/ActivityGraph.vue'

const auth = useAuthStore()
const data = ref(null)
const loading = ref(true)
const error = ref(null)

async function load() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get('/api/dashboard')
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
