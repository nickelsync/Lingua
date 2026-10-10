<template>
    <button
        type="button"
        class="btn-secondary gap-2"
        :disabled="!canPlay"
        :title="canPlay ? 'Play audio' : 'Audio not supported in this browser'"
        @click="play"
    >
        <span aria-hidden="true">{{ tts.speaking.value || playing ? '◼' : '▶' }}</span>
        {{ tts.speaking.value || playing ? 'Stop' : label }}
    </button>
</template>

<script setup>
import { computed, ref } from 'vue'
import { useTts } from '../composables/useSpeech'

const props = defineProps({
    text: String,
    src: String,
    label: { type: String, default: 'Listen' },
    rate: { type: Number, default: 1 },
})

const tts = useTts()
const playing = ref(false)
let audio = null

const canPlay = computed(() => !!props.src || (tts.supported && !!props.text))

function play() {
    if (playing.value || tts.speaking.value) {
        stop()
        return
    }
    if (props.src) {
        audio = new Audio(props.src)
        audio.onended = () => (playing.value = false)
        audio.onerror = () => {
            playing.value = false
            if (props.text) tts.speak(props.text, props.rate)
        }
        playing.value = true
        audio.play().catch(() => (playing.value = false))
    } else if (props.text) {
        tts.speak(props.text, props.rate)
    }
}

function stop() {
    if (audio) { audio.pause(); audio = null }
    playing.value = false
    tts.stop()
}
</script>
