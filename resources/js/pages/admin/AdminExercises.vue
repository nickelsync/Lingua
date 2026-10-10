<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-ink-100">Exercises & question banks</h1>
            <button class="btn-primary text-sm" @click="openForm()">+ New exercise</button>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" class="input sm:max-w-xs" placeholder="Search exercises…" @input="debouncedLoad">
            <select v-model="kindFilter" class="input sm:w-44" @change="load(1)">
                <option value="">All kinds</option>
                <option value="practice">Practice</option>
                <option value="exam_pool">Exam pool</option>
                <option value="placement">Placement</option>
            </select>
            <select v-model="levelFilter" class="input sm:w-44" @change="load(1)">
                <option value="">All levels</option>
                <option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
        </div>

        <LoadingState v-if="loading" message="Loading exercises…" />
        <ErrorState v-else-if="error" :message="error" :retry="() => load(page)" />
        <EmptyState v-else-if="!exercises.length" title="No exercises found" message="Create an exercise or adjust the filters." />
        <template v-else>
            <div class="card mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink-800 text-left text-xs uppercase text-ink-400">
                            <th class="px-4 py-3">Exercise</th><th class="px-4 py-3">Kind</th><th class="px-4 py-3">Level / lesson</th><th class="px-4 py-3">Questions</th><th class="px-4 py-3">Status</th><th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="ex in exercises" :key="ex.id" class="border-b border-ink-800/50 last:border-0 hover:bg-ink-850/40">
                            <td class="px-4 py-2.5">
                                <RouterLink :to="`/admin/exercises/${ex.id}/questions`" class="font-medium text-ink-100 hover:text-accent-400">{{ ex.title }}</RouterLink>
                            </td>
                            <td class="px-4 py-2.5"><span class="rounded-full bg-ink-800 px-2 py-0.5 text-xs text-ink-300">{{ ex.kind }}</span></td>
                            <td class="px-4 py-2.5 text-xs text-ink-400">
                                <span class="uppercase">{{ ex.level?.code || '—' }}</span><span v-if="ex.lesson"> · {{ ex.lesson.title }}</span>
                            </td>
                            <td class="px-4 py-2.5 tabular-nums text-ink-300">{{ ex.questions_count }}<span v-if="ex.question_count > 0" class="text-xs text-ink-400"> (draws {{ ex.question_count }})</span></td>
                            <td class="px-4 py-2.5">
                                <span class="rounded-full px-2 py-0.5 text-xs" :class="ex.is_published ? 'bg-accent-600/15 text-accent-400' : 'bg-ink-800 text-ink-400'">{{ ex.is_published ? 'Published' : 'Draft' }}</span>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <RouterLink :to="`/admin/exercises/${ex.id}/questions`" class="btn-secondary text-xs">Questions</RouterLink>
                                    <button class="btn-ghost text-xs" @click="openForm(ex)">Edit</button>
                                    <button class="btn-ghost text-xs text-red-400" @click="remove(ex)">Delete</button>
                                </div>
                            </td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="lastPage > 1" class="mt-4 flex items-center justify-center gap-3">
                <button class="btn-secondary" :disabled="page <= 1" @click="load(page - 1)">← Prev</button>
                <span class="text-sm text-ink-400">{{ page }} / {{ lastPage }}</span>
                <button class="btn-secondary" :disabled="page >= lastPage" @click="load(page + 1)">Next →</button>
            </div>
        </template>

        <BaseModal :open="formOpen" :title="form.id ? 'Edit exercise' : 'New exercise'" @close="formOpen = false">
            <div class="space-y-3">
                <FormField label="Title"><input v-model="form.title" type="text" class="input"></FormField>
                <FormField label="Kind" hint="Practice = attached to a lesson. Exam pool = level final exam bank. Placement = placement test bank.">
                    <select v-model="form.kind" class="input" :disabled="!!form.id">
                        <option value="practice">practice</option>
                        <option value="exam_pool">exam_pool</option>
                        <option value="placement">placement</option>
                    </select>
                </FormField>
                <FormField v-if="form.kind === 'practice'" label="Lesson">
                    <select v-model="form.lesson_id" class="input">
                        <option :value="null">— none —</option>
                        <option v-for="l in lessons" :key="l.id" :value="l.id">{{ l.title }}</option>
                    </select>
                </FormField>
                <FormField v-if="form.kind !== 'placement'" label="Level">
                    <select v-model="form.cefr_level_id" class="input">
                        <option :value="null">— none —</option>
                        <option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Questions per session" hint="0 = use all"><input v-model.number="form.question_count" type="number" min="0" max="100" class="input"></FormField>
                    <FormField label="XP reward"><input v-model.number="form.xp_reward" type="number" min="0" max="200" class="input"></FormField>
                </div>
                <label class="flex items-center gap-2 text-sm text-ink-300"><input v-model="form.is_published" type="checkbox" class="h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">Published</label>
                <button class="btn-primary w-full" :disabled="!form.title || saving" @click="save">{{ saving ? 'Saving…' : 'Save exercise' }}</button>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
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
const exercises = ref([])
const levels = ref([])
const lessons = ref([])
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const kindFilter = ref('')
const levelFilter = ref('')
const loading = ref(true)
const error = ref(null)
const formOpen = ref(false)
const form = ref({})
const saving = ref(false)
let timer = null

function debouncedLoad() {
    clearTimeout(timer)
    timer = setTimeout(() => load(1), 350)
}

async function load(p = 1) {
    loading.value = true
    error.value = null
    page.value = p
    try {
        const params = new URLSearchParams({ page: p })
        if (search.value) params.set('q', search.value)
        if (kindFilter.value) params.set('kind', kindFilter.value)
        if (levelFilter.value) params.set('level_id', levelFilter.value)
        if (route.query.lesson_id) params.set('lesson_id', route.query.lesson_id)
        const [exRes, lvRes, lsRes] = await Promise.all([
            api.get(`/api/admin/exercises?${params}`),
            levels.value.length ? Promise.resolve(null) : api.get('/api/admin/levels'),
            lessons.value.length ? Promise.resolve(null) : api.get('/api/admin/lessons?per_page=100'),
        ])
        exercises.value = exRes.exercises.data
        lastPage.value = exRes.exercises.last_page
        if (lvRes) levels.value = lvRes.levels
        if (lsRes) lessons.value = lsRes.lessons.data
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

function openForm(ex = null) {
    form.value = ex
        ? { ...ex }
        : { title: '', kind: 'practice', lesson_id: route.query.lesson_id ? Number(route.query.lesson_id) : null, cefr_level_id: null, question_count: 0, xp_reward: 10, is_published: false }
    formOpen.value = true
}

async function save() {
    saving.value = true
    try {
        if (form.value.id) {
            await api.patch(`/api/admin/exercises/${form.value.id}`, form.value)
        } else {
            await api.post('/api/admin/exercises', form.value)
        }
        formOpen.value = false
        ui.toast('Exercise saved.', 'success')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function remove(ex) {
    if (!confirm(`Delete "${ex.title}" and all its questions?`)) return
    try {
        await api.delete(`/api/admin/exercises/${ex.id}`)
        ui.toast('Exercise deleted.', 'info')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(() => load(1))
</script>
