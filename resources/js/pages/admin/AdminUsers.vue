<template>
    <div>
        <h1 class="text-2xl font-bold text-ink-100">Users</h1>
        <div class="mt-4 flex flex-col gap-3 sm:flex-row">
            <input v-model="search" type="search" class="input sm:max-w-xs" placeholder="Search username or name…" @input="debouncedLoad">
            <select v-model="roleFilter" class="input sm:w-36" @change="load(1)">
                <option value="">All roles</option>
                <option value="user">Users</option>
                <option value="admin">Admins</option>
            </select>
            <label class="flex items-center gap-2 text-sm text-ink-300">
                <input v-model="suspendedOnly" type="checkbox" class="h-4 w-4 rounded border-ink-600 bg-ink-900 accent-emerald-600" @change="load(1)">
                Suspended only
            </label>
        </div>

        <LoadingState v-if="loading" message="Loading users…" />
        <ErrorState v-else-if="error" :message="error" :retry="() => load(page)" />
        <EmptyState v-else-if="!users.length" title="No users found" message="Try different filters." />
        <template v-else>
            <div class="card mt-4 overflow-x-auto">
                <table class="w-full text-sm">
                    <thead>
                        <tr class="border-b border-ink-800 text-left text-xs uppercase text-ink-400">
                            <th class="px-4 py-3">User</th><th class="px-4 py-3">Role</th><th class="px-4 py-3">Level</th>
                            <th class="px-4 py-3">XP</th><th class="px-4 py-3">Lessons</th><th class="px-4 py-3">Status</th><th class="px-4 py-3" />
                        </tr>
                    </thead>
                    <tbody>
                        <tr v-for="u in users" :key="u.id" class="border-b border-ink-800/50 last:border-0 hover:bg-ink-850/40">
                            <td class="px-4 py-2.5">
                                <p class="font-medium text-ink-100">{{ u.username }}</p>
                                <p class="text-xs text-ink-400">{{ u.display_name }}</p>
                            </td>
                            <td class="px-4 py-2.5"><span class="rounded-full px-2 py-0.5 text-xs" :class="u.role === 'admin' ? 'bg-accent-600/15 text-accent-400' : 'bg-ink-800 text-ink-300'">{{ u.role }}</span></td>
                            <td class="px-4 py-2.5 uppercase text-ink-300">{{ u.current_level?.code || '—' }}</td>
                            <td class="px-4 py-2.5 tabular-nums text-ink-300">{{ u.xp_total }}</td>
                            <td class="px-4 py-2.5 tabular-nums text-ink-300">{{ u.lesson_completions_count }}</td>
                            <td class="px-4 py-2.5">
                                <span v-if="u.suspended_at" class="rounded-full bg-red-900/40 px-2 py-0.5 text-xs text-red-300">Suspended</span>
                                <span v-else class="text-xs text-ink-400">Active</span>
                            </td>
                            <td class="px-4 py-2.5 text-right"><button class="btn-ghost text-xs" @click="openUser(u)">Manage</button></td>
                        </tr>
                    </tbody>
                </table>
            </div>
            <div v-if="lastPage > 1" class="mt-4 flex items-center justify-center gap-3">
                <button class="btn-secondary" :disabled="page <= 1" @click="load(page - 1)">← Prev</button>
                <span class="text-sm text-ink-400">{{ page }} / {{ lastPage }}</span>
                <button class="btn-secondary" :disabled="page >= lastPage" @click="load(page + 1)">Next →</button>
            </div>
        </template>

        <BaseModal :open="!!selected" :title="selected ? '@' + selected.username : ''" @close="selected = null">
            <template v-if="detail">
                <div class="grid grid-cols-3 gap-3 text-center">
                    <div class="rounded-md bg-ink-850 p-3"><p class="text-lg font-bold text-ink-100">{{ detail.stats.lessons_completed }}</p><p class="text-xs text-ink-400">Lessons</p></div>
                    <div class="rounded-md bg-ink-850 p-3"><p class="text-lg font-bold text-ink-100">{{ detail.stats.xp_total }}</p><p class="text-xs text-ink-400">XP</p></div>
                    <div class="rounded-md bg-ink-850 p-3"><p class="text-lg font-bold text-ink-100">{{ detail.stats.avg_score }}%</p><p class="text-xs text-ink-400">Avg score</p></div>
                </div>
                <div v-if="detail.exam_history.length" class="mt-4">
                    <h3 class="text-xs font-semibold uppercase text-ink-400">Recent exams</h3>
                    <ul class="mt-1.5 space-y-1 text-sm text-ink-300">
                        <li v-for="e in detail.exam_history" :key="e.id">{{ e.kind }} {{ e.level?.code?.toUpperCase() || '' }} — {{ e.score }}% {{ e.kind === 'final' ? (e.passed ? '(passed)' : '(failed)') : '' }}</li>
                    </ul>
                </div>
                <div class="mt-5 space-y-2 border-t border-ink-800 pt-4">
                    <button v-if="detail.user.role !== 'admin'" class="btn-secondary w-full" @click="toggleSuspend">
                        {{ detail.user.suspended_at ? 'Unsuspend account' : 'Suspend account' }}
                    </button>
                    <button v-if="detail.user.id !== auth.user?.id" class="btn-secondary w-full" @click="toggleRole">
                        {{ detail.user.role === 'admin' ? 'Demote to user' : 'Promote to admin' }}
                    </button>
                    <button class="btn-secondary w-full" @click="regenerateRecovery">New recovery code</button>
                    <div v-if="newRecoveryCode" class="rounded-md border border-ink-700 bg-ink-850 p-3 text-center">
                        <p class="text-xs text-ink-400">Share this code securely — it will not be shown again.</p>
                        <p class="mt-1 font-mono text-lg font-bold tracking-wider text-ink-100">{{ newRecoveryCode }}</p>
                    </div>
                </div>
            </template>
            <LoadingState v-else message="Loading user…" />
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../../api/client'
import { useAuthStore } from '../../stores/auth'
import { useUiStore } from '../../stores/ui'
import LoadingState from '../../components/LoadingState.vue'
import ErrorState from '../../components/ErrorState.vue'
import EmptyState from '../../components/EmptyState.vue'
import BaseModal from '../../components/BaseModal.vue'

