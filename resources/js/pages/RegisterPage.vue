<template>
    <div class="flex min-h-screen items-center justify-center px-4 py-8">
        <div class="w-full max-w-sm">
            <RouterLink to="/" class="mb-8 flex items-center justify-center gap-2 text-xl font-semibold text-ink-100">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-accent-600 font-bold text-white">L</span>
                Lingua
            </RouterLink>

            <!-- Step 2: show recovery code once -->
            <div v-if="recoveryCode" class="card p-6">
                <h1 class="mb-2 text-xl font-semibold text-ink-100">Save your recovery code</h1>
                <p class="mb-4 text-sm text-ink-400">
                    Lingua accounts have no email. This code is the <strong class="text-ink-200">only way</strong> to reset your password if you forget it. Save it somewhere safe — it will not be shown again.
                </p>
                <div class="mb-4 select-all rounded-md border border-accent-600/50 bg-ink-850 p-4 text-center font-mono text-lg tracking-wider text-accent-400">
                    {{ recoveryCode }}
                </div>
                <button class="btn-secondary mb-3 w-full" @click="copyCode">{{ copied ? 'Copied!' : 'Copy code' }}</button>
                <label class="mb-4 flex items-start gap-2 text-sm text-ink-300">
                    <input v-model="confirmed" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">
                    I saved my recovery code somewhere safe.
                </label>
                <button class="btn-primary w-full" :disabled="!confirmed" @click="router.push('/app/onboarding')">Continue</button>
            </div>

            <!-- Step 1: registration form -->
            <div v-else class="card p-6">
                <h1 class="mb-6 text-xl font-semibold text-ink-100">Create your account</h1>
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField label="Username" :error="errors.username" hint="3–30 characters. Letters, numbers, underscores." id="username">
                        <input id="username" v-model="form.username" type="text" class="input" autocomplete="username" required autofocus>
                    </FormField>
                    <FormField label="Display name (optional)" :error="errors.display_name" id="display_name">
                        <input id="display_name" v-model="form.display_name" type="text" class="input" autocomplete="name">
                    </FormField>
                    <FormField label="Password" :error="errors.password" hint="At least 8 characters." id="password">
                        <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" required>
                    </FormField>
                    <FormField label="Confirm password" id="password_confirmation">
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required>
                    </FormField>
                    <button type="submit" class="btn-primary w-full" :disabled="loading">
                        {{ loading ? 'Creating account…' : 'Create account' }}
                    </button>
                </form>
                <p class="mt-5 text-center text-sm text-ink-400">
                    Already have an account? <RouterLink to="/login" class="text-accent-400 hover:underline">Log in</RouterLink>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import FormField from '../components/FormField.vue'

const auth = useAuthStore()
const router = useRouter()
const loading = ref(false)
const errors = reactive({})
const recoveryCode = ref(null)
const confirmed = ref(false)
const copied = ref(false)

const form = reactive({
    username: '',
    display_name: '',
    password: '',
    password_confirmation: '',
    timezone: Intl.DateTimeFormat().resolvedOptions().timeZone || 'UTC',
})

async function submit() {
    loading.value = true
    Object.keys(errors).forEach((k) => delete errors[k])
    try {
        const data = await auth.register(form)
        recoveryCode.value = data.recovery_code
    } catch (e) {
        if (e.errors) Object.assign(errors, Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])))
        else errors.username = e.message
    } finally {
        loading.value = false
    }
}

async function copyCode() {
    try {
        await navigator.clipboard.writeText(recoveryCode.value)
        copied.value = true
        setTimeout(() => (copied.value = false), 2000)
    } catch { /* clipboard unavailable */ }
}
</script>
