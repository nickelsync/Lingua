<template>
    <section class="card overflow-hidden">
        <div class="border-b border-ink-800 bg-ink-850/60 px-5 py-2.5">
            <h2 class="flex items-center gap-2 text-sm font-semibold text-ink-100">
                <span aria-hidden="true">{{ icon }}</span>
                {{ section.title || typeLabel }}
            </h2>
        </div>
        <div class="px-5 py-4">
            <!-- Text body -->
            <div v-if="section.body" class="whitespace-pre-line text-sm leading-relaxed text-ink-200" v-html="renderedBody"></div>
            <div v-if="section.body_id" class="mt-3 rounded-md border border-ink-700 bg-ink-850 px-4 py-3 text-sm italic leading-relaxed text-ink-300">
                🇮🇩 {{ section.body_id }}
            </div>

            <!-- Examples -->
            <div v-if="section.meta?.items" class="mt-1 space-y-3">
                <div v-for="(item, i) in section.meta.items" :key="i" class="rounded-md border border-ink-800 bg-ink-850/50 px-4 py-3">
                    <div class="flex items-start justify-between gap-3">
                        <p class="text-sm font-medium text-ink-100">{{ item.en }}</p>
                        <AudioButton :text="item.en" label="" class="!px-2 !py-1 text-xs" />
                    </div>
                    <p v-if="item.id" class="mt-1 text-xs italic text-ink-400">{{ item.id }}</p>
                </div>
            </div>

            <!-- Vocabulary words -->
            <div v-if="section.meta?.words" class="mt-1 space-y-2">
                <div v-for="(word, i) in section.meta.words" :key="i" class="flex flex-col gap-2 rounded-md border border-ink-800 bg-ink-850/50 px-4 py-3 sm:flex-row sm:items-center sm:justify-between">
                    <div>
                        <p class="text-sm">
                            <span class="font-semibold text-ink-100">{{ word.word }}</span>
                            <span v-if="word.ipa" class="ml-2 text-xs text-ink-400">{{ word.ipa }}</span>
                            <span v-if="word.meaning_id" class="ml-2 text-xs italic text-ink-400">— {{ word.meaning_id }}</span>
                        </p>
                        <p v-if="word.example" class="mt-0.5 text-xs text-ink-400">“{{ word.example }}”</p>
                    </div>
                    <div class="flex shrink-0 gap-2">
                        <AudioButton :text="word.word" label="" class="!px-2 !py-1 text-xs" />
                        <button class="btn-secondary !px-2.5 !py-1 text-xs" @click="$emit('save-word', word)">+ Save</button>
                    </div>
                </div>
            </div>

            <!-- Dialogue -->
            <div v-if="section.meta?.lines" class="mt-1 space-y-2">
                <div class="mb-3 flex justify-end">
                    <AudioButton :text="dialogueText" label="Play dialogue" />
                </div>
                <div v-for="(line, i) in section.meta.lines" :key="i" class="flex gap-3">
                    <span class="w-16 shrink-0 pt-0.5 text-xs font-semibold text-accent-400">{{ line.speaker }}</span>
                    <p class="flex-1 rounded-md bg-ink-850 px-3 py-2 text-sm text-ink-200">{{ line.text }}</p>
                </div>
            </div>

            <!-- Listening audio -->
            <div v-if="section.meta?.audio_text" class="mt-3 flex gap-2">
                <AudioButton :text="section.meta.audio_text" label="Play audio" />
                <AudioButton :text="section.meta.audio_text" :rate="0.7" label="Slow" />
            </div>
        </div>
    </section>
</template>

<script setup>
import { computed } from 'vue'
import AudioButton from './AudioButton.vue'

const props = defineProps({ section: { type: Object, required: true } })
defineEmits(['save-word'])

const icons = {
    intro: '👋', explanation: '📖', examples: '💬', vocabulary: '🔤',
    dialogue: '🗣', listening: '🎧', reading: '📄', note: '⚠️', tip: '💡',
}
const labels = {
    intro: 'Introduction', explanation: 'Explanation', examples: 'Examples', vocabulary: 'Vocabulary',
    dialogue: 'Dialogue', listening: 'Listening', reading: 'Reading', note: 'Note', tip: 'Tip',
}
const icon = computed(() => icons[props.section.type] || '▪')
const typeLabel = computed(() => labels[props.section.type] || 'Section')
const dialogueText = computed(() => (props.section.meta?.lines || []).map((l) => l.text).join(' … '))

// Escape HTML, then support the only inline markup content uses: **bold**.
const renderedBody = computed(() => {
    const escaped = String(props.section.body || '')
        .replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;')
    return escaped.replace(/\*\*([^*]+)\*\*/g, '<strong class="text-ink-100">$1</strong>')
})
</script>
