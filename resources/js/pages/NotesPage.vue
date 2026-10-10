<template>
    <div class="mx-auto max-w-3xl">
        <div class="flex items-center justify-between gap-4">
            <div>
                <h1 class="text-2xl font-bold text-ink-100">My notes</h1>
                <p class="mt-1 text-ink-400">Private study notes — only you can see these.</p>
            </div>
            <button class="btn-primary shrink-0" @click="openEditor()">+ New note</button>
        </div>

        <LoadingState v-if="loading" message="Loading notes…" />
        <ErrorState v-else-if="error" :message="error" :retry="load" />
        <EmptyState v-else-if="!notes.length" icon="✎" title="No notes yet" message="Capture grammar rules, tricky words, or anything worth remembering.">
            <button class="btn-primary" @click="openEditor()">Write your first note</button>
        </EmptyState>
        <div v-else class="mt-6 space-y-3">
            <div v-for="note in notes" :key="note.id" class="card p-4">
                <div class="flex items-start justify-between gap-3">
                    <div class="min-w-0 flex-1 cursor-pointer" @click="openEditor(note)">
                        <p class="text-sm font-semibold text-ink-100">{{ note.title || 'Untitled note' }}</p>
                        <p class="mt-1 line-clamp-2 whitespace-pre-line text-sm text-ink-300">{{ note.body }}</p>
                        <p class="mt-2 text-xs text-ink-400">
                            {{ new Date(note.updated_at).toLocaleDateString() }}
                            <RouterLink v-if="note.lesson" :to="`/app/lesson/${note.lesson.slug}`" class="ml-2 text-accent-400 hover:underline" @click.stop>→ {{ note.lesson.title }}</RouterLink>
                        </p>
                    </div>
                    <button class="btn-ghost shrink-0 !px-2 text-sm" title="Delete note" @click="remove(note)">✕</button>
                </div>
            </div>
        </div>

        <BaseModal :open="editorOpen" :title="editing ? 'Edit note' : 'New note'" @close="editorOpen = false">
            <input v-model="form.title" type="text" class="input" placeholder="Title (optional)">
            <textarea v-model="form.body" rows="6" class="input mt-3" placeholder="Write your note…" />
            <button class="btn-primary mt-4 w-full" :disabled="!form.body.trim() || saving" @click="save">{{ saving ? 'Saving…' : 'Save note' }}</button>
        </BaseModal>
    </div>
</template>

<script setup>
import { onMounted, ref } from 'vue'
import { api } from '../api/client'
import { useUiStore } from '../stores/ui'
import LoadingState from '../components/LoadingState.vue'
import ErrorState from '../components/ErrorState.vue'
import EmptyState from '../components/EmptyState.vue'
import BaseModal from '../components/BaseModal.vue'

const ui = useUiStore()
const notes = ref([])
const loading = ref(true)
const error = ref(null)
const editorOpen = ref(false)
const editing = ref(null)
const saving = ref(false)
const form = ref({ title: '', body: '' })

function openEditor(note = null) {
    editing.value = note
    form.value = { title: note?.title || '', body: note?.body || '' }
    editorOpen.value = true
}

async function load() {
    loading.value = true
    error.value = null
    try {
        const data = await api.get('/api/notes')
        notes.value = data.notes
    } catch (e) {
        error.value = e.message
    } finally {
        loading.value = false
    }
}

async function save() {
    saving.value = true
    try {
        if (editing.value) {
            const data = await api.patch(`/api/notes/${editing.value.id}`, form.value)
            const i = notes.value.findIndex((n) => n.id === editing.value.id)
            if (i !== -1) notes.value[i] = data.note
        } else {
            const data = await api.post('/api/notes', form.value)
            notes.value.unshift(data.note)
        }
        editorOpen.value = false
        ui.toast('Note saved.', 'success')
    } catch (e) {
        ui.toast(e.message, 'error')
    } finally {
        saving.value = false
    }
}

async function remove(note) {
    if (!confirm('Delete this note?')) return
    try {
        await api.delete(`/api/notes/${note.id}`)
        notes.value = notes.value.filter((n) => n.id !== note.id)
        ui.toast('Note deleted.', 'info')
    } catch (e) {
        ui.toast(e.message, 'error')
    }
}

onMounted(load)
</script>
