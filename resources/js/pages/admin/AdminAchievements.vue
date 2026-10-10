<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-ink-100">Achievements</h1>
            <button class="btn-primary text-sm" @click="openForm()">+ New achievement</button>
        </div>

        <LoadingState v-if="loading" message="Loading achievements…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <EmptyState v-else-if="!achievements.length" title="No achievements" message="Create milestones to motivate learners." />
        <div v-else class="mt-6 grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
            <div v-for="a in achievements" :key="a.id" class="card p-4" :class="!a.is_active && 'opacity-60'">
                <div class="flex items-start justify-between gap-2">
                    <div class="flex items-center gap-3">
                        <span class="text-2xl" aria-hidden="true">{{ a.icon }}</span>
                        <div>
                            <p class="text-sm font-semibold text-ink-100">{{ a.name }}</p>
                            <p class="text-xs text-ink-400">{{ a.metric }} ≥ {{ a.threshold }}<span v-if="!a.is_active"> · inactive</span></p>
                        </div>
                    </div>
                    <div class="flex gap-1">
                        <button class="btn-ghost text-xs" @click="openForm(a)">Edit</button>
                        <button class="btn-ghost text-xs text-red-400" @click="remove(a)">✕</button>
                    </div>
                </div>
                <p class="mt-2 text-xs text-ink-400">{{ a.description }}</p>
            </div>
        </div>

        <BaseModal :open="formOpen" :title="form.id ? 'Edit achievement' : 'New achievement'" @close="formOpen = false">
            <div class="space-y-3">
                <FormField v-if="!form.id" label="Code" hint="Unique identifier, e.g. lessons_10"><input v-model="form.code" type="text" class="input"></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Name"><input v-model="form.name" type="text" class="input"></FormField>
                    <FormField label="Icon (emoji)"><input v-model="form.icon" type="text" class="input" placeholder="🏆"></FormField>
                </div>
                <FormField label="Description"><input v-model="form.description" type="text" class="input"></FormField>
                <div class="grid grid-cols-2 gap-3">
                    <FormField label="Metric">
                        <select v-model="form.metric" class="input">
                            <option v-for="m in metrics" :key="m" :value="m">{{ m }}</option>
                        </select>
                    </FormField>
                    <FormField label="Threshold"><input v-model.number="form.threshold" type="number" min="1" class="input"></FormField>
                </div>
                <label class="flex items-center gap-2 text-sm text-ink-300"><input v-model="form.is_active" type="checkbox" class="h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">Active</label>
                <button class="btn-primary w-full" :disabled="!form.name || saving" @click="save">{{ saving ? 'Saving…' : 'Save achievement' }}</button>
            </div>
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../api/client'
import { useUiStore } from '../../stores/ui'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import EmptyState from '../../components/EmptyState.vue'
import BaseModal from '../../components/BaseModal.vue'
import FormField from '../../components/FormField.vue'

const ui = useUiStore()
const metrics = ['lessons', 'exercises', 'words', 'reviews', 'streak', 'speaking', 'writing', 'xp', 'levels']
const achievements = ref([])
const loading = ref(true)
const error = ref(null)
const formOpen = ref(false)
const form = ref({})
const saving = ref(false)

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get('/api/admin/achievements')
        achievements.value = data.achievements
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

function openForm(a = null) {
    form.value = a
        ? { ...a }
        : { code: '', name: '', description: '', icon: '🏆', metric: 'lessons', threshold: 1, is_active: true }
    formOpen.value = true
}

async function save() {
    saving.value = true
    try {
        if (form.value.id) {
            await api.patch(`/api/admin/achievements/${form.value.id}`, form.value)
        } else {
            await api.post('/api/admin/achievements', form.value)
        }
        formOpen.value = false
        ui.toast('Achievement saved.', 'success')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function remove(a) {
    if (!confirm(`Delete "${a.name}"? Users who earned it will lose it.`)) return
    try {
        await api.delete(`/api/admin/achievements/${a.id}`)
        ui.toast('Achievement deleted.', 'info')
        load()
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(load)
</script>
