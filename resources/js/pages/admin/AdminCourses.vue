<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-ink-100">Courses & modules</h1>
            <div class="flex gap-2">
                <button class="btn-secondary text-sm" @click="openModule()">+ Module</button>
                <button class="btn-primary text-sm" @click="openCourse()">+ Course</button>
            </div>
        </div>

        <select v-model="levelFilter" class="input mt-4 sm:max-w-xs" @change="load">
            <option value="">All levels</option>
            <option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option>
        </select>

        <LoadingState v-if="loading" message="Loading courses…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <EmptyState v-else-if="!courses.length" title="No courses" message="Create your first course to start building curriculum.">
            <button class="btn-primary" @click="openCourse()">+ New course</button>
        </EmptyState>
        <div v-else class="mt-4 space-y-4">
            <div v-for="course in courses" :key="course.id" class="card p-5">
                <div class="flex flex-wrap items-start justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-ink-100">
                            {{ course.title }}
                            <span class="ml-2 rounded border border-ink-700 px-1.5 py-0.5 text-[10px] uppercase text-ink-400">{{ course.level?.code }}</span>
                            <span class="ml-1 rounded border border-ink-700 px-1.5 py-0.5 text-[10px] text-ink-400">{{ course.skill }}</span>
                            <span v-if="!course.is_published" class="ml-1 rounded bg-ink-800 px-1.5 py-0.5 text-[10px] text-ink-400">Draft</span>
                        </h2>
                        <p class="mt-0.5 text-xs text-ink-400">{{ course.modules_count }} modules · {{ course.description }}</p>
                    </div>
                    <div class="flex gap-1.5">
                        <button class="btn-ghost text-xs" @click="move('courses', course, -1)" title="Move up">↑</button>
                        <button class="btn-ghost text-xs" @click="move('courses', course, 1)" title="Move down">↓</button>
                        <button class="btn-secondary text-xs" @click="openCourse(course)">Edit</button>
                        <button class="btn-ghost text-xs text-red-400" @click="removeCourse(course)">Delete</button>
                    </div>
                </div>
                <!-- modules of this course -->
                <div v-if="modulesByCourse[course.id]?.length" class="mt-4 space-y-2 border-t border-ink-800 pt-3">
                    <div v-for="mod in modulesByCourse[course.id]" :key="mod.id" class="flex items-center justify-between gap-3 rounded-md bg-ink-850/60 px-3 py-2">
                        <p class="text-sm text-ink-200">{{ mod.title }} <span class="ml-1 text-xs text-ink-400">{{ mod.lessons_count }} lessons</span><span v-if="!mod.is_published" class="ml-1 rounded bg-ink-800 px-1.5 text-[10px] text-ink-400">Draft</span></p>
                        <div class="flex gap-1.5">
                            <button class="btn-ghost text-xs" @click="move('modules', mod, -1, course.id)" title="Move up">↑</button>
                            <button class="btn-ghost text-xs" @click="move('modules', mod, 1, course.id)" title="Move down">↓</button>
                            <button class="btn-ghost text-xs" @click="openModule(mod)">Edit</button>
                            <button class="btn-ghost text-xs text-red-400" @click="removeModule(mod)">Delete</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- Course modal -->
        <BaseModal :open="courseModal" :title="courseForm.id ? 'Edit course' : 'New course'" @close="courseModal = false">
            <div class="space-y-3">
                <FormField label="Level"><select v-model="courseForm.cefr_level_id" class="input"><option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option></select></FormField>
                <FormField label="Skill"><select v-model="courseForm.skill" class="input"><option v-for="s in skills" :key="s" :value="s">{{ s }}</option></select></FormField>
                <FormField label="Title"><input v-model="courseForm.title" type="text" class="input"></FormField>
                <FormField label="Description"><textarea v-model="courseForm.description" rows="2" class="input" /></FormField>
                <label class="flex items-center gap-2 text-sm text-ink-300"><input v-model="courseForm.is_published" type="checkbox" class="h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">Published</label>
                <button class="btn-primary w-full" :disabled="!courseForm.title || !courseForm.cefr_level_id || saving" @click="saveCourse">{{ saving ? 'Saving…' : 'Save course' }}</button>
            </div>
        </BaseModal>

        <!-- Module modal -->
        <BaseModal :open="moduleModal" :title="moduleForm.id ? 'Edit module' : 'New module'" @close="moduleModal = false">
            <div class="space-y-3">
                <FormField label="Course"><select v-model="moduleForm.course_id" class="input"><option v-for="c in courses" :key="c.id" :value="c.id">{{ c.level?.code?.toUpperCase() }} — {{ c.title }}</option></select></FormField>
                <FormField label="Title"><input v-model="moduleForm.title" type="text" class="input"></FormField>
                <FormField label="Description"><textarea v-model="moduleForm.description" rows="2" class="input" /></FormField>
                <label class="flex items-center gap-2 text-sm text-ink-300"><input v-model="moduleForm.is_published" type="checkbox" class="h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">Published</label>
                <button class="btn-primary w-full" :disabled="!moduleForm.title || !moduleForm.course_id || saving" @click="saveModule">{{ saving ? 'Saving…' : 'Save module' }}</button>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../../api/client'
