<template>
    <div class="mx-auto max-w-2xl">
        <LoadingState v-if="loading" message="Loading exercise…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />

        <!-- Results screen -->
        <template v-else-if="result">
            <div class="card p-8 text-center">
                <p class="text-sm font-medium uppercase tracking-wide text-ink-400">{{ exercise.title }}</p>
                <p class="mt-4 text-6xl font-bold" :class="result.score >= 80 ? 'text-accent-400' : result.score >= 50 ? 'text-ink-100' : 'text-red-400'">{{ result.score }}%</p>
                <p class="mt-2 text-ink-300">{{ result.correct }} of {{ result.total }} correct</p>
                <p v-if="result.xp_awarded > 0" class="mt-1 text-sm font-medium text-accent-400">+{{ result.xp_awarded }} XP</p>
                <p v-else class="mt-1 text-xs text-ink-400">Repeat attempts earn reduced XP.</p>
                <p v-if="result.best_score !== undefined" class="mt-3 text-xs text-ink-400">Personal best: {{ result.best_score }}%</p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <button class="btn-secondary" @click="restart">Try again</button>
                    <RouterLink v-if="exercise.lesson_id" :to="backLink" class="btn-primary">Back to lesson</RouterLink>
                    <RouterLink v-else to="/app/practice" class="btn-primary">More practice</RouterLink>
                </div>
            </div>
        </template>

        <!-- Question flow -->
        <template v-else-if="exercise && questions.length">
            <div class="mb-4 flex items-center justify-between">
                <RouterLink :to="backLink" class="text-sm text-ink-400 hover:text-ink-200">← Exit</RouterLink>
                <span class="text-sm tabular-nums text-ink-400">{{ current + 1 }} / {{ questions.length }}</span>
            </div>
            <ProgressBar :value="(current / questions.length) * 100" class="mb-6" />

            <div class="card p-6">
                <h1 class="mb-4 text-sm font-medium uppercase tracking-wide text-ink-400">{{ exercise.title }}</h1>
                <QuestionPlayer
                    :key="question.id"
                    :question="question"
                    :feedback="feedback"
                    :checking="checking"
                    :locked="!!feedback"
                    @answer="check"
                />
                <div v-if="feedback" class="mt-4 flex justify-end">
                    <button class="btn-primary" @click="next">{{ current < questions.length - 1 ? 'Next question →' : 'See results' }}</button>
                </div>
            </div>
        </template>

        <EmptyState v-else title="No questions" message="This exercise has no questions yet." />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import ProgressBar from '../components/ProgressBar.vue'
import QuestionPlayer from '../components/QuestionPlayer.vue'

const route = useRoute()
const ui = useUiStore()

const exercise = ref(null)
const questions = ref([])
const current = ref(0)
const answers = ref({})
const feedback = ref(null)
const checking = ref(false)
const result = ref(null)
const loading = ref(true)
const error = ref(null)

const question = computed(() => questions.value[current.value])
const backLink = computed(() => '/app/learn')

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get(`/api/exercises/${route.params.id}`)
        exercise.value = data.exercise
        questions.value = data.questions
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function check(answer) {
    checking.value = true
    answers.value[question.value.id] = answer
    try {
        if (question.value.type === 'speaking') {
            // Speaking uses the transcript-similarity scorer for detailed feedback.
            const data = await api.post('/api/speaking/score', {
                target: question.value.target,
                transcript: answer || '(no speech)',
                question_id: question.value.id,
            })
            feedback.value = {
                correct: data.accuracy >= 70,
                accuracy: data.accuracy,
                missing: data.missing,
                explanation: question.value.explanation,
            }
        } else {
            const data = await api.post(`/api/questions/${question.value.id}/check`, { answer })
            feedback.value = data
        }
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        checking.value = false
    }
}

function next() {
    feedback.value = null
    if (current.value < questions.value.length - 1) {
        current.value++
    } else {
        submit()
    }
}

async function submit() {
    loading.value = true
    try {
        result.value = await api.post(`/api/exercises/${exercise.value.id}/submit`, { answers: answers.value })
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        loading.value = false
    }
}

function restart() {
    result.value = null
    answers.value = {}
    current.value = 0
    feedback.value = null
    load()
}

onMounted(load)
</script>
