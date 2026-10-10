<template>
    <div class="card p-4">
        <div class="flex items-start justify-between gap-3">
            <div class="min-w-0">
                <p class="flex flex-wrap items-baseline gap-x-2">
                    <span class="font-semibold text-ink-100">{{ word.word }}</span>
                    <span v-if="word.ipa" class="text-xs text-ink-400">{{ word.ipa }}</span>
                    <span v-if="word.part_of_speech" class="rounded border border-ink-700 px-1.5 py-0.5 text-[10px] uppercase text-ink-400">{{ word.part_of_speech }}</span>
                </p>
                <p class="mt-1 text-sm text-ink-300">{{ word.definition }}</p>
                <p v-if="word.meaning_id" class="mt-0.5 text-sm italic text-ink-400">🇮🇩 {{ word.meaning_id }}</p>
                <p v-if="word.example" class="mt-2 text-xs text-ink-400">“{{ word.example }}”</p>
                <div class="mt-2 flex flex-wrap items-center gap-2 text-[10px] text-ink-400">
                    <span v-if="word.level" class="rounded-full border border-ink-700 px-2 py-0.5 uppercase">{{ word.level.code }}</span>
                    <span v-if="word.category" class="rounded-full border border-ink-700 px-2 py-0.5">{{ word.category }}</span>
                    <span v-if="word.srs?.due" class="rounded-full border border-accent-600/50 bg-accent-600/10 px-2 py-0.5 font-semibold text-accent-400">Due for review</span>
                </div>
            </div>
            <div class="flex shrink-0 flex-col gap-1.5">
                <AudioButton :text="word.word" :src="word.audio_path" label="" class="!px-2 !py-1 text-xs" />
                <button class="btn-ghost !px-2 !py-1 text-base" :title="word.saved ? 'Remove from my words' : 'Save word'" @click="$emit('toggle-save', word)">
                    {{ word.saved ? '★' : '☆' }}
                </button>
            </div>
        </div>
    </div>
</template>

<script setup>
import AudioButton from './AudioButton.vue'

defineProps({ word: { type: Object, required: true } })
defineEmits(['toggle-save'])
</script>
