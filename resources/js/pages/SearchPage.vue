<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-ink-100">Search</h1>
        <input
            ref="inputEl"
            v-model="query"
            type="search"
            class="input mt-4"
            placeholder="Search lessons, courses, and vocabulary…"
            @input="debouncedSearch"
        >

        <LoadingState v-if="loading" message="Searching…" />
        <template v-else-if="searched">
            <EmptyState v-if="!results.length" icon="🔍" :title="`No results for “${lastQuery}”`" message="Try different keywords, or browse the learning path.">
                <RouterLink to="/app/learn" class="btn-secondary">Browse lessons</RouterLink>
            </EmptyState>
            <div v-else class="mt-6 space-y-2">
                <component
                    :is="r.type === 'vocabulary' ? 'div' : 'RouterLink'"
                    v-for="r in results"
                    :key="`${r.type}-${r.id}`"
                    :to="linkFor(r)"
                    class="card block p-3.5"
                    :class="r.type !== 'vocabulary' && 'hover:border-ink-500'"
                >
                    <div class="flex items-center gap-3">
                        <span class="shrink-0 rounded border border-ink-700 px-1.5 py-0.5 text-[10px] uppercase text-ink-400">{{ r.type }}</span>
                        <div class="min-w-0">
                            <p class="truncate text-sm font-semibold text-ink-100">{{ r.title }}</p>
                            <p class="mt-0.5 truncate text-xs text-ink-400">{{ r.subtitle }}</p>
                        </div>
                    </div>
                </component>
            </div>
        </template>
        <p v-else class="mt-8 text-center text-sm text-ink-400">Type at least 2 characters to search.</p>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import LoadingState from '../components/LoadingState.vue'
import EmptyState from '../components/EmptyState.vue'

const query = ref('')
const lastQuery = ref('')
const results = ref([])
const loading = ref(false)
const searched = ref(false)
const inputEl = ref(null)
let timer = null

function linkFor(r) {
    if (r.type === 'lesson') return `/app/lesson/${r.slug}`
    if (r.type === 'course') return `/app/learn/level/${r.level_id}`
    return '/app/vocabulary'
}

function debouncedSearch() {
    clearTimeout(timer)
    if (query.value.trim().length < 2) { searched.value = false; return }
    timer = setTimeout(run, 350)
}

async function run() {
    loading.value = true
    lastQuery.value = query.value.trim()
    try {
        const data = await api.get(`/api/search?q=${encodeURIComponent(lastQuery.value)}`)
        results.value = data.results
    } catch {
        results.value = []
    } finally {
        loading.value = false
        searched.value = true
    }
}

onMounted(() => inputEl.value?.focus())
</script>
