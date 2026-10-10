<template>
    <div>
        <!-- Passage for reading questions -->
        <div v-if="question.passage" class="card mb-4 bg-ink-850 p-4 text-sm leading-relaxed text-ink-200">
            {{ question.passage }}
        </div>

        <!-- Audio for listening questions -->
        <div v-if="question.type === 'listening' || question.audio_text || question.audio_path" class="mb-4">
            <AudioButton :text="question.audio_text" :src="question.audio_path" label="Play audio" />
            <AudioButton v-if="question.audio_text" :text="question.audio_text" :rate="0.7" label="Slow" class="ml-2" />
        </div>

        <p class="mb-4 text-base font-medium text-ink-100">{{ question.prompt }}</p>

        <!-- Choice-based: multiple_choice, listening, reading, true_false -->
        <div v-if="hasOptions" class="flex flex-col gap-2">
            <button
                v-for="opt in question.options"
                :key="opt.id"
                type="button"
                class="rounded-md border px-4 py-3 text-left text-sm transition-colors"
                :class="optionClass(opt.id)"
                :disabled="locked"
                @click="selectOption(opt.id)"
            >
                {{ opt.text }}
            </button>
        </div>

        <!-- Text answers: fill_blank, translation, typing -->
        <div v-else-if="isText">
            <input
                v-model="textAnswer"
                type="text"
                class="input"
                :disabled="locked"
                placeholder="Type your answer…"
                autocomplete="off"
                autocapitalize="off"
                spellcheck="false"
                @keyup.enter="emitAnswer"
            >
        </div>

        <!-- Rearrange: tap tokens in order -->
        <div v-else-if="question.type === 'rearrange'">
            <div class="mb-3 flex min-h-12 flex-wrap items-center gap-2 rounded-md border border-dashed border-ink-600 bg-ink-900 p-3">
                <button
                    v-for="(token, i) in arranged"
                    :key="'a' + i"
                    type="button"
                    class="rounded-md bg-accent-600/20 border border-accent-600/50 px-3 py-1.5 text-sm text-accent-400"
                    :disabled="locked"
                    @click="removeToken(i)"
                >
                    {{ token }}
                </button>
                <span v-if="!arranged.length" class="text-sm text-ink-400">Tap the words below in order</span>
            </div>
            <div class="flex flex-wrap gap-2">
                <button
                    v-for="(token, i) in remainingTokens"
                    :key="'r' + i"
                    type="button"
                    class="rounded-md border border-ink-600 bg-ink-800 px-3 py-1.5 text-sm text-ink-200 hover:bg-ink-700"
                    :disabled="locked"
                    @click="addToken(i)"
                >
                    {{ token }}
                </button>
            </div>
        </div>

        <!-- Matching: pick a right-side match for each left item -->
        <div v-else-if="question.type === 'matching'" class="flex flex-col gap-3">
            <div v-for="left in question.left" :key="left" class="flex flex-col gap-1 sm:flex-row sm:items-center sm:gap-3">
                <span class="w-full text-sm font-medium text-ink-200 sm:w-1/3">{{ left }}</span>
                <select v-model="matches[left]" class="input sm:flex-1" :disabled="locked">
                    <option value="">Choose a match…</option>
                    <option v-for="right in question.right" :key="right" :value="right">{{ right }}</option>
                </select>
            </div>
        </div>

        <!-- Speaking: record via Web Speech API -->
        <div v-else-if="question.type === 'speaking'">
            <div class="card mb-4 flex items-center justify-between gap-3 bg-ink-850 p-4">
                <p class="text-base font-medium text-ink-100">“{{ question.target }}”</p>
                <AudioButton :text="question.target" label="Hear it" />
            </div>
            <div v-if="stt.supported" class="flex flex-col gap-3">
                <button
                    type="button"
                    class="btn-primary self-start"
                    :class="{ 'animate-pulse': stt.listening.value }"
                    :disabled="locked"
                    @click="stt.listening.value ? stt.stop() : stt.start()"
                >
                    {{ stt.listening.value ? '◼ Stop recording' : '🎤 Start speaking' }}
                </button>
                <p v-if="stt.transcript.value" class="text-sm text-ink-300">Heard: “{{ stt.transcript.value }}”</p>
                <p v-if="stt.error.value" class="text-sm text-red-400">{{ stt.error.value }}</p>
                <div v-if="stt.error.value" class="mt-1">
                    <p class="mb-2 text-sm text-ink-400">Or type the sentence instead:</p>
                    <input v-model="textAnswer" type="text" class="input" :disabled="locked" placeholder="Type the sentence…">
                </div>
            </div>
            <div v-else class="mt-2">
                <p class="mb-2 text-sm text-ink-400">Speech recognition is not available in this browser. Type the sentence instead:</p>
                <input v-model="textAnswer" type="text" class="input" :disabled="locked" placeholder="Type the sentence…">
            </div>
            <p class="mt-3 text-xs text-ink-400">Scoring compares the words recognized from your speech to the target sentence. It does not measure pronunciation quality.</p>
        </div>

        <!-- Writing -->
        <div v-else-if="question.type === 'writing'">
            <textarea v-model="textAnswer" rows="6" class="input" :disabled="locked" placeholder="Write your answer here…" />
            <p class="mt-1 text-xs text-ink-400">{{ wordCount }} words</p>
        </div>

        <!-- Submit + feedback -->
        <div class="mt-5 flex items-center gap-3">
            <button v-if="!locked" type="button" class="btn-primary" :disabled="!canSubmit || checking" @click="emitAnswer">
                {{ checking ? 'Checking…' : submitLabel }}
            </button>
            <slot name="actions" />
        </div>

        <div v-if="feedback" class="mt-4 rounded-md border p-4 text-sm" :class="feedback.correct ? 'border-accent-600/50 bg-accent-600/10' : 'border-red-800 bg-red-900/20'">
            <p class="font-semibold" :class="feedback.correct ? 'text-accent-400' : 'text-red-300'">
                {{ feedback.correct ? 'Correct!' : 'Not quite.' }}
                <span v-if="feedback.accuracy !== undefined" class="font-normal text-ink-300">Word match: {{ feedback.accuracy }}%</span>
            </p>
            <p v-if="!feedback.correct && feedback.correct_answer" class="mt-1 text-ink-200">
                Correct answer: <strong>{{ formatCorrectAnswer(feedback.correct_answer) }}</strong>
            </p>
            <p v-if="feedback.explanation" class="mt-2 text-ink-300">{{ feedback.explanation }}</p>
            <p v-if="feedback.explanation_id" class="mt-1 text-ink-400 italic">{{ feedback.explanation_id }}</p>
            <p v-if="feedback.missing?.length" class="mt-2 text-ink-300">Missing words: {{ feedback.missing.join(', ') }}</p>
        </div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AudioButton from './AudioButton.vue'
