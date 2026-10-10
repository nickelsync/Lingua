<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-ink-100">Lessons</h1>
            <button class="btn-primary text-sm" @click="createModal = true">+ New lesson</button>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" class="input sm:max-w-xs" placeholder="Search lessons…" @input="debouncedLoad">
            <select v-model="moduleFilter" class="input sm:w-64" @change="load(1)">
                <option value="">All modules</option>
                <option v-for="m in modules" :key="m.id" :value="m.id">{{ m.course?.title }} → {{ m.title }}</option>
            </select>
        </div>

        <LoadingState v-if="loading" message="Loading lessons…" />
        <ErrorState v-else-if="error" :message="error" :retry="() => load(page)" />
        <EmptyState v-else-if="!lessons.length" title="No lessons found" message="Create a lesson or adjust the filters.">
            <button class="btn-primary" @click="createModal = true">+ New lesson</button>
        </EmptyState>
        <template v-else>
            <div class="card mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink-800 text-left text-xs uppercase text-ink-400">
                            <th class="px-4 py-3">Lesson</th><th class="px-4 py-3">Module</th><th class="px-4 py-3">Content</th><th class="px-4 py-3">Status</th><th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="l in lessons" :key="l.id" class="border-b border-ink-800/50 last:border-0 hover:bg-ink-850/40">
                            <td class="px-4 py-2.5">
                                <RouterLink :to="`/admin/lessons/${l.id}`" class="font-medium text-ink-100 hover:text-accent-400">{{ l.title }}</RouterLink>
                                <p class="text-xs text-ink-400">{{ l.slug }}</p>
                            </td>
                            <td class="px-4 py-2.5 text-ink-300">{{ l.module?.course?.title }} → {{ l.module?.title }}</td>
                            <td class="px-4 py-2.5 text-xs text-ink-400">{{ l.sections_count }} sections · {{ l.exercises_count }} exercises</td>
                            <td class="px-4 py-2.5">
                                <button class="rounded-full px-2 py-0.5 text-xs" :class="l.is_published ? 'bg-accent-600/15 text-accent-400' : 'bg-ink-800 text-ink-400'" @click="togglePublish(l)">
                                    {{ l.is_published ? 'Published' : 'Draft' }}
                                </button>
                            </td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button class="btn-ghost text-xs" title="Move up" @click="move(l, -1)">↑</button>
                                    <button class="btn-ghost text-xs" title="Move down" @click="move(l, 1)">↓</button>
                                    <button class="btn-ghost text-xs" @click="duplicate(l)">Duplicate</button>
                                    <RouterLink :to="`/admin/lessons/${l.id}`" class="btn-secondary text-xs">Edit</RouterLink>
                                    <button class="btn-ghost text-xs text-red-400" @click="remove(l)">Delete</button>
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

        <BaseModal :open="createModal" title="New lesson" @close="createModal = false">
            <div class="space-y-3">
                <FormField label="Module"><select v-model="form.module_id" class="input"><option v-for="m in modules" :key="m.id" :value="m.id">{{ m.course?.title }} → {{ m.title }}</option></select></FormField>
                <FormField label="Title"><input v-model="form.title" type="text" class="input"></FormField>
                <FormField label="Objective"><textarea v-model="form.objective" rows="2" class="input" /></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="XP reward"><input v-model.number="form.xp_reward" type="number" min="0" max="500" class="input"></FormField>
                    <FormField label="Est. minutes"><input v-model.number="form.estimated_minutes" type="number" min="1" max="120" class="input"></FormField>
                </div>
                <button class="btn-primary w-full" :disabled="!form.title || !form.module_id || saving" @click="create">{{ saving ? 'Creating…' : 'Create & edit content' }}</button>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../../api/client'
import { useUiStore } from '../../stores/ui'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import EmptyState from '../../components/EmptyState.vue'
import BaseModal from '../../components/BaseModal.vue'
import FormField from '../../components/FormField.vue'

const router = useRouter()
const ui = useUiStore()
const lessons = ref([])
const modules = ref([])
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const moduleFilter = ref('')
const loading = ref(true)
const error = ref(null)
const createModal = ref(false)
const saving = ref(false)
const form = ref({ module_id: null, title: '', objective: '', xp_reward: 20, estimated_minutes: 10 })
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
        if (moduleFilter.value) params.set('module_id', moduleFilter.value)
        const [lessonsRes, modulesRes] = await Promise.all([
            api.get(`/api/admin/lessons?${params}`),
            modules.value.length ? Promise.resolve(null) : api.get('/api/admin/modules'),
        ])
        lessons.value = lessonsRes.lessons.data
        lastPage.value = lessonsRes.lessons.last_page
        if (modulesRes) {
            modules.value = modulesRes.modules
            if (!form.value.module_id) form.value.module_id = modulesRes.modules[0]?.id
        }
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function create() {
    saving.value = true
    try {
        const data = await api.post('/api/admin/lessons', form.value)
        router.push(`/admin/lessons/${data.lesson.id}`)
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function togglePublish(l) {
    try {
        await api.patch(`/api/admin/lessons/${l.id}`, { is_published: !l.is_published })
        l.is_published = !l.is_published
        ui.toast(l.is_published ? 'Lesson published.' : 'Lesson unpublished.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function duplicate(l) {
    try {
        const data = await api.post(`/api/admin/lessons/${l.id}/duplicate`)
        ui.toast('Lesson duplicated (as draft).', 'success')
        router.push(`/admin/lessons/${data.lesson.id}`)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function move(l, dir) {
    const siblings = lessons.value.filter((x) => x.module_id === l.module_id)
    const idx = siblings.findIndex((x) => x.id === l.id)
    const target = idx + dir
    if (target < 0 || target >= siblings.length) return
    const ids = siblings.map((x) => x.id)
    ;[ids[idx], ids[target]] = [ids[target], ids[idx]]
    try {
        await api.post('/api/admin/reorder', { type: 'lessons', ids })
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function remove(l) {
    if (!confirm(`Delete "${l.title}"? This also deletes its sections and exercises.`)) return
    try {
        await api.delete(`/api/admin/lessons/${l.id}`)
        ui.toast('Lesson deleted.', 'info')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(() => load(1))
</script>
