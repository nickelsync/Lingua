<template>
    <div class="mx-auto max-w-4xl">
        <LoadingState v-if="loading" message="Loading level…" />
        <ErrorState v-else-if="error" :title="errorTitle" :message="error" :retry="errorTitle === 'Something went wrong' ? load : null">
        </ErrorState>
        <template v-else-if="data">
            <RouterLink to="/app/learn" class="text-sm text-ink-400 hover:text-ink-200">← All levels</RouterLink>
            <div class="mt-2 flex flex-col justify-between gap-4 sm:flex-row sm:items-end">
                <div>
                    <h1 class="text-2xl font-bold text-ink-100">{{ data.level.name }}</h1>
                    <p class="mt-1 max-w-xl text-ink-400">{{ data.level.description }}</p>
                </div>
                <div class="w-full shrink-0 sm:w-56">
                    <div class="mb-1.5 flex justify-between text-xs text-ink-400">
                        <span>Progress</span><span>{{ data.progress.percent }}%</span>
                    </div>
                    <ProgressBar :value="data.progress.percent" />
                </div>
            </div>

            <!-- Final exam CTA -->
            <div v-if="data.level.has_exam" class="card mt-6 flex flex-col justify-between gap-4 p-5 sm:flex-row sm:items-center" :class="data.content_complete ? 'border-accent-600/50' : ''">
                <div>
                    <h2 class="font-semibold text-ink-100">Final exam</h2>
                    <p class="text-sm text-ink-400">
                        {{ data.content_complete
                            ? `You're ready! Score ${data.level.exam_pass_score}% or higher to unlock the next level.`
                            : 'Complete all lessons in this level to unlock the final exam.' }}
                    </p>
                </div>
                <RouterLink v-if="data.content_complete" :to="`/app/exam/${data.level.id}`" class="btn-primary shrink-0">Take final exam</RouterLink>
                <span v-else class="shrink-0 text-xs text-ink-400">🔒 {{ data.progress.lessons_completed }}/{{ data.progress.lessons_total }} lessons done</span>
            </div>

            <EmptyState v-if="!data.courses.length" title="No content yet" message="Lessons for this level are coming soon." class="mt-8" />

            <div v-for="course in data.courses" :key="course.id" class="mt-8">
                <div class="mb-3 flex items-center justify-between">
                    <div>
                        <h2 class="flex items-center gap-2 text-lg font-semibold text-ink-100">
                            {{ course.title }}
                            <span class="rounded-full border border-ink-700 bg-ink-850 px-2 py-0.5 text-[10px] font-medium uppercase tracking-wide text-ink-400">{{ course.skill }}</span>
                        </h2>
                        <p class="text-sm text-ink-400">{{ course.description }}</p>
                    </div>
                    <span class="shrink-0 text-xs text-ink-400">{{ course.lessons_completed }}/{{ course.lessons_total }}</span>
                </div>

                <div v-for="module in course.modules" :key="module.id" class="card mb-3 overflow-hidden">
                    <button class="flex w-full items-center justify-between px-5 py-3.5 text-left hover:bg-ink-850" @click="toggle(module.id)">
                        <div>
                            <h3 class="text-sm font-semibold text-ink-100">{{ module.title }}</h3>
                            <p class="text-xs text-ink-400">{{ module.lessons.length }} lessons</p>
                        </div>
                        <span class="text-ink-400" aria-hidden="true">{{ open.includes(module.id) ? '▾' : '▸' }}</span>
                    </button>
                    <div v-if="open.includes(module.id)" class="border-t border-ink-800">
                        <RouterLink
                            v-for="lesson in module.lessons"
                            :key="lesson.id"
                            :to="`/app/lesson/${lesson.slug}`"
                            class="flex items-center justify-between border-b border-ink-800/50 px-5 py-3 text-sm last:border-0 hover:bg-ink-850"
                        >
                            <span class="flex items-center gap-3">
                                <span
                                    class="flex h-5 w-5 items-center justify-center rounded-full border text-[10px]"
                                    :class="lesson.completed ? 'border-accent-500 bg-accent-600/20 text-accent-400' : 'border-ink-600 text-ink-400'"
                                >
                                    {{ lesson.completed ? '✓' : '' }}
                                </span>
                                <span :class="lesson.completed ? 'text-ink-400' : 'text-ink-200'">{{ lesson.title }}</span>
                            </span>
                            <span class="shrink-0 text-xs text-ink-400">~{{ lesson.estimated_minutes }} min · +{{ lesson.xp_reward }} XP</span>
                        </RouterLink>
                    </div>
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
import EmptyState from '../components/EmptyState.vue'
import ProgressBar from '../components/ProgressBar.vue'

const route = useRoute()
const data = ref(null)
const loading = ref(true)
const error = ref(null)
const errorTitle = ref('Something went wrong')
const open = ref([])

function toggle(id) {
    open.value = open.value.includes(id) ? open.value.filter((i) => i !== id) : [...open.value, id]
}

async function load() {
    loading.value = true
    error.value = null
    try {
        data.value = await api.get(`/api/levels/${route.params.id}`)
        // Open the first module that has an incomplete lesson.
        for (const course of data.value.courses) {
            for (const module of course.modules) {
                if (module.lessons.some((l) => !l.completed)) {
                    open.value = [module.id]
                    return
                }
            }
        }
        if (data.value.courses[0]?.modules[0]) open.value = [data.value.courses[0].modules[0].id]
    } catch (e) {
        errorTitle.value = e.status === 403 ? 'Level locked' : 'Something went wrong'
        error.value = e.message
    } finally {
        loading.value = false
    }
}

onMounted(load)
</script>
