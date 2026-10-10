<template>
    <div class="mx-auto max-w-2xl">
        <!-- Result -->
        <template v-if="result">
            <div class="card p-8 text-center" :class="result.passed ? 'border-accent-600/50' : 'border-red-800/50'">
                <p class="text-sm font-medium uppercase tracking-wide text-ink-400">Final exam · {{ result.level.name }}</p>
                <p class="mt-4 text-6xl font-bold" :class="result.passed ? 'text-accent-400' : 'text-red-400'">{{ result.score }}%</p>
                <p class="mt-3 text-lg font-semibold" :class="result.passed ? 'text-accent-400' : 'text-red-300'">
                    {{ result.passed ? 'Passed! 🎉' : 'Not passed' }}
                </p>
                <p class="mt-1 text-sm text-ink-400">Pass mark: {{ result.pass_score }}%</p>
            </div>

            <div v-if="result.breakdown?.length" class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Skill breakdown</h2>
                <div class="space-y-3">
                    <div v-for="skill in result.breakdown" :key="skill.skill">
                        <div class="mb-1 flex justify-between text-sm">
                            <span class="capitalize text-ink-300">{{ skill.skill }}</span>
                            <span class="text-ink-400">{{ skill.correct }}/{{ skill.total }} · {{ skill.percent }}%</span>
                        </div>
                        <ProgressBar :value="skill.percent" :color="skill.percent >= 75 ? 'accent' : 'red'" />
                    </div>
                </div>
            </div>

            <div v-if="!result.passed && result.recommended_lessons?.length" class="card mt-6 p-6">
                <h2 class="mb-2 font-semibold text-ink-100">Recommended review</h2>
                <p class="mb-4 text-sm text-ink-400">Focus on these lessons before retaking the exam. You can retake it any time.</p>
                <ul class="space-y-2">
                    <li v-for="lesson in result.recommended_lessons" :key="lesson.id">
                        <RouterLink :to="`/app/lesson/${lesson.slug}`" class="block rounded-md border border-ink-700 bg-ink-850 px-4 py-2.5 text-sm text-ink-200 hover:border-ink-500">{{ lesson.title }}</RouterLink>
                    </li>
                </ul>
            </div>

            <div class="mt-6 flex gap-3">
                <RouterLink to="/app/learn" class="btn-primary flex-1 text-center">{{ result.passed ? 'Continue to next level →' : 'Back to lessons' }}</RouterLink>
                <button v-if="!result.passed" class="btn-secondary flex-1" @click="reset">Retake exam</button>
            </div>
        </template>

        <!-- Intro -->
        <template v-else-if="!attempt && !loading">
            <h1 class="text-2xl font-bold text-ink-100">Final exam</h1>
            <p class="mt-2 text-ink-400">Pass this exam to unlock the next level.</p>
            <div class="card mt-6 space-y-3 p-6 text-sm text-ink-300">
                <p>• The exam is <strong class="text-ink-100">timed</strong> — the timer starts as soon as you begin and is enforced by the server.</p>
                <p>• You need <strong class="text-ink-100">75%</strong> to pass. You can retake the exam as many times as you need.</p>
                <p>• Answers are graded at the end. No feedback is shown during the exam.</p>
                <p>• If the timer runs out, only the answers submitted in time are graded.</p>
            </div>
            <div v-if="startError" class="mt-4 rounded-md border border-red-800 bg-red-900/20 p-3 text-sm text-red-300">{{ startError }}</div>
            <div class="mt-6 flex gap-3">
                <button class="btn-primary flex-1" :disabled="starting" @click="start">{{ starting ? 'Preparing…' : 'Start exam' }}</button>
                <RouterLink to="/app/learn" class="btn-secondary flex-1 text-center">Not yet</RouterLink>
            </div>
        </template>

        <LoadingState v-else-if="loading" message="Preparing your exam…" />

        <!-- Active exam -->
        <template v-else-if="attempt">
            <div class="sticky top-14 z-20 -mx-4 mb-4 flex items-center justify-between border-b border-ink-800 bg-ink-950/95 px-4 py-3 backdrop-blur lg:top-14">
                <span class="text-sm font-semibold text-ink-100">{{ attempt.level.name }} final exam</span>
                <span class="rounded-md border px-3 py-1 font-mono text-sm tabular-nums" :class="secondsLeft < 120 ? 'border-red-800 bg-red-900/30 text-red-300' : 'border-ink-700 bg-ink-900 text-ink-200'">
                    {{ timeDisplay }}
                </span>
            </div>

            <div class="mb-4 flex items-center justify-between text-sm text-ink-400">
                <span>Question {{ current + 1 }} of {{ attempt.questions.length }}</span>
                <span>{{ answeredCount }} answered</span>
            </div>
            <ProgressBar :value="(answeredCount / attempt.questions.length) * 100" class="mb-6" />

            <div class="card p-6">
                <ExamQuestion :key="question.id" :question="question" :model-value="answers[question.id]" @update:model-value="(v) => (answers[question.id] = v)" />
            </div>

            <div class="mt-4 flex items-center justify-between gap-3">
                <button class="btn-secondary" :disabled="current === 0" @click="current--">← Previous</button>
                <button v-if="current < attempt.questions.length - 1" class="btn-secondary" @click="current++">Next →</button>
                <button v-else class="btn-primary" :disabled="submitting" @click="confirmOpen = true">Finish exam</button>
            </div>

            <BaseModal :open="confirmOpen" title="Submit exam?" @close="confirmOpen = false">
                <p class="mb-2 text-sm text-ink-300">You answered {{ answeredCount }} of {{ attempt.questions.length }} questions.</p>
                <p v-if="answeredCount < attempt.questions.length" class="mb-4 text-sm text-red-300">Unanswered questions count as incorrect.</p>
                <div class="flex gap-3">
                    <button class="btn-primary flex-1" :disabled="submitting" @click="submit">{{ submitting ? 'Submitting…' : 'Submit now' }}</button>
                    <button class="btn-secondary flex-1" @click="confirmOpen = false">Keep working</button>
                </div>
            </BaseModal>
        </template>
    </div>
