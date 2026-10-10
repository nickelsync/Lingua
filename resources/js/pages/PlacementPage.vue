<template>
    <div class="mx-auto max-w-2xl">
        <!-- Intro screen -->
        <template v-if="!attempt && !loading">
            <h1 class="text-2xl font-bold text-ink-100">Placement assessment</h1>
            <p class="mt-2 text-ink-400">Find your CEFR level so you can start learning at the right place.</p>

            <div class="card mt-6 space-y-4 p-6">
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-accent-400">✓</span>
                    <p class="text-sm text-ink-300"><strong class="text-ink-100">50 multiple-choice questions</strong> covering grammar, vocabulary, reading, and listening — plus <strong class="text-ink-100">10 speaking questions</strong> if your browser supports speech recognition.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-accent-400">✓</span>
                    <p class="text-sm text-ink-300">Takes about <strong class="text-ink-100">30–40 minutes</strong>. There is a generous time limit; answer at your own pace.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-accent-400">✓</span>
                    <p class="text-sm text-ink-300">Your result recommends a starting level. You can also choose to start lower for reinforcement.</p>
                </div>
                <div class="flex items-start gap-3">
                    <span class="mt-0.5 text-ink-400">○</span>
                    <p class="text-sm text-ink-400">Not sure yet? You can skip this and start from Beginner — the placement test stays available.</p>
                </div>
            </div>

            <div v-if="startError" class="mt-4 rounded-md border border-red-800 bg-red-900/20 p-3 text-sm text-red-300">{{ startError }}</div>

            <div class="mt-6 flex flex-col gap-3 sm:flex-row">
                <button class="btn-primary flex-1" :disabled="starting" @click="start">{{ starting ? 'Preparing…' : 'Start assessment' }}</button>
                <RouterLink to="/app/dashboard" class="btn-secondary flex-1 text-center">Skip for now — start from Beginner</RouterLink>
            </div>
        </template>

        <LoadingState v-else-if="loading" message="Preparing your assessment…" />

        <!-- Active test -->
        <template v-else-if="attempt && !finished">
            <div class="mb-4 flex items-center justify-between">
                <h1 class="text-lg font-semibold text-ink-100">Placement assessment</h1>
                <span class="text-sm tabular-nums text-ink-400">{{ current + 1 }} / {{ attempt.questions.length }}</span>
            </div>
            <ProgressBar :value="((current) / attempt.questions.length) * 100" class="mb-6" />

            <div class="card p-6">
                <QuestionPlayer
                    :key="question.id"
                    :question="question"
                    :locked="false"
                    submit-label="Next question"
                    @answer="saveAnswer"
                >
                    <template #actions>
                        <button v-if="question.type === 'speaking'" type="button" class="btn-ghost" @click="saveAnswer('')">Skip speaking question</button>
                    </template>
                </QuestionPlayer>
            </div>

            <p class="mt-4 text-center text-xs text-ink-400">Answers are graded at the end. You can't go back, so answer carefully.</p>
        </template>

        <!-- Submitting -->
        <LoadingState v-else-if="submitting" message="Grading your assessment…" />
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ProgressBar from '../components/ProgressBar.vue'
import QuestionPlayer from '../components/QuestionPlayer.vue'

const router = useRouter()
const auth = useAuthStore()
const ui = useUiStore()

const attempt = ref(null)
const answers = ref({})
const current = ref(0)
const loading = ref(false)
const starting = ref(false)
const startError = ref(null)
const finished = ref(false)
const submitting = ref(false)

const question = computed(() => attempt.value?.questions[current.value])

onMounted(() => {
    if (auth.user?.placement_done) router.replace('/app/placement/result')
})

async function start() {
    starting.value = true
    startError.value = null
    try {
        loading.value = true
        attempt.value = await api.post('/api/placement/start')
    } catch (e) {
        startError.value = e.message
    } finally {
        starting.value = false
        loading.value = false
    }
}

function saveAnswer(answer) {
    answers.value[question.value.id] = answer
    if (current.value < attempt.value.questions.length - 1) {
        current.value++
    } else {
        submit()
    }
}

async function submit() {
    finished.value = true
    submitting.value = true
    try {
        await api.post(`/api/exams/${attempt.value.attempt_id}/submit`, { answers: answers.value })
        await auth.fetchUser()
        router.push('/app/placement/result')
    } catch (e) {
        ui.toast(e.message, 'error')
        submitting.value = false
        finished.value = false
    }
}
</script>
