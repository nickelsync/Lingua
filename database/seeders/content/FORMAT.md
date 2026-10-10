# Lingua seed content format

Each file lives in `database/seeders/content/` and MUST `return` a PHP array (no classes, no namespaces):

```php
<?php

return [
    'level' => 'a1', // one of: beginner, a1, a2, b1, b2, c1, c2
    'courses' => [
        [
            'skill' => 'grammar', // grammar|vocabulary|listening|speaking|reading|writing|pronunciation|everyday
            'title' => 'A1 Grammar Foundations',
            'description' => 'Short course description.',
            'modules' => [
                [
                    'title' => 'Present Simple',
                    'description' => 'Module description.',
                    'lessons' => [
                        [
                            'title' => 'Present Simple: Affirmative',
                            'objective' => 'Use the present simple to talk about habits and facts.',
                            'xp' => 20, 'minutes' => 10,
                            // OPTIONAL, resolves within same level by lesson title:
                            'prerequisites' => ['Some Earlier Lesson Title'],
                            'sections' => [
                                ['type' => 'intro', 'title' => 'Why this matters', 'body' => "Markdown-ish text. Use \n\n for paragraphs and - for lists."],
                                ['type' => 'explanation', 'title' => 'The rule', 'body' => '...', 'body_id' => 'Optional short Indonesian note.'],
                                ['type' => 'examples', 'title' => 'Examples', 'meta' => ['items' => [
                                    ['en' => 'She works in a hospital.', 'id' => 'Dia bekerja di rumah sakit.'],
                                ]]],
                                ['type' => 'vocabulary', 'title' => 'Key words', 'meta' => ['words' => [
                                    ['word' => 'always', 'ipa' => '/ˈɔl.weɪz/', 'meaning_id' => 'selalu', 'example' => 'I always drink coffee.'],
                                ]]],
                                ['type' => 'dialogue', 'title' => 'At the cafe', 'meta' => ['lines' => [
                                    ['speaker' => 'Anna', 'text' => 'Hi! What do you usually order?'],
                                    ['speaker' => 'Ben', 'text' => 'I usually get a latte.'],
                                ]]],
                                ['type' => 'listening', 'title' => 'Listen', 'body' => 'Listen and repeat.', 'meta' => ['audio_text' => 'I wake up at seven every morning.']],
                                ['type' => 'reading', 'title' => 'Short read', 'body' => 'Passage text here...'],
                                ['type' => 'note', 'title' => 'Common mistake (Indonesian learners)', 'body' => 'English requires -s on he/she/it verbs...', 'body_id' => 'Dalam bahasa Indonesia kata kerja tidak berubah...'],
                                ['type' => 'tip', 'title' => 'Tip', 'body' => 'Short practical tip.'],
                            ],
                            'exercises' => [
                                [
                                    'title' => 'Practice: Present Simple',
                                    'xp' => 10,
                                    'pick' => 0, // 0 = use all questions; N = random N per session
                                    'questions' => [
                                        // multiple_choice (also 'listening' with audio_text, 'reading' with passage):
                                        ['type' => 'multiple_choice', 'prompt' => 'She ___ in a bank.', 'difficulty' => 'easy',
                                         'explanation' => 'Third person singular takes -s.', 'explanation_id' => 'Kata kerja untuk he/she/it diberi akhiran -s.',
                                         'options' => [['works', true], ['work', false], ['working', false], ['is work', false]]],
                                        // true_false:
                                        ['type' => 'true_false', 'prompt' => '"He go to school" is correct English.', 'config' => ['correct' => false],
                                         'explanation' => 'Correct: He goes to school.'],
                                        // fill_blank / translation / typing (accepted = list of correct strings, case/punct insensitive):
                                        ['type' => 'fill_blank', 'prompt' => 'I ___ (drink) coffee every morning.', 'config' => ['accepted' => ['drink']]],
                                        ['type' => 'translation', 'prompt' => 'Translate to English: "Saya suka kopi."', 'config' => ['accepted' => ['I like coffee', 'I love coffee']]],
                                        // rearrange:
                                        ['type' => 'rearrange', 'prompt' => 'Put the words in order.', 'config' => ['order' => ['She', 'always', 'drinks', 'tea']]],
                                        // matching:
                                        ['type' => 'matching', 'prompt' => 'Match the word to its meaning.', 'config' => ['pairs' => [
                                            ['left' => 'always', 'right' => 'selalu'], ['left' => 'never', 'right' => 'tidak pernah'], ['left' => 'often', 'right' => 'sering']]]],
                                        // listening (multiple choice + audio_text played via TTS):
                                        ['type' => 'listening', 'prompt' => 'What does she do every morning?', 'audio_text' => 'I go running in the park every morning before work.',
                                         'options' => [['She goes running.', true], ['She goes swimming.', false], ['She works in a park.', false]]],
                                        // reading (multiple choice + passage):
                                        ['type' => 'reading', 'prompt' => 'What time does Maria start work?', 'passage' => 'Maria is a nurse. She starts work at 6 a.m. ...',
                                         'options' => [['6 a.m.', true], ['8 a.m.', false], ['noon', false]]],
                                        // speaking (scored by transcript similarity):
                                        ['type' => 'speaking', 'prompt' => 'Say this sentence aloud.', 'config' => ['target' => 'I would like a cup of coffee.']],
                                        // writing (self-review with model answer):
                                        ['type' => 'writing', 'prompt' => 'Write 3 sentences about your daily routine.',
                                         'config' => ['min_words' => 15, 'model_answer' => 'I wake up at six...', 'review_points' => ['Did you use present simple?', 'Did you add -s for he/she/it?']]],
                                    ],
                                ],
                            ],
                        ],
                    ],
                ],
            ],
        ],
    ],
    // Final-exam pool: distinct from practice questions. NO speaking/writing types here.
    'exam_pool' => [
        'title' => 'A1 Final Exam Pool',
        'questions' => [
            // same question format; add 'skill' => 'grammar'|'vocabulary'|'listening'|'reading' to each
        ],
    ],
];
```

Rules:
- Every `difficulty` is easy|medium|hard.
- All interface text English; `explanation_id`, `body_id`, `meaning_id` fields are Indonesian.
- American English spelling and usage.
- Content must be REAL teaching material, no lorem ipsum, pedagogically appropriate to the CEFR level.
- Each choice question: exactly ONE correct option, 3-4 options total.
