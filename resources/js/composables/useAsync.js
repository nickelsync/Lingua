// Standard loading/error/empty wrapper for data fetching.
import { ref } from 'vue'

export function useAsync(fn) {
    const data = ref(null)
    const loading = ref(false)
    const error = ref(null)

    async function run(...args) {
        loading.value = true
        error.value = null
        try {
            data.value = await fn(...args)
            return data.value
        } catch (e) {
            error.value = e.message || 'Something went wrong.'
            throw e
        } finally {
            loading.value = false
        }
    }

    return { data, loading, error, run }
}
