<?php

// Validates database/seeders/content/*.php against the FORMAT.md contract.
$targets = $argv;
array_shift($targets);
if (! $targets) {
    $targets = ['beginner', 'a1', 'a2', 'b1', 'b2', 'c1', 'c2'];
}
$examMin = ['beginner' => 20, 'a1' => 30, 'a2' => 30, 'b1' => 35, 'b2' => 35, 'c1' => 40, 'c2' => 40];
$errors = [];

function checkQuestion(array $q, string $ctx, array &$errors): void
{
    $type = $q['type'] ?? '';
    if (! $type) {
        $errors[] = "$ctx: missing type";

        return;
    }
    if (empty($q['prompt'])) {
        $errors[] = "$ctx: missing prompt";
    }
    if (preg_match('/^Q\d+\??$/', $q['prompt'] ?? '')) {
        $errors[] = "$ctx: placeholder prompt '{$q['prompt']}'";
    }
    if (mb_strlen(trim($q['prompt'] ?? '')) < 10) {
        $errors[] = "$ctx: prompt too short/low-quality '{$q['prompt']}'";
    }
    if (in_array($type, ['multiple_choice', 'listening', 'reading'], true)) {
        $opts = $q['options'] ?? [];
        $correct = count(array_filter($opts, fn ($o) => ($o[1] ?? false) === true));
        if (count($opts) < 2 || $correct !== 1) {
            $errors[] = "$ctx: needs >=2 options, exactly 1 correct (got ".count($opts)." opts, $correct correct)";
        }
        $texts = array_map(fn ($o) => strtolower(trim((string) ($o[0] ?? ''))), $opts);
        sort($texts);
        if ($texts === ['no', 'yes']) {
            $errors[] = "$ctx: generic Yes/No options are not acceptable for choice questions";
        }
        foreach ($opts as $o) {
            if (mb_strlen(trim((string) ($o[0] ?? ''))) < 1) {
                $errors[] = "$ctx: empty option text";
            }
        }
        if ($type === 'listening' && empty($q['audio_text'])) {
            $errors[] = "$ctx: listening needs audio_text";
        }
        if ($type === 'reading' && empty($q['passage'])) {
            $errors[] = "$ctx: reading needs passage";
        }
    } elseif (in_array($type, ['fill_blank', 'translation', 'typing'], true)) {
        if (empty($q['config']['accepted'])) {
            $errors[] = "$ctx: needs config.accepted";
        }
    } elseif ($type === 'true_false') {
        if (! array_key_exists('correct', $q['config'] ?? [])) {
            $errors[] = "$ctx: needs config.correct";
        }
    } elseif ($type === 'rearrange') {
        if (count($q['config']['order'] ?? []) < 2) {
            $errors[] = "$ctx: needs config.order with >=2 tokens";
        }
    } elseif ($type === 'matching') {
        if (count($q['config']['pairs'] ?? []) < 2) {
            $errors[] = "$ctx: needs config.pairs with >=2 pairs";
        }
    } elseif ($type === 'speaking') {
        if (empty($q['config']['target'])) {
            $errors[] = "$ctx: speaking needs config.target";
        }
    } elseif ($type === 'writing') {
        if (empty($q['config']['min_words'])) {
            $errors[] = "$ctx: writing needs config.min_words";
        }
    } else {
        $errors[] = "$ctx: unknown type '$type'";
    }
}

foreach ($targets as $lv) {
    $file = __DIR__."/../database/seeders/content/$lv.php";
    if (! is_file($file)) {
        $errors[] = "$lv: file missing";

        continue;
    }
    $d = include $file;
    if (($d['level'] ?? '') !== $lv) {
        $errors[] = "$lv: level key mismatch";
    }
    $lessons = 0;
    foreach (($d['courses'] ?? []) as $ci => $c) {
        if (empty($c['title'])) {
            $errors[] = "$lv course#$ci: missing title";

            continue;
        }
        if (empty($c['skill'])) {
            $errors[] = "$lv course#$ci: missing skill";
        }
        if (empty($c['modules'])) {
            $errors[] = "$lv course '{$c['title']}': no modules";
        }
        foreach (($c['modules'] ?? []) as $mi => $m) {
            if (empty($m['title'])) {
                $errors[] = "$lv course '{$c['title']}' module#$mi: missing title";

                continue;
            }
            if (empty($m['lessons'])) {
                $errors[] = "$lv module '{$m['title']}': no lessons";
            }
            foreach (($m['lessons'] ?? []) as $li => $l) {
                if (empty($l['title'])) {
                    $errors[] = "$lv module '{$m['title']}' lesson#$li: missing title";

                    continue;
                }
                $lessons++;
                if (count($l['sections'] ?? []) < 3) {
                    $errors[] = "$lv lesson '{$l['title']}': needs >=3 sections (got ".count($l['sections'] ?? []).')';
                }
                if (empty($l['exercises'])) {
                    $errors[] = "$lv lesson '{$l['title']}': needs >=1 exercise";
                }
                foreach (($l['exercises'] ?? []) as $e) {
                    if (count($e['questions'] ?? []) < 3) {
                        $errors[] = "$lv lesson '{$l['title']}' exercise '{$e['title']}': needs >=3 questions";
                    }
                    foreach (($e['questions'] ?? []) as $qi => $q) {
                        checkQuestion($q, "$lv/{$l['title']}/q#$qi", $errors);
                    }
                }
            }
        }
    }
    if ($lessons < 4) {
        $errors[] = "$lv: only $lessons lessons, need >=4";
    }
    $pool = $d['exam_pool']['questions'] ?? [];
    if (count($pool) < $examMin[$lv]) {
        $errors[] = "$lv exam pool: ".count($pool).' questions, need >='.$examMin[$lv];
    }
    foreach ($pool as $qi => $q) {
        checkQuestion($q, "$lv/exam#$qi", $errors);
    }
    // duplicate prompt detection in exam pool
    // strip trailing "(N)" so numbered copies of one template still count as duplicates
    $prompts = array_count_values(array_map(
        fn ($q) => strtolower(trim(preg_replace('/\s*\(\d+\)\s*$/', '', $q['prompt'] ?? ''))),
        $pool
    ));
    foreach ($prompts as $p => $n) {
        if ($n > 1 && $p !== '') {
            $errors[] = "$lv exam pool: duplicate prompt '$p' x$n";
        }
    }
}

if ($errors) {
    echo implode("\n", $errors)."\n".count($errors)." ERROR(S)\n";
    exit(1);
}
echo "content OK\n";
