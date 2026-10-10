import { defineStore } from 'pinia'
import { api, ApiError } from '../api/client'

const USER_KEY = 'lingua.user'

function readCachedUser() {
    try { return JSON.parse(localStorage.getItem(USER_KEY) || 'null') } catch { return null }
}

export const useAuthStore = defineStore('auth', {
    state: () => ({
        user: null,
        loaded: false,
    }),
    getters: {
        isAuthenticated: (s) => !!s.user,
        isAdmin: (s) => s.user?.role === 'admin',
    },
    actions: {
        cacheUser(user) {
            this.user = user
            try {
                if (user) localStorage.setItem(USER_KEY, JSON.stringify({ id: user.id, username: user.username, display_name: user.display_name, role: user.role, onboarded: user.onboarded, placement_done: user.placement_done }))
                else localStorage.removeItem(USER_KEY)
            } catch { /* storage unavailable */ }
        },
        async fetchUser() {
            try {
                const data = await api.get('/api/auth/me')
                this.cacheUser(data.user)
            } catch (e) {
                // Offline/network failure: keep the cached session so the PWA
                // stays usable; a real 401/419 means the session is gone.
                if (e instanceof ApiError) this.cacheUser(null)
                else this.user = readCachedUser()
            } finally {
                this.loaded = true
            }
        },
        async login(credentials) {
            const data = await api.post('/api/auth/login', credentials)
            this.cacheUser(data.user)
            return data
        },
        async register(payload) {
            const data = await api.post('/api/auth/register', payload)
            this.cacheUser(data.user)
            return data
        },
        async logout() {
            try { await api.post('/api/auth/logout') } finally {
                this.cacheUser(null)
                // Drop cached per-user API data so the next user can't read it offline.
                if ('caches' in window) {
                    try {
                        const keys = await caches.keys()
                        await Promise.all(keys.filter((k) => k.endsWith('-data')).map((k) => caches.delete(k)))
                    } catch { /* cache cleanup is best-effort */ }
                }
                window.location.href = '/'
            }
        },
    },
})
