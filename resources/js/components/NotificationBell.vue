<template>
    <div class="relative">
        <button class="relative flex h-8 w-8 items-center justify-center rounded-full text-ink-300 hover:bg-ink-800" aria-label="Notifications" @click="toggle">
            <span aria-hidden="true">🔔</span>
            <span v-if="unread > 0" class="absolute -right-0.5 -top-0.5 flex h-4 min-w-4 items-center justify-center rounded-full bg-accent-600 px-1 text-[10px] font-bold text-white">{{ unread }}</span>
        </button>
        <Transition name="fade">
            <div v-if="open" class="absolute right-0 top-10 z-50 max-h-96 w-80 max-w-[calc(100vw-2rem)] overflow-y-auto rounded-lg border border-ink-700 bg-ink-900 shadow-2xl">
                <div class="flex items-center justify-between border-b border-ink-800 px-4 py-2.5">
                    <span class="text-sm font-semibold text-ink-100">Notifications</span>
                    <button v-if="unread > 0" class="text-xs text-accent-400 hover:underline" @click="markRead">Mark all read</button>
                </div>
                <div v-if="loading" class="p-4 text-center text-sm text-ink-400">Loading…</div>
                <div v-else-if="!items.length" class="p-6 text-center text-sm text-ink-400">No notifications yet.</div>
                <div v-else>
                    <div v-for="n in items" :key="n.id" class="border-b border-ink-800/50 px-4 py-3 last:border-0" :class="{ 'bg-ink-850': !n.read_at }">
                        <p class="text-sm font-medium text-ink-100">{{ n.title }}</p>
                        <p v-if="n.body" class="mt-0.5 text-xs text-ink-400">{{ n.body }}</p>
                        <p class="mt-1 text-[11px] text-ink-400">{{ new Date(n.created_at).toLocaleDateString() }}</p>
                    </div>
                </div>
            </div>
        </Transition>
    </div>
</template>

<script setup>
import { onBeforeUnmount, onMounted, ref } from 'vue'
import { api } from '../api/client'

const open = ref(false)
const items = ref([])
const unread = ref(0)
const loading = ref(false)

async function load() {
    loading.value = true
    try {
        const data = await api.get('/api/notifications')
        items.value = data.notifications
        unread.value = data.unread
    } catch { /* silent */ } finally { loading.value = false }
}

function toggle() {
    open.value = !open.value
    if (open.value) load()
}

async function markRead() {
    try {
        await api.post('/api/notifications/read')
        unread.value = 0
        items.value = items.value.map((n) => ({ ...n, read_at: n.read_at || new Date().toISOString() }))
    } catch { /* silent */ }
}

function onClickOutside(e) {
    if (open.value && !e.target.closest('.relative')) open.value = false
}

onMounted(() => {
    load()
    document.addEventListener('click', onClickOutside)
})
onBeforeUnmount(() => document.removeEventListener('click', onClickOutside))
</script>

<style scoped>
.fade-enter-active, .fade-leave-active { transition: opacity 0.15s ease; }
.fade-enter-from, .fade-leave-to { opacity: 0; }
</style>
