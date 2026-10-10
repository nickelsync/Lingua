<template>
    <div class="mx-auto max-w-2xl">
        <h1 class="text-2xl font-bold text-ink-100">Settings</h1>

        <!-- Profile -->
        <section class="card mt-6 p-6">
            <h2 class="font-semibold text-ink-100">Profile</h2>
            <div class="mt-4 flex items-center gap-4">
                <img v-if="auth.user?.avatar_url" :src="auth.user.avatar_url" alt="" class="h-16 w-16 rounded-full border border-ink-700 object-cover">
                <div v-else class="flex h-16 w-16 items-center justify-center rounded-full border border-ink-700 bg-ink-800 text-xl font-bold text-ink-300">{{ (auth.user?.display_name || auth.user?.username || '?')[0].toUpperCase() }}</div>
                <div>
                    <input ref="avatarInput" type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="uploadAvatar">
                    <button class="btn-secondary text-sm" :disabled="avatarUploading" @click="$refs.avatarInput.click()">{{ avatarUploading ? 'Uploading…' : 'Change photo' }}</button>
                    <p class="mt-1 text-xs text-ink-400">JPEG, PNG, or WebP, max 2 MB.</p>
                </div>
            </div>
            <div class="mt-5 grid gap-4 sm:grid-cols-2">
                <FormField label="Username" :error="errors.username">
                    <input v-model="profile.username" type="text" class="input" autocapitalize="off">
                </FormField>
                <FormField label="Display name" :error="errors.display_name">
                    <input v-model="profile.display_name" type="text" class="input" placeholder="Shown on your profile">
                </FormField>
                <FormField label="Timezone" :error="errors.timezone" hint="Used for daily streaks.">
                    <select v-model="profile.timezone" class="input">
                        <option v-for="tz in timezones" :key="tz" :value="tz">{{ tz }}</option>
                    </select>
                </FormField>
                <FormField label="Daily goal (minutes)" :error="errors.daily_goal_minutes">
                    <select v-model.number="profile.daily_goal_minutes" class="input">
                        <option v-for="m in [5, 10, 15, 20, 30, 45, 60, 90, 120]" :key="m" :value="m">{{ m }} minutes</option>
                    </select>
                </FormField>
            </div>
            <label class="mt-4 flex items-start gap-2.5 text-sm text-ink-300">
                <input v-model="profile.is_public" type="checkbox" class="mt-0.5 h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600">
                <span>Public profile<span class="block text-xs text-ink-400">Anyone with your profile link can see your level, XP, and achievements.</span></span>
            </label>
            <div class="mt-5 flex items-center gap-3">
                <button class="btn-primary" :disabled="savingProfile" @click="saveProfile">{{ savingProfile ? 'Saving…' : 'Save profile' }}</button>
                <RouterLink v-if="auth.user?.is_public" :to="`/u/${auth.user.username}`" class="text-sm text-accent-400 hover:underline">View public profile →</RouterLink>
            </div>
        </section>

        <!-- Password -->
        <section class="card mt-6 p-6">
            <h2 class="font-semibold text-ink-100">Change password</h2>
            <div class="mt-4 space-y-4">
                <FormField label="Current password" :error="pwErrors.current_password">
                    <input v-model="pw.current_password" type="password" class="input" autocomplete="current-password">
                </FormField>
                <FormField label="New password" :error="pwErrors.password" hint="At least 8 characters.">
                    <input v-model="pw.password" type="password" class="input" autocomplete="new-password">
                </FormField>
                <FormField label="Confirm new password">
                    <input v-model="pw.password_confirmation" type="password" class="input" autocomplete="new-password">
                </FormField>
            </div>
            <button class="btn-primary mt-5" :disabled="savingPw || !pw.current_password || !pw.password" @click="changePassword">{{ savingPw ? 'Updating…' : 'Update password' }}</button>
        </section>

        <!-- App -->
        <section class="card mt-6 p-6">
            <h2 class="font-semibold text-ink-100">App & audio</h2>
            <FormField class="mt-4" label="Preferred voice" hint="Voices are provided by your device. American English voices are listed first.">
                <select v-model="voicePref" class="input" @change="saveVoice">
                    <option value="">Automatic (recommended)</option>
                    <option v-for="v in voices" :key="v.name" :value="v.name">{{ v.name }} ({{ v.lang }})</option>
                </select>
            </FormField>
            <button class="btn-secondary mt-4 text-sm" @click="testVoice">▶ Test voice</button>
        </section>

        <!-- Account -->
        <section class="card mt-6 border-red-900/50 p-6">
            <h2 class="font-semibold text-red-300">Account</h2>
            <p class="mt-2 text-sm text-ink-400">Lingua uses a recovery code instead of email. If you've lost your code, change your password while logged in, or contact an administrator.</p>
            <button class="btn-secondary mt-4" @click="logout">Sign out</button>
        </section>
    </div>
