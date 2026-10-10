<template>
    <div class="flex min-h-screen">
        <aside class="fixed inset-y-0 left-0 z-40 hidden w-60 flex-col border-r border-ink-800 bg-ink-900 lg:flex">
            <div class="flex items-center gap-2 px-5 py-5">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-ink-700 font-bold text-ink-100">⚙</span>
                <div>
                    <p class="text-sm font-semibold text-ink-100">Lingua Admin</p>
                    <p class="text-xs text-ink-400">Content management</p>
                </div>
            </div>
            <nav class="flex-1 space-y-1 overflow-y-auto px-3 pb-4">
                <RouterLink v-for="item in nav" :key="item.to" :to="item.to" class="nav-link" :class="{ active: route.path.startsWith(item.to) }">
                    {{ item.label }}
                </RouterLink>
            </nav>
            <div class="border-t border-ink-800 p-3">
                <RouterLink to="/app/dashboard" class="nav-link">← Back to app</RouterLink>
            </div>
        </aside>

        <div class="flex min-h-screen flex-1 flex-col lg:pl-60">
            <header class="sticky top-0 z-30 flex h-14 items-center justify-between border-b border-ink-800 bg-ink-950/90 px-4 backdrop-blur lg:px-8">
                <div class="flex items-center gap-2 lg:hidden">
                    <span class="font-semibold text-ink-100">Lingua Admin</span>
                </div>
                <select class="input w-auto py-1 text-xs lg:hidden" :value="route.path" @change="$router.push($event.target.value)">
                    <option v-for="item in nav" :key="item.to" :value="item.to">{{ item.label }}</option>
                </select>
                <span class="text-sm text-ink-400">{{ auth.user?.display_name }}</span>
            </header>
            <main class="flex-1 px-4 py-6 lg:px-8">
                <router-view />
            </main>
        </div>
    </div>
</template>

<script setup>
import { useRoute } from 'vue-router'
import { useAuthStore } from '../stores/auth'

const route = useRoute()
const auth = useAuthStore()

const nav = [
    { to: '/admin/overview', label: 'Overview' },
    { to: '/admin/users', label: 'Users' },
    { to: '/admin/levels', label: 'CEFR Levels' },
    { to: '/admin/courses', label: 'Courses & Modules' },
    { to: '/admin/lessons', label: 'Lessons' },
    { to: '/admin/exercises', label: 'Exercises' },
    { to: '/admin/vocabulary', label: 'Vocabulary' },
    { to: '/admin/achievements', label: 'Achievements' },
    { to: '/admin/settings', label: 'Settings' },
]
</script>

<style scoped>
.nav-link {
    display: block;
    border-radius: 0.375rem;
    padding: 0.5rem 0.75rem;
    font-size: 0.875rem;
    color: var(--color-ink-300);
}
.nav-link:hover { background: var(--color-ink-800); color: var(--color-ink-100); }
.nav-link.active { background: var(--color-ink-800); color: var(--color-accent-400); font-weight: 500; }
</style>
