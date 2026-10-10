<template>
    <div class="mx-auto max-w-3xl">
        <h1 class="text-2xl font-bold text-ink-100">Practice studio</h1>
        <p class="mt-1 text-ink-400">Sharpen your speaking and listening with free browser-powered tools.</p>

        <!-- Mode tabs -->
        <div class="mt-6 flex gap-1 rounded-lg border border-ink-700 bg-ink-900 p-1">
            <button class="tab" :class="{ active: mode === 'speaking' }" @click="mode = 'speaking'">🎤 Speaking</button>
            <button class="tab" :class="{ active: mode === 'listening' }" @click="mode = 'listening'">🎧 Listening</button>
            <button class="tab" :class="{ active: mode === 'history' }" @click="mode = 'history'; loadHistory()">◷ History</button>
        </div>

        <!-- SPEAKING -->
        <div v-show="mode === 'speaking'" class="mt-6">
            <div class="card p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-ink-100">Repeat the sentence</h2>
                    <button class="btn-ghost text-sm" @click="nextSentence">↻ New sentence</button>
                </div>
                <div class="card mt-4 flex flex-col items-start justify-between gap-3 bg-ink-850 p-5 sm:flex-row sm:items-center">
                    <p class="text-lg font-medium text-ink-100">“{{ sentence }}”</p>
                    <div class="flex shrink-0 gap-2">
                        <AudioButton :text="sentence" label="Listen" />
                        <AudioButton :text="sentence" :rate="0.7" label="Slow" />
                    </div>
                </div>

                <div v-if="stt.supported" class="mt-5">
                    <button class="btn-primary" :class="{ 'animate-pulse': stt.listening.value }" @click="stt.listening.value ? stt.stop() : startSpeaking()">
                        {{ stt.listening.value ? '◼ Stop recording' : '🎤 Start speaking' }}
                    </button>
                    <p v-if="stt.transcript.value" class="mt-3 text-sm text-ink-300">Heard: “{{ stt.transcript.value }}”</p>
                    <p v-if="stt.error.value" class="mt-3 text-sm text-red-400">{{ stt.error.value }}</p>
                    <button v-if="stt.transcript.value && !stt.listening.value" class="btn-secondary mt-3" :disabled="scoring" @click="score">
                        {{ scoring ? 'Scoring…' : 'Score my attempt' }}
                    </button>
                </div>
                <div v-else class="mt-5 rounded-md border border-ink-700 bg-ink-850 p-4 text-sm text-ink-400">
                    Speech recognition isn't supported in this browser. Try Chrome or Edge on desktop or Android. You can still use the audio playback to practice listening and repeating.
                </div>

                <div v-if="speakResult" class="mt-5 rounded-md border p-4" :class="speakResult.accuracy >= 70 ? 'border-accent-600/50 bg-accent-600/10' : 'border-ink-600 bg-ink-850'">
                    <p class="text-lg font-bold" :class="speakResult.accuracy >= 70 ? 'text-accent-400' : 'text-ink-100'">Word match: {{ speakResult.accuracy }}%</p>
                    <p v-if="speakResult.missing?.length" class="mt-1 text-sm text-ink-300">Missing: {{ speakResult.missing.join(', ') }}</p>
                    <p v-if="speakResult.xp_awarded > 0" class="mt-1 text-sm text-accent-400">+{{ speakResult.xp_awarded }} XP</p>
                    <p class="mt-2 text-xs text-ink-400">This score reflects how many target words were recognized in your speech — not pronunciation quality.</p>
                </div>
            </div>
        </div>

        <!-- LISTENING -->
        <div v-show="mode === 'listening'" class="mt-6">
            <div class="card p-6">
                <div class="flex items-center justify-between gap-3">
                    <h2 class="font-semibold text-ink-100">Dictation: type what you hear</h2>
                    <button class="btn-ghost text-sm" @click="nextDictation">↻ New sentence</button>
                </div>
                <div class="mt-4 flex gap-2">
                    <AudioButton :text="dictation" label="Play" />
                    <AudioButton :text="dictation" :rate="0.7" label="Slow" />
                </div>
                <input v-model="dictationAnswer" type="text" class="input mt-4" placeholder="Type the sentence you hear…" spellcheck="false" autocapitalize="off" :disabled="dictationResult !== null" @keyup.enter="checkDictation">
                <div class="mt-4 flex gap-3">
                    <button v-if="dictationResult === null" class="btn-primary" :disabled="!dictationAnswer.trim()" @click="checkDictation">Check</button>
                    <button v-else class="btn-primary" @click="nextDictation">Next sentence →</button>
                </div>
                <div v-if="dictationResult !== null" class="mt-4 rounded-md border p-4" :class="dictationResult ? 'border-accent-600/50 bg-accent-600/10' : 'border-red-800 bg-red-900/20'">
                    <p class="font-semibold" :class="dictationResult ? 'text-accent-400' : 'text-red-300'">{{ dictationResult ? 'Correct!' : 'Not quite.' }}</p>
                    <p v-if="!dictationResult" class="mt-1 text-sm text-ink-200">The sentence was: <strong>“{{ dictation }}”</strong></p>
                </div>
            </div>
        </div>

        <!-- HISTORY -->
        <div v-show="mode === 'history'" class="mt-6">
            <LoadingState v-if="historyLoading" message="Loading history…" />
            <EmptyState v-else-if="!history.length" title="No speaking attempts yet" message="Your speaking practice history will appear here." />
            <div v-else class="space-y-3">
                <div v-for="a in history" :key="a.id" class="card p-4">
                    <div class="flex items-center justify-between gap-3">
                        <p class="text-sm font-medium text-ink-100">“{{ a.target_text }}”</p>
                        <span class="shrink-0 rounded-full px-2.5 py-0.5 text-xs font-bold" :class="a.accuracy >= 70 ? 'bg-accent-600/15 text-accent-400' : 'bg-ink-800 text-ink-300'">{{ a.accuracy }}%</span>
                    </div>
                    <p class="mt-1 text-xs text-ink-400">You said: “{{ a.transcript }}” · {{ new Date(a.created_at).toLocaleDateString() }}</p>
                </div>
            </div>
        </div>
    </div>
