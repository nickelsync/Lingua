<template>
    <div class="mx-auto max-w-4xl">
        <LoadingState v-if="loading" message="Loading lesson…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <template v-else-if="lesson">
            <RouterLink to="/admin/lessons" class="text-sm text-ink-400 hover:text-ink-200">← All lessons</RouterLink>

            <!-- Meta -->
            <div class="card mt-3 p-6">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <h1 class="text-xl font-bold text-ink-100">{{ lesson.title }}</h1>
                    <button class="rounded-full px-3 py-1 text-xs font-medium" :class="lesson.is_published ? 'bg-accent-600/15 text-accent-400' : 'bg-ink-800 text-ink-400'" @click="togglePublish">
                        {{ lesson.is_published ? '● Published' : '○ Draft — click to publish' }}
                    </button>
                </div>
                <div class="mt-4 grid gap-4 sm:grid-cols-2">
                    <FormField label="Title"><input v-model="lesson.title" type="text" class="input"></FormField>
                    <FormField label="Objective"><input v-model="lesson.objective" type="text" class="input"></FormField>
                    <FormField label="XP reward"><input v-model.number="lesson.xp_reward" type="number" min="0" max="500" class="input"></FormField>
                    <FormField label="Estimated minutes"><input v-model.number="lesson.estimated_minutes" type="number" min="1" max="120" class="input"></FormField>
                </div>
                <FormField class="mt-4" label="Prerequisites" hint="Lessons the learner must complete first.">
                    <select multiple class="input h-28" :value="prereqIds" @change="onPrereqChange">
                        <option v-for="opt in prereqOptions" :key="opt.id" :value="opt.id">{{ opt.title }}</option>
                    </select>
                </FormField>
                <button class="btn-primary mt-4" :disabled="saving" @click="saveMeta">{{ saving ? 'Saving…' : 'Save details' }}</button>
            </div>

            <!-- Sections -->
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink-100">Sections</h2>
                    <button class="btn-secondary text-sm" @click="openSection()">+ Add section</button>
                </div>
                <EmptyState v-if="!lesson.sections.length" title="No sections" message="Add explanation, vocabulary, dialogue, or listening sections." />
                <div v-else class="mt-3 space-y-2">
                    <div v-for="(s, i) in lesson.sections" :key="s.id" class="card flex items-center justify-between gap-3 p-3.5">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink-100"><span class="mr-2 rounded border border-ink-700 px-1.5 py-0.5 text-[10px] uppercase text-ink-400">{{ s.type }}</span>{{ s.title || '(untitled)' }}</p>
                            <p class="mt-0.5 line-clamp-1 text-xs text-ink-400">{{ s.body || metaSummary(s) }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1.5">
                            <button class="btn-ghost text-xs" :disabled="i === 0" @click="moveSection(i, -1)">↑</button>
                            <button class="btn-ghost text-xs" :disabled="i === lesson.sections.length - 1" @click="moveSection(i, 1)">↓</button>
                            <button class="btn-secondary text-xs" @click="openSection(s)">Edit</button>
                            <button class="btn-ghost text-xs text-red-400" @click="removeSection(s)">Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Exercises -->
            <div class="mt-6">
                <div class="flex items-center justify-between">
                    <h2 class="text-lg font-semibold text-ink-100">Exercises</h2>
                    <RouterLink :to="`/admin/exercises?lesson_id=${lesson.id}`" class="btn-secondary text-sm">Manage exercises →</RouterLink>
                </div>
                <EmptyState v-if="!lesson.exercises.length" title="No exercises" message="Add exercises from the Exercises page." />
                <div v-else class="mt-3 space-y-2">
                    <RouterLink v-for="ex in lesson.exercises" :key="ex.id" :to="`/admin/exercises/${ex.id}/questions`" class="card flex items-center justify-between p-3.5 hover:border-ink-500">
                        <p class="text-sm font-medium text-ink-100">{{ ex.title }}</p>
                        <p class="text-xs text-ink-400">{{ ex.questions?.length ?? 0 }} questions →</p>
                    </RouterLink>
                </div>
            </div>

            <!-- Section modal -->
            <BaseModal :open="sectionModal" :title="sectionForm.id ? 'Edit section' : 'New section'" wide @close="sectionModal = false">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <FormField label="Type">
                            <select v-model="sectionForm.type" class="input">
                                <option v-for="t in sectionTypes" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </FormField>
                        <FormField label="Title"><input v-model="sectionForm.title" type="text" class="input"></FormField>
                    </div>
                    <FormField label="Body (English)"><textarea v-model="sectionForm.body" rows="4" class="input" /></FormField>
                    <FormField label="Body (Indonesian, optional)"><textarea v-model="sectionForm.body_id" rows="2" class="input" /></FormField>
                    <FormField v-if="['vocabulary', 'examples', 'dialogue', 'listening'].includes(sectionForm.type)" label="Structured data (JSON)" :error="metaError" hint='vocabulary: {"words":[{"word","ipa","meaning_id","example"}]} · examples: {"items":[{"en","id"}]} · dialogue: {"lines":[{"speaker","text"}]} · listening: {"audio_text":"…"}'>
                        <textarea v-model="sectionForm.metaJson" rows="6" class="input font-mono text-xs" />
                    </FormField>
                    <button class="btn-primary w-full" :disabled="savingSection" @click="saveSection">{{ savingSection ? 'Saving…' : 'Save section' }}</button>
                </div>
            </BaseModal>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../../api/client'
import { useUiStore } from '../../stores/ui'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import EmptyState from '../../components/EmptyState.vue'
import BaseModal from '../../components/BaseModal.vue'
import FormField from '../../components/FormField.vue'

const route = useRoute()
const ui = useUiStore()
const sectionTypes = ['intro', 'explanation', 'examples', 'vocabulary', 'dialogue', 'reading', 'listening', 'note', 'tip']

const lesson = ref(null)
const allLessons = ref([])
const loading = ref(true)
const error = ref(null)
const saving = ref(false)
const prereqIds = ref([])

const sectionModal = ref(false)
const sectionForm = ref({})
const savingSection = ref(false)
const metaError = ref(null)

const prereqOptions = computed(() => allLessons.value.filter((l) => l.id !== lesson.value?.id))

function metaSummary(s) {
    if (!s.meta) return ''
    if (s.meta.words) return `${s.meta.words.length} words`
    if (s.meta.lines) return `${s.meta.lines.length} dialogue lines`
    if (s.meta.items) return `${s.meta.items.length} examples`
    if (s.meta.audio_text) return `audio: ${s.meta.audio_text}`
    return ''
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get(`/api/admin/lessons/${route.params.id}`)
        lesson.value = data.lesson
        prereqIds.value = (data.lesson.prerequisites || []).map((p) => String(p.id))
        api.get('/api/admin/lessons?per_page=100').then((d) => { allLessons.value = d.lessons.data }).catch(() => {})
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

function onPrereqChange(event) {
    prereqIds.value = Array.from(event.target.selectedOptions).map((o) => o.value)
}

async function saveMeta() {
    saving.value = true
    try {
        await api.patch(`/api/admin/lessons/${lesson.value.id}`, {
            title: lesson.value.title,
            objective: lesson.value.objective,
            xp_reward: lesson.value.xp_reward,
            estimated_minutes: lesson.value.estimated_minutes,
            prerequisite_ids: prereqIds.value.map(Number),
        })
        ui.toast('Lesson saved.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function togglePublish() {
    try {
        await api.patch(`/api/admin/lessons/${lesson.value.id}`, { is_published: !lesson.value.is_published })
        lesson.value.is_published = !lesson.value.is_published
        ui.toast(lesson.value.is_published ? 'Published.' : 'Unpublished.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

function openSection(s = null) {
    metaError.value = null
    sectionForm.value = s
        ? { ...s, metaJson: s.meta ? JSON.stringify(s.meta, null, 2) : '' }
        : { type: 'explanation', title: '', body: '', body_id: '', metaJson: '' }
    sectionModal.value = true
}

async function saveSection() {
    metaError.value = null
    let meta = null
    if (sectionForm.value.metaJson?.trim()) {
        try { meta = JSON.parse(sectionForm.value.metaJson) } catch { metaError.value = 'Invalid JSON.'; return }
    }
    savingSection.value = true
    const payload = {
        type: sectionForm.value.type,
        title: sectionForm.value.title || null,
        body: sectionForm.value.body || null,
        body_id: sectionForm.value.body_id || null,
        meta,
    }
    try {
        if (sectionForm.value.id) {
            await api.patch(`/api/admin/sections/${sectionForm.value.id}`, payload)
        } else {
            await api.post(`/api/admin/lessons/${lesson.value.id}/sections`, payload)
        }
        sectionModal.value = false
        ui.toast('Section saved.', 'success')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        savingSection.value = false
    }
}

async function moveSection(i, dir) {
    const ids = lesson.value.sections.map((s) => s.id)
    ;[ids[i], ids[i + dir]] = [ids[i + dir], ids[i]]
    try {
        await api.post('/api/admin/reorder', { type: 'sections', ids })
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function removeSection(s) {
    if (!confirm('Delete this section?')) return
    try {
        await api.delete(`/api/admin/sections/${s.id}`)
        lesson.value.sections = lesson.value.sections.filter((x) => x.id !== s.id)
        ui.toast('Section deleted.', 'info')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(load)
</script>
