<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-ink-100">Bookmarks</h1>
        <p class="mt-1 text-ink-400">Lessons and words you've saved for quick access.</p>

        <LoadingState v-if="loading" message="Loading bookmarks…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <EmptyState v-else-if="!bookmarks.length" icon="☆" title="No bookmarks yet" message="Tap the star on any lesson to save it here.">
            <RouterLink to="/app/learn" class="btn-primary">Browse lessons</RouterLink>
        </EmptyState>
        <div v-else class="mt-6 space-y-3">
            <div v-for="b in bookmarks" :key="b.id" class="card flex items-center justify-between gap-4 p-4">
                <RouterLink :to="linkFor(b)" class="min-w-0 flex-1">
                    <p class="truncate text-sm font-semibold text-ink-100 hover:text-accent-400">{{ b.title }}</p>
                    <p class="mt-0.5 text-xs text-ink-400">{{ b.type === 'lesson' ? 'Lesson' : 'Word' }} · saved {{ new Date(b.created_at).toLocaleDateString() }}</p>
                </RouterLink>
                <button class="btn-ghost shrink-0 !px-2 text-sm" title="Remove bookmark" @click="remove(b)">✕</button>
            </div>
        </div>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'

const ui = useUiStore()
const bookmarks = ref([])
const loading = ref(true)
const error = ref(null)

function linkFor(b) {
    return b.type === 'lesson' ? `/app/lesson/${b.slug}` : '/app/vocabulary'
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get('/api/bookmarks')
        bookmarks.value = data.bookmarks
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function remove(b) {
    try {
        await api.post('/api/bookmarks/toggle', { type: b.type, id: b.target_id })
        bookmarks.value = bookmarks.value.filter((x) => x.id !== b.id)
        ui.toast('Bookmark removed.', 'info')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(load)
</script>
