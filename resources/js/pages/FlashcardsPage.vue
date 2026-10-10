<template>
    <div class="mx-auto max-w-lg">
        <LoadingState v-if="loading" message="Loading your review queue…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />

        <!-- Session complete -->
        <template v-else-if="finished">
            <div class="card p-8 text-center">
                <p class="text-4xl" aria-hidden="true">🎉</p>
                <h1 class="mt-3 text-xl font-bold text-ink-100">Review complete!</h1>
                <p class="mt-2 text-ink-300">You reviewed {{ reviewed }} {{ reviewed === 1 ? 'card' : 'cards' }}<span v-if="xpEarned > 0"> and earned {{ xpEarned }} XP</span>.</p>
                <p v-if="dueCount > 0" class="mt-1 text-sm text-ink-400">{{ dueCount }} more cards are due.</p>
                <div class="mt-6 flex flex-col justify-center gap-3 sm:flex-row">
                    <button v-if="dueCount > 0" class="btn-primary" @click="load">Keep reviewing</button>
                    <RouterLink to="/app/vocabulary" class="btn-secondary">My vocabulary</RouterLink>
                </div>
            </div>
        </template>

        <!-- Empty queue -->
        <EmptyState v-else-if="!cards.length" icon="▧" title="No cards due" message="All caught up! Save words from lessons or the word bank, and they'll appear here when it's time to review.">
            <RouterLink to="/app/vocabulary" class="btn-primary">Browse vocabulary</RouterLink>
        </EmptyState>

        <!-- Review -->
        <template v-else>
            <div class="mb-4 flex items-center justify-between text-sm text-ink-400">
                <h1 class="font-semibold text-ink-100">Flashcards</h1>
                <span>{{ index + 1 }} / {{ cards.length }}</span>
            </div>
            <ProgressBar :value="(index / cards.length) * 100" class="mb-6" />

            <!-- Card -->
            <button class="card block min-h-64 w-full p-8 text-center" @click="flipped = !flipped">
                <template v-if="!flipped">
                    <p class="text-xs font-medium uppercase tracking-wide text-ink-400">{{ reverse ? 'Meaning' : 'Word' }}</p>
                    <p class="mt-6 text-3xl font-bold text-ink-100">{{ reverse ? card.word.meaning_id || card.word.definition : card.word.word }}</p>
                    <p v-if="!reverse && card.word.ipa" class="mt-2 text-sm text-ink-400">{{ card.word.ipa }}</p>
                    <p class="mt-8 text-xs text-ink-400">Tap to reveal</p>
                </template>
                <template v-else>
                    <p class="text-xs font-medium uppercase tracking-wide text-accent-400">{{ reverse ? 'Word' : 'Meaning' }}</p>
                    <p class="mt-4 text-2xl font-bold text-ink-100">{{ reverse ? card.word.word : card.word.definition }}</p>
                    <p v-if="!reverse && card.word.meaning_id" class="mt-1 text-base italic text-ink-300">🇮🇩 {{ card.word.meaning_id }}</p>
                    <p v-if="card.word.example" class="mt-4 text-sm text-ink-400">“{{ card.word.example }}”</p>
                </template>
            </button>

            <div class="mt-3 flex justify-center">
                <AudioButton :text="card.word.word" label="Pronounce" />
            </div>

            <!-- Grading -->
            <div v-if="flipped" class="mt-5 grid grid-cols-4 gap-2">
                <button class="grade border-red-800 text-red-300 hover:bg-red-900/30" :disabled="grading" @click="grade(0)">Again</button>
                <button class="grade border-ink-600 text-ink-300 hover:bg-ink-800" :disabled="grading" @click="grade(1)">Hard</button>
                <button class="grade border-ink-600 text-ink-200 hover:bg-ink-800" :disabled="grading" @click="grade(2)">Good</button>
                <button class="grade border-accent-600/60 text-accent-400 hover:bg-accent-600/10" :disabled="grading" @click="grade(3)">Easy</button>
            </div>
            <p v-else class="mt-5 text-center text-xs text-ink-400">Think of the answer, then tap the card.</p>

            <label class="mt-6 flex items-center justify-center gap-2 text-xs text-ink-400">
                <input v-model="reverse" type="checkbox" class="h-3.5 w-3.5 rounded border-ink-600 bg-ink-900 accent-emerald-600">
                Reverse mode (meaning → word)
            </label>
        </template>
    </div>
</template>

<script setup>
import { computed, onMounted, ref } from 'vue'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import ProgressBar from '../components/ProgressBar.vue'
import AudioButton from '../components/AudioButton.vue'

const ui = useUiStore()
const cards = ref([])
const index = ref(0)
const flipped = ref(false)
const reverse = ref(false)
const grading = ref(false)
const finished = ref(false)
const reviewed = ref(0)
const xpEarned = ref(0)
const dueCount = ref(0)
const loading = ref(true)
const error = ref(null)

const card = computed(() => cards.value[index.value])

async function load() {
    loading.value = true
    error.value = null
    finished.value = false
    reviewed.value = 0
    xpEarned.value = 0
    index.value = 0
    flipped.value = false
    try {
        const data = await api.get('/api/review/queue?limit=20')
        cards.value = data.cards
        dueCount.value = data.due_count
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function grade(quality) {
    grading.value = true
    try {
        const data = await api.post(`/api/review/${card.value.entry_id}`, { quality })
        reviewed.value++
        xpEarned.value += data.xp_awarded || 0
        dueCount.value = data.due_count
        flipped.value = false
        if (index.value < cards.value.length - 1) {
            index.value++
        } else {
            finished.value = true
        }
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        grading.value = false
    }
}

onMounted(load)
</script>

<style scoped>
.grade {
    border-width: 1px;
    border-radius: 0.375rem;
    padding: 0.625rem 0.5rem;
    font-size: 0.8125rem;
    font-weight: 500;
    background: var(--color-ink-900);
    transition: background 0.15s;
}
.grade:disabled { opacity: 0.5; }
</style>
