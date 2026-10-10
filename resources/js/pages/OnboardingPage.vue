<template>
    <div class="mx-auto max-w-lg">
        <h1 class="text-2xl font-bold text-ink-100">Welcome to Lingua! 👋</h1>
        <p class="mt-2 text-ink-400">Tell us a little about your goals so we can personalize your experience.</p>

        <form class="card mt-6 space-y-6 p-6" @submit.prevent="submit">
            <FormField label="Why are you learning English?" :error="errors.learning_goal">
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="g in goals" :key="g" type="button" class="choice" :class="{ chosen: form.learning_goal === g }" @click="form.learning_goal = g">{{ g }}</button>
                </div>
            </FormField>

            <FormField label="How much English do you already know?" :error="errors.experience_level">
                <div class="grid grid-cols-1 gap-2">
                    <button v-for="e in experiences" :key="e.value" type="button" class="choice text-left" :class="{ chosen: form.experience_level === e.value }" @click="form.experience_level = e.value">
                        <span class="block font-medium">{{ e.label }}</span>
                        <span class="block text-xs opacity-70">{{ e.hint }}</span>
                    </button>
                </div>
            </FormField>

            <FormField label="Which skill do you want to focus on?" :error="errors.focus_skill">
                <div class="grid grid-cols-2 gap-2">
                    <button v-for="s in skills" :key="s" type="button" class="choice" :class="{ chosen: form.focus_skill === s }" @click="form.focus_skill = s">{{ s }}</button>
                </div>
            </FormField>

            <FormField label="Daily goal" :error="errors.daily_goal_minutes">
                <div class="grid grid-cols-4 gap-2">
                    <button v-for="m in [10, 15, 30, 60]" :key="m" type="button" class="choice" :class="{ chosen: form.daily_goal_minutes === m }" @click="form.daily_goal_minutes = m">{{ m }} min</button>
                </div>
            </FormField>

            <button type="submit" class="btn-primary w-full" :disabled="!complete || loading">{{ loading ? 'Saving…' : 'Continue' }}</button>
        </form>
    </div>
</template>

<script setup>
import { computed, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import FormField from '../components/FormField.vue'

const router = useRouter()
const auth = useAuthStore()
const loading = ref(false)
const errors = reactive({})

const goals = ['Work & career', 'Study abroad', 'Travel', 'Daily conversation', 'Exams & tests', 'Personal growth']
const skills = ['Grammar', 'Vocabulary', 'Speaking', 'Listening', 'Reading', 'Writing']
const experiences = [
    { value: 'none', label: 'Complete beginner', hint: "I'm starting from zero." },
    { value: 'some', label: 'Some basics', hint: 'I know common words and simple phrases.' },
    { value: 'intermediate', label: 'Conversational', hint: 'I can hold everyday conversations.' },
    { value: 'advanced', label: 'Advanced', hint: 'I want to polish and master the language.' },
]

const form = reactive({
    learning_goal: '',
    experience_level: '',
    focus_skill: '',
    daily_goal_minutes: 15,
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
})

const complete = computed(() => form.learning_goal && form.experience_level && form.focus_skill && form.daily_goal_minutes)

async function submit() {
    loading.value = true
    try {
        const data = await api.post('/api/onboarding', form)
        auth.cacheUser(data.user)
        // Complete beginners skip placement suggestion straight to learning.
        router.push(form.experience_level === 'none' ? '/app/dashboard' : '/app/placement')
    } catch (e) {
        if (e.errors) Object.assign(errors, Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])))
    } finally {
        loading.value = false
    }
}
</script>

<style scoped>
.choice {
    border: 1px solid var(--color-ink-600);
    background: var(--color-ink-900);
    border-radius: 0.375rem;
    padding: 0.625rem 0.75rem;
    font-size: 0.875rem;
    color: var(--color-ink-200);
    transition: border-color 0.15s, background 0.15s;
}
.choice:hover { border-color: var(--color-ink-400); }
.choice.chosen { border-color: var(--color-accent-500); background: color-mix(in srgb, var(--color-accent-600) 12%, transparent); color: var(--color-accent-400); }
</style>
