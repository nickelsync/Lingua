<template>
    <div class="mx-auto max-w-4xl">
        <RouterLink to="/admin/exercises" class="text-sm text-ink-400 hover:text-ink-200">← All exercises</RouterLink>

        <LoadingState v-if="loading" message="Loading questions…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <template v-else>
            <div class="mt-3 flex flex-wrap items-center justify-between gap-3">
                <h1 class="text-xl font-bold text-ink-100">Questions <span class="text-sm font-normal text-ink-400">({{ questions.length }})</span></h1>
                <button class="btn-primary text-sm" @click="openForm()">+ Add question</button>
            </div>

            <EmptyState v-if="!questions.length" title="No questions yet" message="Add the first question to this exercise.">
                <button class="btn-primary" @click="openForm()">+ Add question</button>
            </EmptyState>
            <div v-else class="mt-4 space-y-2">
                <div v-for="(q, i) in questions" :key="q.id" class="card p-4">
                    <div class="flex items-start justify-between gap-3">
                        <div class="min-w-0">
                            <p class="text-sm font-medium text-ink-100">
                                <span class="mr-2 rounded border border-ink-700 px-1.5 py-0.5 text-[10px] uppercase text-ink-400">{{ q.type }}</span>
                                <span class="mr-2 rounded border border-ink-700 px-1.5 py-0.5 text-[10px] text-ink-400">{{ q.difficulty }}</span>
                                {{ q.prompt }}
                            </p>
                            <p v-if="q.options?.length" class="mt-1 text-xs text-ink-400">
                                <span v-for="(o, oi) in q.options" :key="o.id" :class="o.is_correct && 'font-semibold text-accent-400'">{{ o.text }}{{ oi < q.options.length - 1 ? ' · ' : '' }}</span>
                            </p>
                            <p v-else-if="q.answer_config" class="mt-1 truncate text-xs text-ink-400">{{ configSummary(q) }}</p>
                        </div>
                        <div class="flex shrink-0 gap-1.5">
                            <button class="btn-ghost text-xs" :disabled="i === 0" @click="move(i, -1)">↑</button>
                            <button class="btn-ghost text-xs" :disabled="i === questions.length - 1" @click="move(i, 1)">↓</button>
                            <button class="btn-secondary text-xs" @click="openForm(q)">Edit</button>
                            <button class="btn-ghost text-xs text-red-400" @click="remove(q)">Delete</button>
                        </div>
                    </div>
                </div>
            </div>

            <BaseModal :open="formOpen" :title="form.id ? 'Edit question' : 'New question'" wide @close="formOpen = false">
                <div class="space-y-3">
                    <div class="grid grid-cols-2 gap-3">
                        <FormField label="Type">
                            <select v-model="form.type" class="input" @change="onTypeChange">
                                <option v-for="t in types" :key="t" :value="t">{{ t }}</option>
                            </select>
                        </FormField>
                        <FormField label="Difficulty">
                            <select v-model="form.difficulty" class="input">
                                <option value="easy">easy</option><option value="medium">medium</option><option value="hard">hard</option>
                            </select>
                        </FormField>
                    </div>
                    <FormField label="Skill">
                        <select v-model="form.skill" class="input">
                            <option v-for="s in skills" :key="s" :value="s">{{ s }}</option>
                        </select>
                    </FormField>
                    <FormField label="Prompt"><textarea v-model="form.prompt" rows="2" class="input" /></FormField>
                    <FormField v-if="form.type === 'reading'" label="Reading passage"><textarea v-model="form.passage" rows="4" class="input" /></FormField>
                    <FormField v-if="form.type === 'listening'" label="Audio text (spoken via TTS)"><input v-model="form.audio_text" type="text" class="input"></FormField>

                    <!-- Options for choice types -->
                    <div v-if="isChoice" class="rounded-md border border-ink-800 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase text-ink-400">Options (check the correct one)</p>
                        <div v-for="(o, oi) in form.options" :key="oi" class="mb-2 flex items-center gap-2">
                            <input type="radio" name="correct" :checked="o.is_correct" class="h-4 w-4 accent-emerald-600" @change="setCorrect(oi)">
                            <input v-model="o.text" type="text" class="input flex-1" :placeholder="`Option ${oi + 1}`">
                            <button class="btn-ghost !px-2 text-sm" :disabled="form.options.length <= 2" @click="form.options.splice(oi, 1)">✕</button>
                        </div>
                        <button class="btn-secondary text-xs" :disabled="form.options.length >= 8" @click="form.options.push({ text: '', is_correct: false })">+ Option</button>
                    </div>

                    <!-- true/false -->
                    <FormField v-if="form.type === 'true_false'" label="Correct answer">
                        <select v-model="form.tfCorrect" class="input"><option :value="true">True</option><option :value="false">False</option></select>
                    </FormField>

                    <!-- text answers -->
                    <FormField v-if="isText" label="Accepted answers (one per line)" hint="Case and punctuation are ignored when checking.">
                        <textarea v-model="form.accepted" rows="3" class="input" />
                    </FormField>

                    <!-- rearrange -->
                    <FormField v-if="form.type === 'rearrange'" label="Correct sentence" hint="Words will be shuffled for the learner.">
                        <input v-model="form.sentence" type="text" class="input">
                    </FormField>

                    <!-- matching -->
                    <div v-if="form.type === 'matching'" class="rounded-md border border-ink-800 p-3">
                        <p class="mb-2 text-xs font-semibold uppercase text-ink-400">Pairs (left ↔ right)</p>
                        <div v-for="(p, pi) in form.pairs" :key="pi" class="mb-2 flex items-center gap-2">
                            <input v-model="p.left" type="text" class="input flex-1" placeholder="Left">
                            <span class="text-ink-400">↔</span>
                            <input v-model="p.right" type="text" class="input flex-1" placeholder="Right">
                            <button class="btn-ghost !px-2 text-sm" :disabled="form.pairs.length <= 2" @click="form.pairs.splice(pi, 1)">✕</button>
                        </div>
                        <button class="btn-secondary text-xs" @click="form.pairs.push({ left: '', right: '' })">+ Pair</button>
                    </div>

                    <!-- speaking -->
                    <FormField v-if="form.type === 'speaking'" label="Target sentence (learner repeats this)">
                        <input v-model="form.target" type="text" class="input">
                    </FormField>

                    <!-- writing -->
                    <FormField v-if="form.type === 'writing'" label="Minimum words">
                        <input v-model.number="form.minWords" type="number" min="5" max="500" class="input">
                    </FormField>

                    <FormField label="Explanation (English)"><textarea v-model="form.explanation" rows="2" class="input" /></FormField>
                    <FormField label="Explanation (Indonesian, optional)"><textarea v-model="form.explanation_id" rows="2" class="input" /></FormField>
                    <p v-if="formError" class="text-sm text-red-300">{{ formError }}</p>
                    <button class="btn-primary w-full" :disabled="!form.prompt || saving" @click="save">{{ saving ? 'Saving…' : 'Save question' }}</button>
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
const types = ['multiple_choice', 'true_false', 'fill_blank', 'translation', 'typing', 'rearrange', 'matching', 'listening', 'reading', 'speaking', 'writing']
const skills = ['grammar', 'vocabulary', 'listening', 'speaking', 'reading', 'writing', 'pronunciation', 'everyday', 'general']

