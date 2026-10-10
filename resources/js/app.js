import './bootstrap-pwa'
import { createApp } from 'vue'
import { createPinia } from 'pinia'
import App from './App.vue'
import { router } from './router'
import { useUiStore } from './stores/ui'
import { flushQueue, pendingActionCount } from './api/client'

const app = createApp(App)
app.use(createPinia())
app.use(router)

const ui = useUiStore()
window.addEventListener('online', () => {
    ui.setOnline(true)
    const pending = pendingActionCount()
    if (pending > 0) {
        flushQueue().then((n) => { if (n > 0) ui.toast(`Synced ${n} pending update${n > 1 ? 's' : ''}.`, 'success') })
    } else {
        ui.toast('You are back online.', 'success')
    }
})
window.addEventListener('offline', () => {
    ui.setOnline(false)
    ui.toast('You are offline. Progress will sync when you reconnect.', 'info', 5000)
})
window.addEventListener('auth:expired', () => {
    window.location.href = '/login'
})

app.mount('#app')
