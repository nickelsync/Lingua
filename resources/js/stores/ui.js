import { defineStore } from 'pinia'

let toastId = 0

export const useUiStore = defineStore('ui', {
    state: () => ({
        toasts: [],
        sidebarCollapsed: localStorage.getItem('lingua.sidebar') === '1',
        online: navigator.onLine,
    }),
    actions: {
        toast(message, type = 'info', timeout = 3500) {
            const id = ++toastId
            this.toasts.push({ id, message, type })
            setTimeout(() => this.dismiss(id), timeout)
        },
        dismiss(id) {
            this.toasts = this.toasts.filter((t) => t.id !== id)
        },
        toggleSidebar() {
            this.sidebarCollapsed = !this.sidebarCollapsed
            localStorage.setItem('lingua.sidebar', this.sidebarCollapsed ? '1' : '0')
        },
        setOnline(v) { this.online = v },
    },
})
