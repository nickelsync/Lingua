<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="theme-color" content="#0a0a0b">
    <meta name="description" content="Lingua — a free, structured path from Beginner to English Mastery. CEFR-aligned lessons, real exercises, vocabulary training, and level exams.">
    <title>Lingua — Learn English from Beginner to Mastery</title>
    <link rel="manifest" href="/manifest.webmanifest">
    <link rel="icon" href="/icons/icon-192.png" type="image/png">
    @vite(['resources/css/app.css'])
</head>
<body class="min-h-screen">
    <header class="border-b border-ink-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-4">
            <a href="/" class="flex items-center gap-2 text-lg font-semibold text-ink-100">
                <span class="flex h-8 w-8 items-center justify-center rounded-md bg-accent-600 font-bold text-white">L</span>
                Lingua
            </a>
            <nav class="flex items-center gap-3">
                <a href="/login" class="btn-ghost">Log in</a>
                <a href="/register" class="btn-primary">Get started</a>
            </nav>
        </div>
    </header>

    <main>
        <section class="mx-auto max-w-6xl px-6 py-20 text-center">
            <p class="mb-4 text-sm font-medium uppercase tracking-widest text-accent-400">Beginner → A1 → A2 → B1 → B2 → C1 → C2</p>
            <h1 class="mx-auto max-w-3xl text-4xl font-bold leading-tight text-ink-100 md:text-6xl">
                Learn English the structured way.
            </h1>
            <p class="mx-auto mt-6 max-w-2xl text-lg text-ink-400">
                A complete CEFR-aligned curriculum: real lessons, interactive exercises, vocabulary training with spaced repetition,
                speaking &amp; listening practice, and level exams that unlock your path to mastery.
            </p>
            <div class="mt-10 flex items-center justify-center gap-4">
                <a href="/register" class="btn-primary px-8 py-3 text-base">Start learning free</a>
                <a href="/login" class="btn-secondary px-8 py-3 text-base">I have an account</a>
            </div>
            <p class="mt-6 text-sm text-ink-400">No email required. Free forever. Works offline.</p>
        </section>

        <section class="border-t border-ink-800 bg-ink-900/40">
            <div class="mx-auto grid max-w-6xl gap-8 px-6 py-16 md:grid-cols-3">
                <div class="card p-6">
                    <h3 class="mb-2 font-semibold text-ink-100">Structured curriculum</h3>
                    <p class="text-sm text-ink-400">Seven CEFR stages with grammar, vocabulary, reading, listening, speaking, and writing lessons. Pass each level exam to unlock the next.</p>
                </div>
                <div class="card p-6">
                    <h3 class="mb-2 font-semibold text-ink-100">Practice that counts</h3>
                    <p class="text-sm text-ink-400">Ten exercise types with instant feedback and clear explanations — including Indonesian notes for tricky grammar.</p>
                </div>
                <div class="card p-6">
                    <h3 class="mb-2 font-semibold text-ink-100">Vocabulary that sticks</h3>
                    <p class="text-sm text-ink-400">Save words to your personal deck and review them with flashcards scheduled by spaced repetition.</p>
                </div>
                <div class="card p-6">
                    <h3 class="mb-2 font-semibold text-ink-100">Speak and listen</h3>
                    <p class="text-sm text-ink-400">Practice pronunciation with your browser's speech recognition and natural text-to-speech audio. No paid services.</p>
                </div>
                <div class="card p-6">
                    <h3 class="mb-2 font-semibold text-ink-100">Track your progress</h3>
                    <p class="text-sm text-ink-400">XP, daily streaks, skill breakdowns, achievements, and a full learning history keep you motivated.</p>
                </div>
                <div class="card p-6">
                    <h3 class="mb-2 font-semibold text-ink-100">Find your level</h3>
                    <p class="text-sm text-ink-400">Take the placement assessment to start at the right CEFR level, or begin from zero — Beginner is a real stage here.</p>
                </div>
            </div>
        </section>

        <section class="mx-auto max-w-6xl px-6 py-20 text-center">
            <h2 class="text-3xl font-bold text-ink-100">Ready to reach English Mastery?</h2>
            <p class="mx-auto mt-4 max-w-xl text-ink-400">Create a free account with just a username and start your first lesson in under a minute.</p>
            <a href="/register" class="btn-primary mt-8 px-8 py-3 text-base">Create free account</a>
        </section>
    </main>

    <footer class="border-t border-ink-800">
        <div class="mx-auto flex max-w-6xl items-center justify-between px-6 py-6 text-sm text-ink-400">
            <span>Lingua — Learn English</span>
            <span>Free · No ads · Works offline</span>
        </div>
    </footer>
</body>
</html>