</template>

<script setup>
import { onMounted, reactive, ref } from 'vue'
import { useRouter } from 'vue-router'
import { api } from '../api/client'
import { useAuthStore } from '../stores/auth'
import { useUiStore } from '../stores/ui'
import { getVoices, speak } from '../composables/useSpeech'
import FormField from '../components/FormField.vue'

const auth = useAuthStore()
const ui = useUiStore()
const router = useRouter()

const profile = reactive({
    username: auth.user?.username || '',
    display_name: auth.user?.display_name || '',
    timezone: auth.user?.timezone || 'Asia/Jakarta',
    daily_goal_minutes: auth.user?.daily_goal_minutes || 15,
    is_public: auth.user?.is_public || false,
})
const errors = ref({})
const savingProfile = ref(false)
const avatarUploading = ref(false)

const pw = reactive({ current_password: '', password: '', password_confirmation: '' })
const pwErrors = ref({})
const savingPw = ref(false)

const timezones = [
    'Asia/Jakarta', 'Asia/Makassar', 'Asia/Jayapura', 'Asia/Singapore', 'Asia/Kuala_Lumpur',
    'Asia/Bangkok', 'Asia/Manila', 'Asia/Tokyo', 'Asia/Seoul', 'Asia/Shanghai', 'Asia/Kolkata',
    'Asia/Dubai', 'Europe/London', 'Europe/Paris', 'Europe/Berlin', 'America/New_York',
    'America/Chicago', 'America/Denver', 'America/Los_Angeles', 'Australia/Sydney', 'Pacific/Auckland', 'UTC',
]

const voices = ref([])
const voicePref = ref(localStorage.getItem('lingua:voice') || '')

async function saveProfile() {
    savingProfile.value = true
    errors.value = {}
    try {
        const data = await api.patch('/api/profile', profile)
        auth.cacheUser(data.user)
        ui.toast('Profile saved.', 'success')
    } catch (e) {
        errors.value = flatten(e.errors)
        if (!e.errors) ui.toast(e.message, 'error')
    } finally {
        savingProfile.value = false
    }
}

async function uploadAvatar(event) {
    const file = event.target.files?.[0]
    if (!file) return
    if (file.size > 2 * 1024 * 1024) { ui.toast('Image must be under 2 MB.', 'error'); return }
    avatarUploading.value = true
    try {
        const form = new FormData()
        form.append('avatar', file)
        const data = await api.upload('/api/profile/avatar', form)
        auth.user.avatar_url = data.avatar_url
        ui.toast('Photo updated.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        avatarUploading.value = false
        event.target.value = ''
    }
}

async function changePassword() {
    savingPw.value = true
    pwErrors.value = {}
    try {
        await api.post('/api/auth/change-password', pw)
        pw.current_password = pw.password = pw.password_confirmation = ''
        ui.toast('Password updated.', 'success')
    } catch (e) {
        pwErrors.value = flatten(e.errors)
        if (!e.errors) ui.toast(e.message, 'error')
    } finally {
        savingPw.value = false
    }
}

function flatten(errs) {
    if (!errs) return {}
    return Object.fromEntries(Object.entries(errs).map(([k, v]) => [k, Array.isArray(v) ? v[0] : v]))
}

function saveVoice() {
    if (voicePref.value) localStorage.setItem('lingua:voice', voicePref.value)
    else localStorage.removeItem('lingua:voice')
}

function testVoice() {
    speak('Hello! This is how I sound. Welcome to Lingua.')
}

async function logout() {
    await auth.logout()
    router.push('/login')
}

onMounted(async () => {
    voices.value = await getVoices()
})
</script>