const auth = useAuthStore()
const ui = useUiStore()
const users = ref([])
const page = ref(1)
const lastPage = ref(1)
const search = ref('')
const roleFilter = ref('')
const suspendedOnly = ref(false)
const loading = ref(true)
const error = ref(null)
const selected = ref(null)
const detail = ref(null)
const newRecoveryCode = ref(null)
let timer = null

function debouncedLoad() {
    clearTimeout(timer)
    timer = setTimeout(() => load(1), 350)
}

async function load(p = 1) {
    loading.value = true
    error.value = null
    page.value = p
    try {
        const params = new URLSearchParams({ page: p })
        if (search.value) params.set('q', search.value)
        if (roleFilter.value) params.set('role', roleFilter.value)
        if (suspendedOnly.value) params.set('suspended', '1')
        const data = await api.get(`/api/admin/users?${params}`)
        users.value = data.users.data
        lastPage.value = data.users.last_page
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function openUser(u) {
    selected.value = u
    detail.value = null
    newRecoveryCode.value = null
    try {
        detail.value = await api.get(`/api/admin/users/${u.id}`)
    } catch (e) {
        ui.toast(e.message, 'error')
        selected.value = null
    }
}

async function toggleSuspend() {
    try {
        const data = await api.post(`/api/admin/users/${detail.value.user.id}/suspend`)
        detail.value.user.suspended_at = data.suspended ? new Date().toISOString() : null
        ui.toast(data.suspended ? 'Account suspended.' : 'Account restored.', 'success')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function regenerateRecovery() {
    if (!confirm('Issue a new recovery code? The old code stops working immediately.')) return
    try {
        const data = await api.post(`/api/admin/users/${detail.value.user.id}/recovery-code`)
        newRecoveryCode.value = data.recovery_code
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

async function toggleRole() {
    const newRole = detail.value.user.role === 'admin' ? 'user' : 'admin'
    if (!confirm(`Change role to ${newRole}?`)) return
    try {
        const data = await api.patch(`/api/admin/users/${detail.value.user.id}/role`, { role: newRole })
        detail.value.user.role = data.user.role
        ui.toast(`Role updated to ${data.user.role}.`, 'success')
        load(page.value)
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(() => load(1))
</script>