</template>

<script setup>
import { ref } from 'vue'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'
import { useSpeechRecognition } from '../composables/useSpeech'
import AudioButton from '../components/AudioButton.vue'
import LoadingState from '../components/LoadingState.vue'
import EmptyState from '../components/EmptyState.vue'

const ui = useUiStore()
const mode = ref('speaking')
const stt = useSpeechRecognition()

const sentences = [
    'Good morning. How are you today?',
    'I would like a cup of coffee, please.',
    'Could you tell me where the train station is?',
    'She has been working here for five years.',
    'The weather is beautiful this afternoon.',
    'I usually wake up at seven in the morning.',
    'Can I have the check, please?',
    'What do you usually do on the weekend?',
    'He is taller than his older brother.',
    'We are planning a trip to the mountains next month.',
    'If it rains tomorrow, we will stay home.',
    'I have never seen such a beautiful sunset before.',
    'The meeting has been postponed until next Thursday.',
    'Despite the traffic, we arrived at the airport on time.',
    'She suggested that we try the new restaurant downtown.',
]

const sentence = ref(sentences[Math.floor(Math.random() * sentences.length)])
const speakResult = ref(null)
const scoring = ref(false)

const dictation = ref('')
const dictationAnswer = ref('')
const dictationResult = ref(null)

nextDictation()

function nextSentence() {
    let next = sentence.value
    while (next === sentence.value) next = sentences[Math.floor(Math.random() * sentences.length)]
    sentence.value = next
    speakResult.value = null
    stt.transcript.value = ''
}

function startSpeaking() {
    speakResult.value = null
    stt.start()
}

async function score() {
    scoring.value = true
    try {
        speakResult.value = await api.post('/api/speaking/score', {
            target: sentence.value,
            transcript: stt.transcript.value,
        })
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        scoring.value = false
    }
}

function nextDictation() {
    let next = dictation.value
    while (next === dictation.value) next = sentences[Math.floor(Math.random() * sentences.length)]
    dictation.value = next
    dictationAnswer.value = ''
    dictationResult.value = null
}

function normalize(s) {
    return s.toLowerCase().replace(/[.,!?;:'"’]/g, '').replace(/\s+/g, ' ').trim()
}

function checkDictation() {
    if (!dictationAnswer.value.trim()) return
    dictationResult.value = normalize(dictationAnswer.value) === normalize(dictation.value)
}

const history = ref([])
const historyLoading = ref(false)

async function loadHistory() {
    historyLoading.value = true
    try {
        const data = await api.get('/api/speaking/history')
        history.value = data.attempts
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        historyLoading.value = false
    }
}
</script>

<style scoped>
.tab {
    flex: 1;
    border-radius: 0.375rem;
    padding: 0.5rem;
    font-size: 0.875rem;
    color: var(--color-ink-300);
    transition: background 0.15s;
}
.tab:hover { color: var(--color-ink-100); }
.tab.active { background: var(--color-ink-700); color: var(--color-ink-100); font-weight: 500; }
</style>
