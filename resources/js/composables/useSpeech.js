// Browser-native speech: TTS via speechSynthesis, STT via Web Speech API.
// Both free, no keys. Graceful fallbacks when unsupported.
import { ref } from 'vue'

let cachedVoice = null

function pickAmericanVoice() {
    if (cachedVoice) return cachedVoice
    const voices = window.speechSynthesis?.getVoices?.() || []
    const preferred = localStorage.getItem('lingua:voice')
    if (preferred) {
        const match = voices.find((v) => v.name === preferred)
        if (match) { cachedVoice = match; return cachedVoice }
    }
    cachedVoice =
        voices.find((v) => v.lang === 'en-US' && v.localService) ||
        voices.find((v) => v.lang === 'en-US') ||
        voices.find((v) => v.lang?.startsWith('en')) ||
        null
    return cachedVoice
}

if (window.speechSynthesis) {
    window.speechSynthesis.onvoiceschanged = () => { cachedVoice = null; pickAmericanVoice() }
}

// One-off helpers used outside components (e.g. settings page).
export function speak(text, rate = 1) {
    if (!window.speechSynthesis || !text) return false
    cachedVoice = null
    window.speechSynthesis.cancel()
    const utterance = new SpeechSynthesisUtterance(text)
    utterance.lang = 'en-US'
    utterance.rate = rate
    const voice = pickAmericanVoice()
    if (voice) utterance.voice = voice
    window.speechSynthesis.speak(utterance)
    return true
}

export function getVoices() {
    return new Promise((resolve) => {
        if (!window.speechSynthesis) return resolve([])
        const list = window.speechSynthesis.getVoices()
        if (list.length) return resolve(sortVoices(list))
        window.speechSynthesis.onvoiceschanged = () => resolve(sortVoices(window.speechSynthesis.getVoices()))
        setTimeout(() => resolve(sortVoices(window.speechSynthesis.getVoices())), 1500)
    })
}

function sortVoices(list) {
    return [...list]
        .filter((v) => v.lang?.startsWith('en'))
        .sort((a, b) => (a.lang === 'en-US' ? -1 : 1) - (b.lang === 'en-US' ? -1 : 1) || a.name.localeCompare(b.name))
}

export function useTts() {
    const speaking = ref(false)
    const supported = !!window.speechSynthesis

    function speak(text, rate = 1) {
        if (!supported || !text) return false
        window.speechSynthesis.cancel()
        const utterance = new SpeechSynthesisUtterance(text)
        utterance.lang = 'en-US'
        utterance.rate = rate
        const voice = pickAmericanVoice()
        if (voice) utterance.voice = voice
        utterance.onstart = () => (speaking.value = true)
        utterance.onend = () => (speaking.value = false)
        utterance.onerror = () => (speaking.value = false)
        window.speechSynthesis.speak(utterance)
        return true
    }

    function stop() {
        if (supported) window.speechSynthesis.cancel()
        speaking.value = false
    }

    return { speak, stop, speaking, supported }
}

export function useSpeechRecognition() {
    const Recognition = window.SpeechRecognition || window.webkitSpeechRecognition
    const supported = !!Recognition
    const listening = ref(false)
    const transcript = ref('')
    const error = ref(null)
    let recognition = null

    function start() {
        if (!supported) {
            error.value = 'Speech recognition is not supported in this browser. Try Chrome or Edge, or type your answer instead.'
            return
        }
        error.value = null
        transcript.value = ''
        recognition = new Recognition()
        recognition.lang = 'en-US'
        recognition.interimResults = true
        recognition.maxAlternatives = 1
        recognition.onresult = (event) => {
            transcript.value = Array.from(event.results).map((r) => r[0].transcript).join(' ').trim()
        }
        recognition.onerror = (event) => {
            listening.value = false
            error.value = event.error === 'not-allowed'
                ? 'Microphone access was denied. Allow microphone access and try again.'
                : event.error === 'no-speech'
                    ? 'No speech detected. Try again and speak clearly.'
                    : 'Speech recognition failed. You can type the sentence instead.'
        }
        recognition.onend = () => (listening.value = false)
        recognition.start()
        listening.value = true
    }

    function stop() {
        recognition?.stop()
        listening.value = false
    }

    return { start, stop, listening, transcript, error, supported }
}
