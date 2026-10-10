<template>
    <div>
        <div v-if="question.passage" class="card mb-4 bg-ink-850 p-4 text-sm leading-relaxed text-ink-200">{{ question.passage }}</div>
        <div v-if="question.type === 'listening' || question.audio_text || question.audio_path" class="mb-4">
            <AudioButton :text="question.audio_text" :src="question.audio_path" label="Play audio" />
        </div>

        <p class="mb-4 text-base font-medium text-ink-100">{{ question.prompt }}</p>

        <div v-if="question.options" class="flex flex-col gap-2">
            <button
                v-for="opt in question.options"
                :key="opt.id"
                type="button"
                class="rounded-md border px-4 py-3 text-left text-sm transition-colors"
                :class="modelValue === opt.id ? 'border-accent-500 bg-ink-800 text-ink-100' : 'border-ink-600 bg-ink-900 text-ink-200 hover:border-ink-400'"
                @click="$emit('update:modelValue', opt.id)"
            >
                {{ opt.text }}
            </button>
        </div>

        <div v-else-if="isText">
            <input :value="modelValue || ''" type="text" class="input" placeholder="Type your answer…" autocomplete="off" spellcheck="false" @input="$emit('update:modelValue', $event.target.value)">
        </div>

        <!-- Rearrange: tap tokens in order -->
        <div v-else-if="question.type === 'rearrange'">
            <div class="mb-3 flex min-h-12 flex-wrap items-center gap-2 rounded-md border border-dashed border-ink-600 bg-ink-900 p-3">
                <button
                    v-for="(token, i) in arranged"
                    :key="'a' + i"
                    type="button"
                    class="rounded-md bg-accent-600/20 border border-accent-600/50 px-3 py-1.5 text-sm text-accent-400"
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
                <select :value="(modelValue || {})[left] || ''" class="input sm:flex-1" @change="setMatch(left, $event.target.value)">
                    <option value="">Choose a match…</option>
                    <option v-for="right in question.right" :key="right" :value="right">{{ right }}</option>
                </select>
            </div>
        </div>

        <div v-else-if="question.type === 'speaking'">
            <div class="card mb-4 flex items-center justify-between gap-3 bg-ink-850 p-4">
                <p class="text-base font-medium text-ink-100">“{{ question.target }}”</p>
                <AudioButton :text="question.target" label="Hear it" />
            </div>
            <div v-if="stt.supported" class="flex flex-col gap-3">
                <button type="button" class="btn-primary self-start" :class="{ 'animate-pulse': stt.listening.value }" @click="toggleListen">
                    {{ stt.listening.value ? '◼ Stop recording' : '🎤 Start speaking' }}
                </button>
                <p v-if="modelValue" class="text-sm text-ink-300">Recorded: “{{ modelValue }}”</p>
                <p v-if="stt.error.value" class="text-sm text-red-400">{{ stt.error.value }}</p>
            </div>
            <div v-else>
                <p class="mb-2 text-sm text-ink-400">Speech recognition unavailable — type the sentence instead:</p>
                <input :value="modelValue || ''" type="text" class="input" @input="$emit('update:modelValue', $event.target.value)">
            </div>
        </div>

        <div v-else class="text-sm text-ink-400">Unsupported question type.</div>
    </div>
</template>

<script setup>
import { computed, ref, watch } from 'vue'
import AudioButton from './AudioButton.vue'
import { useSpeechRecognition } from '../composables/useSpeech'

const props = defineProps({
    question: { type: Object, required: true },
    modelValue: { default: null },
})
const emit = defineEmits(['update:modelValue'])

const stt = useSpeechRecognition()
const isText = ['fill_blank', 'translation', 'typing'].includes(props.question.type)

// Rearrange state; restore from a saved answer when navigating back to the question.
const arranged = ref(Array.isArray(props.modelValue) ? [...props.modelValue] : [])
const removedIdx = ref([])
if (arranged.value.length) {
    const used = []
    for (const token of arranged.value) {
        const i = (props.question.tokens || []).findIndex((t, idx) => t === token && !used.includes(idx))
        if (i !== -1) used.push(i)
    }
    removedIdx.value = used
}
const remainingTokens = computed(() => (props.question.tokens || []).filter((_, i) => !removedIdx.value.includes(i)))

function addToken(visibleIdx) {
    const realIdx = (props.question.tokens || []).map((_, i) => i).filter((i) => !removedIdx.value.includes(i))[visibleIdx]
    removedIdx.value.push(realIdx)
    arranged.value.push(props.question.tokens[realIdx])
    emit('update:modelValue', [...arranged.value])
}

function removeToken(i) {
    const token = arranged.value[i]
    arranged.value.splice(i, 1)
    const ri = removedIdx.value.findIndex((idx) => props.question.tokens[idx] === token)
    if (ri !== -1) removedIdx.value.splice(ri, 1)
    emit('update:modelValue', arranged.value.length ? [...arranged.value] : null)
}

function setMatch(left, right) {
    const next = { ...(props.modelValue || {}) }
    if (right) next[left] = right
    else delete next[left]
    emit('update:modelValue', Object.keys(next).length ? next : null)
}

watch(stt.transcript, (t) => {
    if (t) emit('update:modelValue', t)
})

function toggleListen() {
    stt.listening.value ? stt.stop() : stt.start()
}
</script>
