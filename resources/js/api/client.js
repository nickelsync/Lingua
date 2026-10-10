// Minimal fetch wrapper: session-cookie auth + CSRF + JSON + offline queueing.
let csrfToken = document.querySelector('meta[name="csrf-token"]')?.content

async function request(method, url, body, opts = {}) {
    const headers = { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' }
    if (csrfToken) headers['X-CSRF-TOKEN'] = csrfToken

    let payload
    if (body instanceof FormData) {
        payload = body
    } else if (body !== undefined) {
        headers['Content-Type'] = 'application/json'
        payload = JSON.stringify(body)
    }

    const res = await fetch(url, { method, headers, body: payload, credentials: 'same-origin' })

    if (res.status === 419) {
        // CSRF/session expired: force re-login
        window.dispatchEvent(new CustomEvent('auth:expired'))
        throw new ApiError('Your session expired. Please log in again.', 419)
    }

    let data = null
    const text = await res.text()
    try { data = text ? JSON.parse(text) : null } catch { data = null }

    if (!res.ok) {
        const message = data?.message || `Request failed (${res.status})`
        throw new ApiError(message, res.status, data?.errors || null, data)
    }
    return data
}

export class ApiError extends Error {
    constructor(message, status, errors = null, body = null) {
        super(message)
        this.status = status
        this.errors = errors
        this.body = body
    }
}

export const api = {
    get: (url) => request('GET', url),
    post: (url, body) => request('POST', url, body),
    patch: (url, body) => request('PATCH', url, body),
    delete: (url) => request('DELETE', url),
    upload: (url, formData) => request('POST', url, formData),
}

// ---- Offline sync queue (safe progress actions only) ----
const QUEUE_KEY = 'lingua.pendingActions'

export function queueAction(action) {
    const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]')
    // dedupe by key so retries do not double-submit
    if (action.key && queue.some((a) => a.key === action.key)) return
    queue.push({ ...action, queuedAt: Date.now() })
    localStorage.setItem(QUEUE_KEY, JSON.stringify(queue))
}

export function pendingActionCount() {
    return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]').length
}

export async function flushQueue() {
    const queue = JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]')
    if (!queue.length) return 0
    let flushed = 0
    const remaining = []
    for (const action of queue) {
        try {
            await request(action.method, action.url, action.body)
            flushed++
        } catch (e) {
            // 4xx = permanently rejected (e.g. duplicate), drop it; network errors keep it
            if (e instanceof ApiError && e.status >= 400 && e.status < 500) flushed++
            else remaining.push(action)
        }
    }
    localStorage.setItem(QUEUE_KEY, JSON.stringify(remaining))
    return flushed
}

window.addEventListener('online', () => { flushQueue() })
