<template>
    <div class="mx-auto max-w-2xl px-4 py-10">
        <RouterLink to="/" class="mb-8 flex items-center gap-2 text-lg font-semibold text-ink-100">
            <span class="flex h-8 w-8 items-center justify-center rounded-md bg-accent-600 font-bold text-white">L</span>
            Lingua
        </RouterLink>

        <LoadingState v-if="loading" message="Loading profile…" />
        <ErrorState v-else-if="error" title="Profile unavailable" :message="error" />
        <template v-else-if="profile">
            <div class="card flex flex-col items-center gap-4 p-8 text-center sm:flex-row sm:text-left">
                <div class="flex h-20 w-20 shrink-0 items-center justify-center overflow-hidden rounded-full border border-ink-700 bg-ink-800 text-2xl font-bold text-ink-200">
                    <img v-if="profile.avatar_url" :src="profile.avatar_url" alt="" class="h-full w-full object-cover">
                    <span v-else>{{ (profile.display_name || profile.username).slice(0, 2).toUpperCase() }}</span>
                </div>
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-ink-100">{{ profile.display_name }}</h1>
                    <p class="text-sm text-ink-400">@{{ profile.username }} · Member since {{ profile.member_since }}</p>
                    <p v-if="profile.level" class="mt-2 inline-block rounded-full border border-accent-600/50 bg-accent-600/10 px-3 py-1 text-xs font-semibold text-accent-400">
                        {{ profile.level.name }}
                    </p>
                </div>
            </div>

            <div class="mt-6 grid grid-cols-3 gap-4">
                <StatCard label="Total XP" :value="profile.xp_total" />
                <StatCard label="Account level" :value="profile.account_level" />
                <StatCard label="Lessons done" :value="profile.lessons_completed" />
            </div>

            <div v-if="profile.achievements?.length" class="card mt-6 p-6">
                <h2 class="mb-4 font-semibold text-ink-100">Achievements</h2>
                <div class="flex flex-wrap gap-3">
                    <span v-for="a in profile.achievements" :key="a.name" class="flex items-center gap-2 rounded-full border border-ink-700 bg-ink-850 px-3 py-1.5 text-sm text-ink-200">
                        <span aria-hidden="true">{{ a.icon }}</span> {{ a.name }}
                    </span>
                </div>
            </div>
        </template>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { useRoute } from 'vue-router'
import { api } from '../api/client'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import StatCard from '../components/StatCard.vue'

const route = useRoute()
const profile = ref(null)
const loading = ref(true)
const error = ref(null)

onMounted(async () => {
    try {
        profile.value = await api.get(`/api/profiles/${route.params.username}`)
    } catch (e) {
        error.value = e.status === 404 ? 'This profile is private or does not exist.' : e.message
    } finally {
        loading.value = false
    }
})
</script>
