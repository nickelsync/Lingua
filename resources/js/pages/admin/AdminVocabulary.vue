<template>
    <div>
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h1 class="text-2xl font-bold text-ink-100">Vocabulary bank</h1>
            <button class="btn-primary text-sm" @click="openForm()">+ New word</button>
        </div>

        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" class="input sm:max-w-xs" placeholder="Search words…" @input="debouncedLoad">
            <select v-model="levelFilter" class="input sm:w-44" @change="load(1)">
                <option value="">All levels</option>
                <option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option>
            </select>
        </div>

        <LoadingState v-if="loading" message="Loading words…" />
        <ErrorState v-else-if="error" :message="error" :retry="() => load(page)" />
        <EmptyState v-else-if="!words.length" title="No words found" message="Add words or adjust the filters." />
        <template v-else>
            <div class="card mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink-800 text-left text-xs uppercase text-ink-400">
                            <th class="px-4 py-3">Word</th><th class="px-4 py-3">Meaning (ID)</th><th class="px-4 py-3">Level</th><th class="px-4 py-3">Category</th><th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="w in words" :key="w.id" class="border-b border-ink-800/50 last:border-0 hover:bg-ink-850/40">
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-ink-100">{{ w.word }} <span class="text-xs text-ink-400">{{ w.ipa }}</span></p>
                                <p class="text-xs text-ink-400">{{ w.definition }}</p>
                            </td>
                            <td class="px-4 py-2.5 italic text-ink-300">{{ w.meaning_id }}</td>
                            <td class="px-4 py-2.5 uppercase text-ink-400">{{ w.level?.code || '—' }}</td>
                            <td class="px-4 py-2.5 text-ink-400">{{ w.category }}</td>
                            <td class="px-4 py-2.5 text-right">
                                <div class="flex justify-end gap-1.5">
                                    <button class="btn-secondary text-xs" @click="openForm(w)">Edit</button>
                                    <button class="btn-ghost text-xs text-red-400" @click="remove(w)">Delete</button>
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

        <BaseModal :open="formOpen" :title="form.id ? 'Edit word' : 'New word'" wide @close="formOpen = false">
            <div class="grid gap-3 sm:grid-cols-2">
                <FormField label="Word"><input v-model="form.word" type="text" class="input"></FormField>
                <FormField label="Part of speech">
                    <select v-model="form.part_of_speech" class="input">
                        <option v-for="p in ['noun', 'verb', 'adjective', 'adverb', 'preposition', 'pronoun', 'conjunction', 'interjection', 'phrase']" :key="p" :value="p">{{ p }}</option>
                    </select>
                </FormField>
                <FormField label="IPA"><input v-model="form.ipa" type="text" class="input" placeholder="/həˈloʊ/"></FormField>
                <FormField label="Category"><input v-model="form.category" type="text" class="input" placeholder="greetings"></FormField>
                <FormField label="Level">
                    <select v-model="form.cefr_level_id" class="input">
                        <option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option>
                    </select>
                </FormField>
                <FormField label="Difficulty">
                    <select v-model="form.difficulty" class="input"><option value="easy">easy</option><option value="medium">medium</option><option value="hard">hard</option></select>
                </FormField>
            </div>
            <FormField class="mt-3" label="Definition (English)"><textarea v-model="form.definition" rows="2" class="input" /></FormField>
            <FormField class="mt-3" label="Meaning (Indonesian)"><input v-model="form.meaning_id" type="text" class="input"></FormField>
            <FormField class="mt-3" label="Example sentence"><input v-model="form.example" type="text" class="input"></FormField>
            <button class="btn-primary mt-4 w-full" :disabled="!form.word || !form.definition || saving" @click="save">{{ saving ? 'Saving…' : 'Save word' }}</button>
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
const words = ref([])
const levels = ref([])
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
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
        if (levelFilter.value) params.set('level_id', levelFilter.value)
        const [wRes, lRes] = await Promise.all([
            api.get(`/api/admin/vocabulary?${params}`),
            levels.value.length ? Promise.resolve(null) : api.get('/api/admin/levels'),
        ])
        words.value = wRes.vocabulary.data
        lastPage.value = wRes.vocabulary.last_page
        if (lRes) levels.value = lRes.levels
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

function openForm(w = null) {
    form.value = w
        ? { ...w }
        : { word: '', part_of_speech: 'noun', ipa: '', definition: '', meaning_id: '', example: '', category: '', cefr_level_id: levels.value[0]?.id, difficulty: 'easy' }
    formOpen.value = true
}

async function save() {
    saving.value = true
    try {
        if (form.value.id) {
            await api.patch(`/api/admin/vocabulary/${form.value.id}`, form.value)
        } else {
            await api.post('/api/admin/vocabulary', form.value)
        }
        formOpen.value = false
        ui.toast('Word saved.', 'success')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function remove(w) {
    if (!confirm(`Delete "${w.word}"?`)) return
    try {
        await api.delete(`/api/admin/vocabulary/${w.id}`)
        ui.toast('Word deleted.', 'info')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(() => load(1))
</script>
