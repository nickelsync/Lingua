<template>
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold text-ink-100">Platform settings</h1>
        <p class="mt-1 text-ink-400">XP values and exam defaults. Changes apply immediately.</p>

        <LoadingState v-if="loading" message="Loading settings…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <div v-else class="card mt-6 p-6">
            <div class="grid gap-4 sm:grid-cols-2">
                <FormField v-for="field in fields" :key="field.key" :label="field.label" :hint="field.hint">
                    <input v-model="settings[field.key]" type="number" min="0" class="input">
                </FormField>
            </div>
            <button class="btn-primary mt-6" :disabled="saving" @click="save">{{ saving ? 'Saving…' : 'Save settings' }}</button>
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
const fields = [
    { key: 'xp_lesson_default', label: 'Default lesson XP', hint: 'Used when a lesson has no explicit reward.' },
    { key: 'xp_exercise_default', label: 'Default exercise XP' },
    { key: 'xp_vocab_review', label: 'XP per flashcard review' },
    { key: 'xp_speaking', label: 'XP per speaking attempt' },
    { key: 'xp_writing', label: 'XP per writing submission' },
    { key: 'repeat_xp_percent', label: 'Repeat XP (%)', hint: 'Share of XP granted for repeat completions.' },
]
const settings = ref({})
const loading = ref(true)
const error = ref(null)
const saving = ref(false)

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get('/api/admin/settings')
        settings.value = { ...data.settings }
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    try {
        const payload = Object.fromEntries(Object.entries(settings.value).map(([k, v]) => [k, String(v)]))
        await api.patch('/api/admin/settings', { settings: payload })
        ui.toast('Settings saved.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

onMounted(load)
</script>
