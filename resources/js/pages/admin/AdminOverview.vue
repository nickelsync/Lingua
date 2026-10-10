<template>
    <div>
        <h1 class="text-2xl font-bold text-ink-100">Overview</h1>
        <LoadingState v-if="loading" message="Loading analytics…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <template v-else-if="data">
            <div class="mt-6 grid grid-cols-2 gap-4 lg:grid-cols-4">
                <StatCard label="Total learners" :value="data.total_users" />
                <StatCard label="Active (7 days)" :value="data.active_users_7d" />
                <StatCard label="Lessons completed" :value="data.lessons_completed" />
                <StatCard label="Avg quiz score" :value="data.average_quiz_score + '%'" />
                <StatCard label="Exams taken" :value="data.exams_taken" />
                <StatCard label="Exam pass rate" :value="data.exam_pass_rate !== null ? data.exam_pass_rate + '%' : '—'" />
                <StatCard label="Words saved" :value="data.words_saved" />
                <StatCard label="Vocab reviews" :value="data.vocab_reviews" />
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">Learners per level</h2>
                    <EmptyState v-if="!data.cefr_distribution.length" title="No data" message="No learners assigned to levels yet." icon="◔" />
                    <div v-else class="space-y-2.5">
                        <div v-for="d in data.cefr_distribution" :key="d.code">
                            <div class="mb-1 flex justify-between text-sm">
                                <span class="uppercase text-ink-300">{{ d.code }}</span>
                                <span class="text-ink-400">{{ d.count }}</span>
                            </div>
                            <ProgressBar :value="(d.count / maxLevelCount) * 100" />
                        </div>
                    </div>
                </div>
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">Daily active learners (30d)</h2>
                    <EmptyState v-if="!data.activity_by_day.length" title="No activity" message="Learning activity will appear here." icon="◷" />
                    <div v-else class="flex h-36 items-end gap-0.5">
                        <div v-for="d in data.activity_by_day" :key="d.local_date" class="group relative flex-1">
                            <div class="w-full rounded-t bg-accent-600/70 group-hover:bg-accent-500" :style="{ height: (d.users / maxDayUsers) * 100 + '%', minHeight: '2px' }" />
                            <span class="pointer-events-none absolute -top-7 left-1/2 z-10 hidden -translate-x-1/2 whitespace-nowrap rounded bg-ink-800 px-1.5 py-0.5 text-[10px] text-ink-100 group-hover:block">{{ d.users }} · {{ d.local_date }}</span>
                        </div>
                    </div>
                </div>
            </div>

            <div class="mt-6 grid gap-6 lg:grid-cols-2">
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">Hardest lessons <span class="text-xs font-normal text-ink-400">(lowest avg score, ≥3 attempts)</span></h2>
                    <EmptyState v-if="!data.hardest_lessons.length" title="Not enough data" message="Needs at least 3 attempts per lesson." icon="✎" />
                    <ul v-else class="divide-y divide-ink-800">
                        <li v-for="l in data.hardest_lessons" :key="l.id" class="flex justify-between py-2 text-sm">
                            <span class="text-ink-200">{{ l.title }}</span>
                            <span class="shrink-0 tabular-nums text-ink-400">{{ Math.round(l.avg_score) }}% · {{ l.attempts }} tries</span>
                        </li>
                    </ul>
                </div>
                <div class="card p-6">
                    <h2 class="mb-4 font-semibold text-ink-100">Most missed questions</h2>
                    <EmptyState v-if="!data.most_failed_questions.length" title="Not enough data" message="Needs at least 3 attempts per question." icon="?" />
                    <ul v-else class="divide-y divide-ink-800">
                        <li v-for="q in data.most_failed_questions" :key="q.id" class="py-2 text-sm">
                            <p class="line-clamp-1 text-ink-200">{{ q.prompt }}</p>
                            <p class="text-xs text-ink-400">{{ q.failures }}/{{ q.attempts }} incorrect</p>
                        </li>
                    </ul>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../../api/client'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import EmptyState from '../../components/EmptyState.vue'
import StatCard from '../../components/StatCard.vue'
import ProgressBar from '../../components/ProgressBar.vue'

const data = ref(null)
const loading = ref(true)
const error = ref(null)

const maxLevelCount = computed(() => Math.max(1, ...(data.value?.cefr_distribution || []).map((d) => d.count)))
const maxDayUsers = computed(() => Math.max(1, ...(data.value?.activity_by_day || []).map((d) => d.users)))

async function load() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get('/api/admin/overview')
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
