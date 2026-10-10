<template>
    <div class="flex min-h-screen">
        <!-- Sidebar (desktop) -->
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r border-ink-800 bg-ink-900 lg:flex">
            <div class="flex items-center gap-2 px-5 py-5">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-accent-600 font-bold text-white">L</span>
                <span class="text-lg font-semibold text-ink-100">Lingua</span>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                <RouterLink v-for="item in nav" :key="item.to" :to="item.to" class="nav-link" :class="{ active: isActive(item.to) }">
                    <span class="w-5 text-center" aria-hidden="true">{{ item.icon }}</span>
                    {{ item.label }}
                    <span v-if="item.to === '/app/flashcards' && dueCount > 0" class="ml-auto rounded-full bg-accent-600 px-2 py-0.5 text-xs font-semibold text-white">{{ dueCount }}</span>
                </RouterLink>
            </nav>
            <div class="border-t border-ink-800 p-3">
                <RouterLink v-if="auth.isAdmin" to="/admin" class="nav-link">
                    <span class="w-5 text-center" aria-hidden="true">⚙</span> Admin panel
                </RouterLink>
                <button class="nav-link w-full" @click="auth.logout()">
                    <span class="w-5 text-center" aria-hidden="true">↩</span> Log out
                </button>
            </div>
        </aside>

        <!-- Main -->
        <div class="flex min-h-screen flex-1 flex-col lg:pl-60">
            <!-- Top bar -->
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between gap-3 border-b border-ink-800 bg-ink-950/90 px-4 backdrop-blur lg:px-8">
                <div class="flex items-center gap-3 lg:hidden">
                    <span class="flex h-7 w-7 items-center justify-center rounded-md bg-accent-600 text-sm font-bold text-white">L</span>
                    <span class="font-semibold text-ink-100">Lingua</span>
                </div>
                <div class="hidden items-center gap-2 text-sm text-ink-400 lg:flex">
                    <RouterLink to="/app/search" class="btn-ghost text-sm">🔍 Search</RouterLink>
                </div>
                <div class="flex items-center gap-2">
                    <span v-if="auth.user" class="hidden items-center gap-1.5 rounded-full border border-ink-700 bg-ink-900 px-3 py-1 text-xs font-medium text-ink-300 sm:flex" title="Daily streak">
                        🔥 {{ auth.user.streak }}
                    </span>
                    <span v-if="auth.user" class="hidden items-center gap-1.5 rounded-full border border-ink-700 bg-ink-900 px-3 py-1 text-xs font-medium text-ink-300 sm:flex" title="Total XP">
                        ⚡ {{ auth.user.xp_total }} XP
                    </span>
                    <NotificationBell />
                    <RouterLink to="/app/settings" class="flex h-8 w-8 items-center justify-center overflow-hidden rounded-full border border-ink-700 bg-ink-800 text-xs font-bold text-ink-200" :title="auth.user?.display_name">
                        <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="" class="h-full w-full object-cover">
                        <span v-else>{{ initials }}</span>
                    </RouterLink>
                </div>
            </header>

            <main class="flex-1 px-4 py-6 pb-24 lg:px-8 lg:pb-8">
                <router-view />
            </main>

            <!-- Bottom nav (mobile) -->
            <nav class="fixed inset-x-0 bottom-0 z-40 flex border-t border-ink-800 bg-ink-900/95 backdrop-blur lg:hidden">
                <RouterLink v-for="item in mobileNav" :key="item.to" :to="item.to" class="flex flex-1 flex-col items-center gap-0.5 py-2.5 text-[11px]" :class="isActive(item.to) ? 'text-accent-400' : 'text-ink-400'">
                    <span class="text-base" aria-hidden="true">{{ item.icon }}</span>
                    {{ item.label }}
                </RouterLink>
            </nav>
        </div>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import { api } from '../api/client'
import NotificationBell from '../components/NotificationBell.vue'

const auth = useAuthStore()
const route = useRoute()
const dueCount = ref(0)

const nav = [
    { to: '/app/dashboard', icon: '⌂', label: 'Dashboard' },
    { to: '/app/learn', icon: '▤', label: 'Learn' },
    { to: '/app/practice', icon: '◎', label: 'Practice' },
    { to: '/app/vocabulary', icon: 'A', label: 'Vocabulary' },
    { to: '/app/flashcards', icon: '▧', label: 'Flashcards' },
    { to: '/app/progress', icon: '↗', label: 'Progress' },
    { to: '/app/bookmarks', icon: '☆', label: 'Bookmarks' },
    { to: '/app/notes', icon: '✎', label: 'Notes' },
]

const mobileNav = [
    { to: '/app/dashboard', icon: '⌂', label: 'Home' },
    { to: '/app/learn', icon: '▤', label: 'Learn' },
    { to: '/app/practice', icon: '◎', label: 'Practice' },
    { to: '/app/vocabulary', icon: 'A', label: 'Words' },
    { to: '/app/progress', icon: '↗', label: 'Progress' },
]

const initials = computed(() => (auth.user?.display_name || auth.user?.username || '?').slice(0, 2).toUpperCase())

function isActive(path) {
    return route.path === path || route.path.startsWith(path + '/')
}

onMounted(async () => {
    try {
        const data = await api.get('/api/review/queue?limit=1')
        dueCount.value = data.due_count
    } catch { /* non-critical */ }
})
</script>

<style scoped>
.nav-link {
    display: flex;
    align-items: center;
    gap: 0.625rem;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: var(--color-ink-300);
    transition: background 0.15s, color 0.15s;
}
.nav-link:hover { background: var(--color-ink-800); color: var(--color-ink-100); }
.nav-link.active { background: var(--color-ink-800); color: var(--color-accent-400); font-weight: 500; }
</style>
