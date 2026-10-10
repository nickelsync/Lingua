<template>
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <RouterLink to="/" class="mb-8 flex items-center justify-center gap-2 text-xl font-semibold text-ink-100">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-accent-600 font-bold text-white">L</span>
                Lingua
            </RouterLink>
            <div class="card p-6">
                <h1 class="mb-6 text-xl font-semibold text-ink-100">Welcome back</h1>
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField label="Username" :error="errors.username" id="username">
                        <input id="username" v-model="form.username" type="text" class="input" autocomplete="username" required autofocus>
                    </FormField>
                    <FormField label="Password" :error="errors.password" id="password">
                        <input id="password" v-model="form.password" type="password" class="input" autocomplete="current-password" required>
                    </FormField>
                    <label class="flex items-center gap-2 text-sm text-ink-300">
                        <input v-model="form.remember" type="checkbox" class="h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">
                        Remember me
                    </label>
                    <button type="submit" class="btn-primary w-full" :disabled="loading">
                        {{ loading ? 'Logging in…' : 'Log in' }}
                    </button>
                </form>
                <div class="mt-5 space-y-1.5 text-center text-sm text-ink-400">
                    <p>Forgot your password? <RouterLink to="/recover" class="text-accent-400 hover:underline">Use your recovery code</RouterLink></p>
                    <p>New to Lingua? <RouterLink to="/register" class="text-accent-400 hover:underline">Create an account</RouterLink></p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { useRoute, useRouter } from 'vue-router'
import { useAuthStore } from '../stores/auth'
import FormField from '../components/FormField.vue'

const auth = useAuthStore()
const router = useRouter()
const route = useRoute()
const loading = ref(false)
const errors = reactive({})
const form = reactive({ username: '', password: '', remember: true })

async function submit() {
    loading.value = true
    Object.keys(errors).forEach((k) => delete errors[k])
    try {
        await auth.login(form)
        router.push(route.query.redirect || '/app/dashboard')
    } catch (e) {
        if (e.errors) Object.assign(errors, Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])))
        else errors.password = e.message
    } finally {
        loading.value = false
    }
}
</script>
