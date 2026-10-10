<template>
    <div class="mx-auto max-w-4xl">
        <h1 class="text-2xl font-bold text-ink-100">Progress & statistics</h1>
        <p class="mt-1 text-ink-400">Everything you've accomplished so far.</p>

        <LoadingState v-if="loading" message="Crunching your numbers…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <template v-else-if="stats">
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Lessons completed" :value="stats.lessons_completed" />
                <StatCard label="Exercises done" :value="stats.exercises_completed" />
                <StatCard label="Average accuracy" :value="stats.average_accuracy !== null ? stats.average_accuracy + '%' : '—'" />
                <StatCard label="Study time" :value="formatMinutes(stats.estimated_study_minutes)" sub="estimated" />
                <StatCard label="Current streak" :value="`🔥 ${stats.streak_current}`" :sub="`longest: ${stats.streak_longest}`" />
                <StatCard label="Words saved" :value="stats.words_saved" />
                <StatCard label="Words reviewed" :value="stats.words_reviewed" />
                <StatCard label="Speaking attempts" :value="stats.speaking_attempts" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">XP — last 30 days</h2>
                    <XpChart :data="stats.xp_history" />
                </div>
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">Skill coverage (current level)</h2>
                    <EmptyState v-if="!stats.skills_overall?.length" title="No data yet" message="Complete lessons to see skill progress." icon="◔" />
                    <div v-else class="space-y-3">
                        <div v-for="skill in stats.skills_overall" :key="skill.skill">
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="capitalize text-ink-300">{{ skill.skill }}</span>
                                <span class="text-ink-400">{{ skill.percent }}%</span>
                            </div>
                            <ProgressBar :value="skill.percent" />
                        </div>
                    </div>
                </div>
            </div>

            <!-- Achievements -->
            <div class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Achievements</h2>
                <LoadingState v-if="achievementsLoading" message="Loading achievements…" />
                <div v-else class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
                    <div v-for="a in achievements" :key="a.id" class="rounded-lg border p-4 text-center" :class="a.earned ? 'border-accent-600/50 bg-accent-600/5' : 'border-ink-800 opacity-50'">
                        <p class="text-2xl" aria-hidden="true">{{ achievementIcon(a.icon) }}</p>
                        <p class="mt-2 text-sm font-semibold" :class="a.earned ? 'text-ink-100' : 'text-ink-400'">{{ a.name }}</p>
                        <p class="mt-1 text-xs text-ink-400">{{ a.description }}</p>
                        <p v-if="a.earned" class="mt-1.5 text-[10px] text-accent-400">Earned {{ new Date(a.earned_at).toLocaleDateString() }}</p>
                    </div>
                </div>
            </div>

            <!-- Exam history -->
            <div class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Exam history</h2>
                <EmptyState v-if="!exams.length" title="No exams taken" message="Your placement and final exam results will appear here." icon="✎" />
                <div v-else class="overflow-x-auto">
                    <table class="w-full text-sm">
                        <thead>
                            <tr class="border-b border-ink-800 text-left text-xs uppercase text-ink-400">
                                <th class="pb-2 pr-4">Exam</th><th class="pb-2 pr-4">Score</th><th class="pb-2 pr-4">Result</th><th class="pb-2">Date</th>
                            </tr>
                        </thead>
                        <tbody>
                            <tr v-for="e in exams" :key="e.id" class="border-b border-ink-800/50 last:border-0">
                                <td class="py-2.5 pr-4 text-ink-200">{{ e.kind === 'placement' ? 'Placement' : (e.level?.name || '') + ' final' }}</td>
                                <td class="py-2.5 pr-4 tabular-nums text-ink-100">{{ e.score }}%</td>
                                <td class="py-2.5 pr-4">
                                    <span v-if="e.kind === 'placement'" class="text-ink-400">—</span>
                                    <span v-else :class="e.passed ? 'text-accent-400' : 'text-red-400'">{{ e.passed ? 'Passed' : 'Failed' }}</span>
                                </td>
                                <td class="py-2.5 text-ink-400">{{ new Date(e.submitted_at).toLocaleDateString() }}</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>

            <!-- Learning history -->
            <div class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Learning history</h2>
                <EmptyState v-if="!historyItems.length" title="No history yet" message="Completed lessons and exercises will appear here." icon="◷" />
                <ul v-else class="divide-y divide-ink-800">
                    <li v-for="(h, i) in historyItems" :key="i" class="flex items-center justify-between py-2.5 text-sm">
                        <span class="text-ink-200">
                            <span class="mr-2 text-xs uppercase text-ink-400">{{ h.kind }}</span>{{ h.title }}
                        </span>
                        <span class="shrink-0 text-xs text-ink-400">{{ h.detail }}</span>
                    </li>
                </ul>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api/client'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import StatCard from '../components/StatCard.vue'
import ProgressBar from '../components/ProgressBar.vue'
import XpChart from '../components/XpChart.vue'

const stats = ref(null)
const achievements = ref([])
const achievementsLoading = ref(true)
const exams = ref([])
const history = ref(null)
const loading = ref(true)
const error = ref(null)

const historyItems = computed(() => {
    if (!history.value) return []
    const lessons = (history.value.lesson_completions || []).map((c) => ({
        kind: 'lesson',
        title: c.lesson?.title || 'Lesson',
        detail: new Date(c.first_completed_at).toLocaleDateString(),
        at: c.first_completed_at,
    }))
    const attempts = (history.value.exercise_attempts || []).map((a) => ({
        kind: 'exercise',
        title: a.exercise?.title || 'Exercise',
        detail: `${a.score}% · ${new Date(a.created_at).toLocaleDateString()}`,
        at: a.created_at,
    }))
    return [...lessons, ...attempts].sort((a, b) => new Date(b.at) - new Date(a.at)).slice(0, 40)
})

const achievementIcons = {
    book: '📘', bookmark: '🔖', check: '✅', flame: '🔥', mic: '🎤',
    pen: '✍️', refresh: '🔁', star: '⭐', trophy: '🏆',
}
function achievementIcon(code) {
    return achievementIcons[code] || '🎖'
}

function formatMinutes(min) {
    if (!min) return '0 min'
    if (min < 60) return `${min} min`
    return `${Math.floor(min / 60)}h ${min % 60}m`
}

async function load() {
    loading.value = true
    error.value = null
    try {
        stats.value = await api.get('/api/stats')
        api.get('/api/achievements').then((d) => { achievements.value = d.achievements }).finally(() => (achievementsLoading.value = false))
        api.get('/api/exams').then((d) => { exams.value = d.attempts }).catch(() => {})
        api.get('/api/history').then((d) => { history.value = d }).catch(() => {})
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
