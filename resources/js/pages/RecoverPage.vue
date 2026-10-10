<template>
    <div class="flex min-h-screen items-center justify-center px-4">
        <div class="w-full max-w-sm">
            <RouterLink to="/" class="mb-8 flex items-center justify-center gap-2 text-xl font-semibold text-ink-100">
                <span class="flex h-9 w-9 items-center justify-center rounded-md bg-accent-600 font-bold text-white">L</span>
                Lingua
            </RouterLink>

            <div v-if="newCode" class="card p-6">
                <h1 class="mb-2 text-xl font-semibold text-ink-100">Password reset</h1>
                <p class="mb-4 text-sm text-ink-400">Your password was changed and a <strong class="text-ink-200">new recovery code</strong> was issued. Your old code no longer works. Save this one:</p>
                <div class="mb-4 select-all rounded-md border border-accent-600/50 bg-ink-850 p-4 text-center font-mono text-lg tracking-wider text-accent-400">{{ newCode }}</div>
                <RouterLink to="/login" class="btn-primary w-full">Log in with new password</RouterLink>
            </div>

            <div v-else class="card p-6">
                <h1 class="mb-2 text-xl font-semibold text-ink-100">Reset your password</h1>
                <p class="mb-5 text-sm text-ink-400">Enter your username and the recovery code you received when you registered.</p>
                <form class="space-y-4" @submit.prevent="submit">
                    <FormField label="Username" :error="errors.username" id="username">
                        <input id="username" v-model="form.username" type="text" class="input" autocomplete="username" required autofocus>
                    </FormField>
                    <FormField label="Recovery code" :error="errors.recovery_code" hint="Format: XXXX-XXXX-XXXX" id="recovery_code">
                        <input id="recovery_code" v-model="form.recovery_code" type="text" class="input font-mono uppercase" placeholder="XXXX-XXXX-XXXX" required>
                    </FormField>
                    <FormField label="New password" :error="errors.password" id="password">
                        <input id="password" v-model="form.password" type="password" class="input" autocomplete="new-password" required>
                    </FormField>
                    <FormField label="Confirm new password" id="password_confirmation">
                        <input id="password_confirmation" v-model="form.password_confirmation" type="password" class="input" autocomplete="new-password" required>
                    </FormField>
                    <button type="submit" class="btn-primary w-full" :disabled="loading">{{ loading ? 'Resetting…' : 'Reset password' }}</button>
                </form>
                <p class="mt-5 text-center text-sm text-ink-400">
                    Remembered it? <RouterLink to="/login" class="text-accent-400 hover:underline">Log in</RouterLink>
                </p>
            </div>
        </div>
    </div>
</template>

<script setup>
import { reactive, ref } from 'vue'
import { api } from '../api/client'
import FormField from '../components/FormField.vue'

const loading = ref(false)
const errors = reactive({})
const newCode = ref(null)
const form = reactive({ username: '', recovery_code: '', password: '', password_confirmation: '' })

async function submit() {
    loading.value = true
    Object.keys(errors).forEach((k) => delete errors[k])
    try {
        const data = await api.post('/api/auth/recover', form)
        newCode.value = data.recovery_code
    } catch (e) {
        if (e.errors) Object.assign(errors, Object.fromEntries(Object.entries(e.errors).map(([k, v]) => [k, v[0]])))
        else errors.recovery_code = e.message
    } finally {
        loading.value = false
    }
}
</script>
