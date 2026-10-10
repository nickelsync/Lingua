<template>
    <div class="mx-auto max-w-4xl">
        <div class="flex flex-col justify-between gap-4 sm:flex-row sm:items-center">
            <div>
                <h1 class="text-2xl font-bold text-ink-100">Vocabulary</h1>
                <p class="mt-1 text-ink-400">Browse the word bank or manage your saved words.</p>
            </div>
            <RouterLink to="/app/flashcards" class="btn-primary self-start">▧ Review flashcards</RouterLink>
        </div>

        <!-- Tabs -->
        <div class="mt-6 flex gap-1 rounded-lg border border-ink-700 bg-ink-900 p-1">
            <button class="tab" :class="{ active: tab === 'all' }" @click="switchTab('all')">Word bank</button>
            <button class="tab" :class="{ active: tab === 'mine' }" @click="switchTab('mine')">My words</button>
        </div>

        <!-- Filters -->
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" class="input sm:flex-1" placeholder="Search words or Indonesian meanings…" @input="debouncedLoad">
            <select v-if="tab === 'all'" v-model="levelFilter" class="input sm:w-40" @change="load(1)">
                <option value="">All levels</option>
                <option v-for="l in ['beginner', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2']" :key="l" :value="l">{{ l === 'beginner' ? 'Beginner' : l.toUpperCase() }}</option>
            </select>
            <select v-model="categoryFilter" class="input sm:w-44" @change="load(1)">
                <option value="">All categories</option>
                <option v-for="c in categories" :key="c" :value="c">{{ c }}</option>
            </select>
        </div>

        <LoadingState v-if="loading" message="Loading words…" />
        <ErrorState v-else-if="error" :message="error" :retry="() => load(page)" />
        <EmptyState v-else-if="!words.length" :title="tab === 'mine' ? 'No saved words yet' : 'No words found'" :message="tab === 'mine' ? 'Save words from lessons or the word bank to build your personal deck.' : 'Try a different search or filter.'">
            <button v-if="tab === 'mine'" class="btn-primary" @click="switchTab('all')">Browse word bank</button>
        </EmptyState>

        <template v-else>
            <p class="mt-4 text-xs text-ink-400">{{ total }} words<span v-if="tab === 'mine' && dueCount > 0"> · {{ dueCount }} due for review</span></p>
            <div class="mt-2 grid gap-3 sm:grid-cols-2">
                <WordCard v-for="word in words" :key="word.id" :word="word" @toggle-save="toggleSave" />
            </div>

            <div v-if="lastPage > 1" class="mt-6 flex items-center justify-center gap-3">
                <button class="btn-secondary" :disabled="page <= 1" @click="load(page - 1)">← Prev</button>
                <span class="text-sm text-ink-400">{{ page }} / {{ lastPage }}</span>
                <button class="btn-secondary" :disabled="page >= lastPage" @click="load(page + 1)">Next →</button>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import WordCard from '../components/WordCard.vue'

const ui = useUiStore()
const tab = ref('all')
const words = ref([])
const categories = ref([])
const total = ref(0)
const page = ref(1)
const lastPage = ref(1)
const dueCount = ref(0)
const search = ref('')
const levelFilter = ref('')
const categoryFilter = ref('')
const loading = ref(true)
const error = ref(null)
let debounceTimer = null

function debouncedLoad() {
    clearTimeout(debounceTimer)
    debounceTimer = setTimeout(() => load(1), 350)
}

function switchTab(t) {
    tab.value = t
    search.value = ''
    categoryFilter.value = ''
    load(1)
}

async function load(p = 1) {
    loading.value = true
    error.value = null
    page.value = p
    try {
        const params = new URLSearchParams({ page: p })
        if (search.value) params.set('q', search.value)
        if (levelFilter.value && tab.value === 'all') params.set('level', levelFilter.value)
        if (categoryFilter.value) params.set('category', categoryFilter.value)
        const url = tab.value === 'all' ? `/api/vocabulary?${params}` : `/api/vocabulary/mine?${params}`
        const data = await api.get(url)
        words.value = data.data
        total.value = data.total
        lastPage.value = data.last_page
        if (data.categories) categories.value = data.categories
        if (data.due_count !== undefined) dueCount.value = data.due_count
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function toggleSave(word) {
    try {
        if (word.saved) {
            await api.delete(`/api/vocabulary/${word.id}/save`)
            word.saved = false
            if (tab.value === 'mine') words.value = words.value.filter((w) => w.id !== word.id)
            ui.toast('Removed from your words.', 'info')
        } else {
            await api.post(`/api/vocabulary/${word.id}/save`)
            word.saved = true
            ui.toast(`"${word.word}" saved.`, 'success')
        }
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(() => load(1))
</script>

<style scoped>
.tab {
    flex: 1;
    border-radius: 0.375rem;
    padding: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-ink-300);
    transition: background 0.15s;
}
.tab:hover { color: var(--color-ink-100); }
.tab.active { background: var(--color-ink-700); color: var(--color-ink-100); font-weight: 500; }
</style>