</template>

<script setup>
import { computed, onBeforeUnmount, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ProgressBar from '../components/ProgressBar.vue'
import BaseModal from '../components/BaseModal.vue'
import ExamQuestion from '../components/ExamQuestion.vue'

const route = useRoute()
const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const attempt = ref(null)
const answers = ref({})
const current = ref(0)
const loading = ref(false)
const starting = ref(false)
const startError = ref(null)
const submitting = ref(false)
const result = ref(null)
const confirmOpen = ref(false)
const secondsLeft = ref(0)
let timer = null

const question = computed(() => attempt.value?.questions[current.value])
const answeredCount = computed(() => Object.values(answers.value).filter((v) => v !== undefined && v !== null && v !== '').length)
const timeDisplay = computed(() => {
    const m = Math.floor(secondsLeft.value / 60)
    const s = secondsLeft.value % 60
    return `${m}:${String(s).padStart(2, '0')}`
})

async function start() {
    starting.value = true
    startError.value = null
    try {
        loading.value = true
        attempt.value = await api.post(`/api/levels/${route.params.levelId}/exam/start`)
        answers.value = {}
        current.value = 0
        secondsLeft.value = attempt.value.seconds_remaining ?? 0
        timer = setInterval(tick, 1000)
    } catch (e) {
        startError.value = e.message
    } finally {
        starting.value = false
        loading.value = false
    }
}

function tick() {
    secondsLeft.value = Math.max(0, secondsLeft.value - 1)
    if (secondsLeft.value === 0) {
        clearInterval(timer)
        ui.toast('Time is up — submitting your answers.', 'info')
        submit()
    }
}

async function submit() {
    if (submitting.value) return
    submitting.value = true
    confirmOpen.value = false
    clearInterval(timer)
    try {
        result.value = await api.post(`/api/exams/${attempt.value.attempt_id}/submit`, { answers: answers.value })
        attempt.value = null
        if (result.value.passed) await auth.fetchUser()
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        submitting.value = false
    }
}

function reset() {
    result.value = null
    attempt.value = null
    answers.value = {}
}

onBeforeUnmount(() => clearInterval(timer))
</script>
