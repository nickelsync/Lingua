<template>
    <div class="mx-auto max-w-3xl">
        <LoadingState v-if="loading" message="Loading lesson…" />
        <template v-else-if="blocked">
            <div class="card p-8 text-center">
                <p class="text-3xl" aria-hidden="true">🔒</p>
                <h1 class="mt-3 text-xl font-semibold text-ink-100">Prerequisites required</h1>
                <p class="mt-2 text-sm text-ink-400">Complete these lessons first:</p>
                <ul class="mx-auto mt-4 max-w-sm space-y-2">
                    <li v-for="p in blocked.missing_prerequisites" :key="p.id">
                        <RouterLink :to="`/app/lesson/${p.slug}`" class="block rounded-md border border-ink-700 bg-ink-850 px-4 py-2.5 text-sm text-ink-200 hover:border-ink-500">{{ p.title }}</RouterLink>
                    </li>
                </ul>
                <RouterLink to="/app/learn" class="btn-secondary mt-6">Back to learning path</RouterLink>
            </div>
        </template>
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <template v-else-if="lesson">
            <!-- Header -->
            <div class="flex items-start justify-between gap-4">
                <div>
                    <RouterLink :to="`/app/learn/level/${lesson.level.id}`" class="text-sm text-ink-400 hover:text-ink-200">← {{ lesson.level.name }} · {{ lesson.course.title }}</RouterLink>
                    <h1 class="mt-1 text-2xl font-bold text-ink-100">{{ lesson.title }}</h1>
                    <p v-if="lesson.objective" class="mt-1 text-ink-400">{{ lesson.objective }}</p>
                    <p class="mt-2 text-xs text-ink-400">~{{ lesson.estimated_minutes }} min · +{{ lesson.xp_reward }} XP
                        <span v-if="lesson.completed" class="ml-2 rounded-full border border-accent-600/50 bg-accent-600/10 px-2 py-0.5 text-[10px] font-semibold text-accent-400">✓ Completed</span>
                    </p>
                </div>
                <div class="flex shrink-0 gap-2">
                    <button class="btn-ghost px-2.5" :title="bookmarked ? 'Remove bookmark' : 'Bookmark lesson'" @click="toggleBookmark">
                        {{ bookmarked ? '★' : '☆' }}
                    </button>
                    <button class="btn-ghost px-2.5" title="Add note" @click="noteOpen = true">✎</button>
                </div>
            </div>

            <!-- Sections -->
            <div class="mt-6 space-y-5">
                <LessonSection v-for="section in lesson.sections" :key="section.id" :section="section" @save-word="saveWord" />
            </div>

            <!-- Exercises -->
            <div v-if="lesson.exercises.length" class="mt-8">
                <h2 class="mb-3 text-lg font-semibold text-ink-100">Exercises</h2>
                <div class="space-y-3">
                    <div v-for="ex in lesson.exercises" :key="ex.id" class="card flex items-center justify-between gap-4 p-4">
                        <div>
                            <h3 class="text-sm font-semibold text-ink-100">{{ ex.title }}</h3>
                            <p class="text-xs text-ink-400">
                                {{ ex.question_total }} questions · +{{ ex.xp_reward }} XP
                                <span v-if="ex.best_score !== null" class="ml-1" :class="ex.best_score >= 80 ? 'text-accent-400' : 'text-ink-300'">· Best: {{ ex.best_score }}%</span>
                            </p>
                        </div>
                        <RouterLink :to="`/app/exercise/${ex.id}`" class="shrink-0" :class="ex.attempts > 0 ? 'btn-secondary' : 'btn-primary'">
                            {{ ex.attempts > 0 ? 'Retry' : 'Start' }}
                        </RouterLink>
                    </div>
                </div>
            </div>

            <!-- Complete -->
            <div class="card mt-8 p-6 text-center">
                <template v-if="lesson.completed">
                    <p class="font-semibold text-accent-400">✓ Lesson completed</p>
                    <RouterLink v-if="nextLesson" :to="`/app/lesson/${nextLesson.slug}`" class="btn-primary mt-4">Next: {{ nextLesson.title }} →</RouterLink>
                    <RouterLink v-else :to="`/app/learn/level/${lesson.level.id}`" class="btn-primary mt-4">Back to level</RouterLink>
                </template>
                <template v-else>
                    <p class="text-sm text-ink-400">{{ lesson.exercises.length ? 'Attempt every exercise above, then mark the lesson complete.' : 'Read through the material, then mark the lesson complete.' }}</p>
                    <button class="btn-primary mt-4" :disabled="completing" @click="complete">{{ completing ? 'Saving…' : `Complete lesson (+${lesson.xp_reward} XP)` }}</button>
                    <p v-if="completeError" class="mt-3 text-sm text-red-300">{{ completeError }}</p>
                </template>
            </div>

            <!-- Note modal -->
            <BaseModal :open="noteOpen" title="Note for this lesson" @close="noteOpen = false">
                <textarea v-model="noteBody" rows="5" class="input" placeholder="Write your note…" />
                <button class="btn-primary mt-4 w-full" :disabled="!noteBody.trim() || noteSaving" @click="saveNote">{{ noteSaving ? 'Saving…' : 'Save note' }}</button>
            </BaseModal>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref, watch } from 'vue'
