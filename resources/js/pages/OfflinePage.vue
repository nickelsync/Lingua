<template>
    <div class="flex min-h-screen flex-col items-center justify-center gap-4 px-4 text-center">
        <div class="flex h-14 w-14 items-center justify-center rounded-full border border-ink-700 bg-ink-800 text-2xl">⚡</div>
        <h1 class="text-2xl font-bold text-ink-100">You're offline</h1>
        <p class="max-w-sm text-ink-400">This page isn't cached yet. Lessons and vocabulary you've already opened remain available offline. Reconnect to load new content.</p>
        <button class="btn-primary" @click="reload">Try again</button>
    </div>
</template>

<script setup>
function reload() { window.location.reload() }
</script>