import { useSpeechRecognition } from '../composables/useSpeech'

const props = defineProps({
    question: { type: Object, required: true },
    feedback: { type: Object, default: null },
    checking: Boolean,
    locked: Boolean,
    submitLabel: { type: String, default: 'Check answer' },
})
const emit = defineEmits(['answer'])

const selected = ref(null)
const textAnswer = ref('')
const arranged = ref([])
const removedIdx = ref([])
const matches = ref({})
const stt = useSpeechRecognition()

watch(() => props.question, () => {
    selected.value = null
    textAnswer.value = ''
    arranged.value = []
    removedIdx.value = []
    matches.value = {}
    stt.transcript.value = ''
}, { immediate: true })

const hasOptions = computed(() => ['multiple_choice', 'listening', 'reading', 'true_false'].includes(props.question.type) && props.question.options)
const isText = computed(() => ['fill_blank', 'translation', 'typing'].includes(props.question.type))
const wordCount = computed(() => textAnswer.value.trim() ? textAnswer.value.trim().split(/\s+/).length : 0)

const remainingTokens = computed(() => (props.question.tokens || []).filter((_, i) => !removedIdx.value.includes(i)))

const canSubmit = computed(() => {
    const q = props.question
    if (hasOptions.value) return selected.value !== null
    if (isText.value || q.type === 'writing') return textAnswer.value.trim().length > 0
    if (q.type === 'rearrange') return arranged.value.length === (q.tokens || []).length && arranged.value.length > 0
    if (q.type === 'matching') return (q.left || []).every((l) => matches.value[l])
    if (q.type === 'speaking') return stt.transcript.value.trim().length > 0 || textAnswer.value.trim().length > 0
    return false
})

function selectOption(id) {
    if (props.locked) return
    selected.value = id
}

function optionClass(id) {
    const base = props.feedback && selected.value === id
        ? props.feedback.correct
            ? 'border-accent-500 bg-accent-600/15 text-accent-400'
            : 'border-red-700 bg-red-900/20 text-red-300'
        : selected.value === id
            ? 'border-accent-500 bg-ink-800 text-ink-100'
            : 'border-ink-600 bg-ink-900 text-ink-200 hover:border-ink-400'
    return base + (props.locked ? ' cursor-default opacity-90' : '')
}

function addToken(visibleIdx) {
    const tokens = props.question.tokens || []
    let count = -1
    for (let i = 0; i < tokens.length; i++) {
        if (!removedIdx.value.includes(i)) count++
        if (count === visibleIdx) {
            removedIdx.value.push(i)
            arranged.value.push(tokens[i])
            return
        }
    }
}

function removeToken(i) {
    arranged.value.splice(i, 1)
    removedIdx.value.splice(i, 1)
}

function currentAnswer() {
    const q = props.question
    if (hasOptions.value) return selected.value
    if (isText.value || q.type === 'writing') return textAnswer.value.trim()
    if (q.type === 'rearrange') return arranged.value
    if (q.type === 'matching') return { ...matches.value }
    if (q.type === 'speaking') return stt.transcript.value.trim() || textAnswer.value.trim()
    return null
}

function emitAnswer() {
    if (!canSubmit.value || props.locked) return
    emit('answer', currentAnswer())
}

function formatCorrectAnswer(answer) {
    if (Array.isArray(answer)) return answer.map((p) => (p.left ? `${p.left} → ${p.right}` : p)).join('; ')
    return answer
}

defineExpose({ currentAnswer, canSubmit })
</script>