import { useRoute } from 'vue-router'
import { api, queueAction } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import BaseModal from '../components/BaseModal.vue'
import LessonSection from '../components/LessonSection.vue'

const route = useRoute()
const auth = useAuthStore()
const ui = useUiStore()

const lesson = ref(null)
const nextLesson = ref(null)
const loading = ref(true)
const error = ref(null)
const blocked = ref(null)
const completing = ref(false)
const completeError = ref(null)
const bookmarked = ref(false)
const noteOpen = ref(false)
const noteBody = ref('')
const noteSaving = ref(false)

async function load() {
    loading.value = true
    error.value = null
    blocked.value = null
    try {
        const data = await api.get(`/api/lessons/${route.params.slug}`)
        lesson.value = data.lesson
        nextLesson.value = data.next_lesson
    } catch (e) {
        if (e.status === 403 && e.body?.missing_prerequisites) blocked.value = e.body
        if (!blocked.value) error.value = e.message
    } finally {
        loading.value = false
    }
}

async function complete() {
    completing.value = true
    completeError.value = null
    try {
        const data = await api.post(`/api/lessons/${lesson.value.id}/complete`)
        lesson.value.completed = true
        ui.toast(data.xp_awarded > 0 ? `Lesson complete! +${data.xp_awarded} XP` : 'Lesson complete!', 'success')
        auth.fetchUser()
    } catch (e) {
        if (!navigator.onLine) {
            queueAction({ key: `complete-lesson-${lesson.value.id}`, method: 'POST', url: `/api/lessons/${lesson.value.id}/complete` })
            lesson.value.completed = true
            ui.toast('Saved offline — will sync when you reconnect.', 'info')
        } else {
            completeError.value = e.message
        }
    } finally {
        completing.value = false
    }
}

async function toggleBookmark() {
    try {
        const data = await api.post('/api/bookmarks/toggle', { type: 'lesson', id: lesson.value.id })
        bookmarked.value = data.bookmarked
        ui.toast(data.bookmarked ? 'Lesson bookmarked.' : 'Bookmark removed.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function saveNote() {
    noteSaving.value = true
    try {
        await api.post('/api/notes', { lesson_id: lesson.value.id, title: lesson.value.title, body: noteBody.value })
        noteOpen.value = false
        noteBody.value = ''
        ui.toast('Note saved.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        noteSaving.value = false
    }
}

async function saveWord(word) {
    try {
        // Look up the seeded vocabulary entry by exact word.
        const data = await api.get(`/api/vocabulary?q=${encodeURIComponent(word.word)}`)
        const match = data.data.find((v) => v.word.toLowerCase() === word.word.toLowerCase())
        if (!match) {
            ui.toast('This word is not in the vocabulary bank yet.', 'info')
            return
        }
        await api.post(`/api/vocabulary/${match.id}/save`)
        ui.toast(`"${match.word}" added to your vocabulary.`, 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

watch(() => route.params.slug, () => { if (route.params.slug) load() })
onMounted(load)
</script>