const questions = ref([])
const loading = ref(true)
const error = ref(null)
const formOpen = ref(false)
const form = ref({})
const formError = ref(null)
const saving = ref(false)

const isChoice = computed(() => ['multiple_choice', 'listening', 'reading'].includes(form.value.type))
const isText = computed(() => ['fill_blank', 'translation', 'typing'].includes(form.value.type))

function configSummary(q) {
    const c = q.answer_config || {}
    if (c.accepted) return `Accepted: ${c.accepted.join(' | ')}`
    if (c.correct !== undefined) return `Correct: ${c.correct}`
    if (c.order) return `Sentence: ${c.order.join(' ')}`
    if (c.pairs) return `${c.pairs.length} pairs`
    if (c.target) return `Target: ${c.target}`
    if (c.min_words) return `Min ${c.min_words} words`
    return ''
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get(`/api/admin/exercises/${route.params.id}/questions`)
        questions.value = data.questions
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

function openForm(q = null) {
    formError.value = null
    if (q) {
        const c = q.answer_config || {}
        form.value = {
            id: q.id, type: q.type, skill: q.skill || 'general', difficulty: q.difficulty,
            prompt: q.prompt, passage: q.passage, audio_text: q.audio_text,
            explanation: q.explanation, explanation_id: q.explanation_id,
            options: (q.options || []).map((o) => ({ text: o.text, is_correct: o.is_correct })),
            tfCorrect: c.correct ?? true,
            accepted: (c.accepted || []).join('\n'),
            sentence: (c.order || []).join(' '),
            pairs: c.pairs ? c.pairs.map((p) => ({ ...p })) : [{ left: '', right: '' }, { left: '', right: '' }],
            target: c.target || '',
            minWords: c.min_words || 30,
        }
        if (form.value.options.length < 2 && isChoice.value) form.value.options = defaultOptions()
    } else {
        form.value = {
            type: 'multiple_choice', skill: 'general', difficulty: 'medium', prompt: '', passage: '', audio_text: '',
            explanation: '', explanation_id: '', options: defaultOptions(),
            tfCorrect: true, accepted: '', sentence: '', pairs: [{ left: '', right: '' }, { left: '', right: '' }], target: '', minWords: 30,
        }
    }
    formOpen.value = true
}

function defaultOptions() {
    return [{ text: '', is_correct: true }, { text: '', is_correct: false }, { text: '', is_correct: false }, { text: '', is_correct: false }]
}

function onTypeChange() {
    if (isChoice.value && (!form.value.options || form.value.options.length < 2)) form.value.options = defaultOptions()
}

function setCorrect(index) {
    form.value.options.forEach((o, i) => { o.is_correct = i === index })
}

function buildPayload() {
    const f = form.value
    const payload = {
        type: f.type, skill: f.skill, difficulty: f.difficulty, prompt: f.prompt,
        passage: f.type === 'reading' ? f.passage || null : null,
        audio_text: f.type === 'listening' ? f.audio_text || null : null,
        explanation: f.explanation || null, explanation_id: f.explanation_id || null,
    }
    if (isChoice.value) {
        payload.options = f.options.filter((o) => o.text.trim())
    } else if (f.type === 'true_false') {
        payload.answer_config = { correct: f.tfCorrect === true || f.tfCorrect === 'true' }
    } else if (isText.value) {
        payload.answer_config = { accepted: f.accepted.split('\n').map((s) => s.trim()).filter(Boolean) }
    } else if (f.type === 'rearrange') {
        payload.answer_config = { order: f.sentence.trim().split(/\s+/).filter(Boolean) }
    } else if (f.type === 'matching') {
        payload.answer_config = { pairs: f.pairs.filter((p) => p.left.trim() && p.right.trim()) }
    } else if (f.type === 'speaking') {
        payload.answer_config = { target: f.target.trim() }
    } else if (f.type === 'writing') {
        payload.answer_config = { min_words: f.minWords }
    }
    return payload
}

async function save() {
    formError.value = null
    saving.value = true
    try {
        const payload = buildPayload()
        if (form.value.id) {
            await api.patch(`/api/admin/questions/${form.value.id}`, payload)
        } else {
            await api.post(`/api/admin/exercises/${route.params.id}/questions`, payload)
        }
        formOpen.value = false
        ui.toast('Question saved.', 'success')
        load()
    } catch (e) {
        formError.value = e.message
    } finally {
        saving.value = false
    }
}

async function move(i, dir) {
    const ids = questions.value.map((q) => q.id)
    ;[ids[i], ids[i + dir]] = [ids[i + dir], ids[i]]
    try {
        await api.post('/api/admin/reorder', { type: 'questions', ids })
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function remove(q) {
    if (!confirm('Delete this question?')) return
    try {
        await api.delete(`/api/admin/questions/${q.id}`)
        questions.value = questions.value.filter((x) => x.id !== q.id)
        ui.toast('Question deleted.', 'info')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(load)
</script>