import { useUiStore } from '../../stores/ui'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import EmptyState from '../../components/EmptyState.vue'
import BaseModal from '../../components/BaseModal.vue'
import FormField from '../../components/FormField.vue'

const ui = useUiStore()
const skills = ['grammar', 'vocabulary', 'listening', 'speaking', 'reading', 'writing', 'pronunciation', 'everyday']
const levels = ref([])
const courses = ref([])
const modules = ref([])
const levelFilter = ref('')
const loading = ref(true)
const error = ref(null)
const saving = ref(false)

const courseModal = ref(false)
const courseForm = ref({})
const moduleModal = ref(false)
const moduleForm = ref({})

const modulesByCourse = computed(() => {
    const map = {}
    for (const m of modules.value) (map[m.course_id] ||= []).push(m)
    return map
})

async function load() {
    loading.value = true
    error.value = null
    try {
        const params = levelFilter.value ? `?level_id=${levelFilter.value}` : ''
        const [levelsRes, coursesRes, modulesRes] = await Promise.all([
            levels.value.length ? Promise.resolve(null) : api.get('/api/admin/levels'),
            api.get(`/api/admin/courses${params}`),
            api.get('/api/admin/modules'),
        ])
        if (levelsRes) levels.value = levelsRes.levels
        courses.value = coursesRes.courses
        modules.value = modulesRes.modules
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

function openCourse(course = null) {
    courseForm.value = course
        ? { ...course }
        : { cefr_level_id: levelFilter.value || levels.value[0]?.id, skill: 'grammar', title: '', description: '', is_published: false }
    courseModal.value = true
}

async function saveCourse() {
    saving.value = true
    try {
        if (courseForm.value.id) {
            await api.patch(`/api/admin/courses/${courseForm.value.id}`, courseForm.value)
        } else {
            await api.post('/api/admin/courses', courseForm.value)
        }
        courseModal.value = false
        ui.toast('Course saved.', 'success')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function removeCourse(course) {
    if (!confirm(`Delete "${course.title}" and all its modules/lessons?`)) return
    try {
        await api.delete(`/api/admin/courses/${course.id}`)
        ui.toast('Course deleted.', 'info')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

function openModule(mod = null) {
    moduleForm.value = mod
        ? { ...mod }
        : { course_id: courses.value[0]?.id, title: '', description: '', is_published: false }
    moduleModal.value = true
}

async function saveModule() {
    saving.value = true
    try {
        if (moduleForm.value.id) {
            await api.patch(`/api/admin/modules/${moduleForm.value.id}`, moduleForm.value)
        } else {
            await api.post('/api/admin/modules', moduleForm.value)
        }
        moduleModal.value = false
        ui.toast('Module saved.', 'success')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function removeModule(mod) {
    if (!confirm(`Delete module "${mod.title}" and its lessons?`)) return
    try {
        await api.delete(`/api/admin/modules/${mod.id}`)
        ui.toast('Module deleted.', 'info')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function move(type, item, dir, courseId = null) {
    const list = type === 'courses'
        ? courses.value.filter((c) => c.cefr_level_id === item.cefr_level_id)
        : modulesByCourse.value[courseId] || []
    const idx = list.findIndex((x) => x.id === item.id)
    const target = idx + dir
    if (target < 0 || target >= list.length) return
    const ids = list.map((x) => x.id)
    ;[ids[idx], ids[target]] = [ids[target], ids[idx]]
    try {
        await api.post('/api/admin/reorder', { type, ids })
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(load)
</script>
