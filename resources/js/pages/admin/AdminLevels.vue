<template>
    <div>
        <h1 class="text-2xl font-bold text-ink-100">CEFR Levels</h1>
        <p class="mt-1 text-ink-400">Adjust exam settings per level. The order and codes are fixed.</p>

        <LoadingState v-if="loading" message="Loading levels…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <div v-else class="mt-6 space-y-3">
            <div v-for="level in levels" :key="level.id" class="card p-5">
                <div class="flex flex-wrap items-center justify-between gap-3">
                    <div>
                        <h2 class="font-semibold text-ink-100">{{ level.name }} <span class="ml-1 text-xs uppercase text-ink-400">{{ level.code }}</span></h2>
                        <p class="text-xs text-ink-400">{{ level.courses_count }} courses</p>
                    </div>
                    <button class="btn-secondary text-sm" @click="editing = editing === level.id ? null : level.id">{{ editing === level.id ? 'Close' : 'Edit' }}</button>
                </div>
                <div v-if="editing === level.id" class="mt-4 grid gap-4 border-t border-ink-800 pt-4 sm:grid-cols-2">
                    <FormField label="Display name"><input v-model="level.name" type="text" class="input"></FormField>
                    <FormField label="Description"><input v-model="level.description" type="text" class="input"></FormField>
                    <template v-if="level.has_exam">
                        <FormField label="Exam questions"><input v-model.number="level.exam_question_count" type="number" min="5" max="100" class="input"></FormField>
                        <FormField label="Time limit (minutes)"><input v-model.number="level.exam_time_limit_minutes" type="number" min="5" max="180" class="input"></FormField>
                        <FormField label="Pass score (%)"><input v-model.number="level.exam_pass_score" type="number" min="50" max="100" class="input"></FormField>
                    </template>
                    <div class="flex items-end"><button class="btn-primary" :disabled="saving" @click="save(level)">{{ saving ? 'Saving…' : 'Save level' }}</button></div>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../api/client'
import { useUiStore } from '../../stores/ui'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import FormField from '../../components/FormField.vue'

const ui = useUiStore()
const levels = ref([])
const editing = ref(null)
const saving = ref(false)
const loading = ref(true)
const error = ref(null)

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get('/api/admin/levels')
        levels.value = data.levels
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function save(level) {
    saving.value = true
    try {
        await api.patch(`/api/admin/levels/${level.id}`, {
            name: level.name,
            description: level.description,
            ...(level.has_exam ? {
                exam_question_count: level.exam_question_count,
                exam_time_limit_minutes: level.exam_time_limit_minutes,
                exam_pass_score: level.exam_pass_score,
            } : {}),
        })
        editing.value = null
        ui.toast('Level saved.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>
