<?php

namespace App\Services;

class SpeakingScorer
{
    /**
     * Compare a spoken transcript to a target sentence at the word level.
     * Honest about scope: this measures word-level transcript similarity,
     * not phonetic quality.
     *
     * @return array{accuracy:int, missing:array, extra:array, target_words:array, spoken_words:array}
     */
    public function score(string $target, string $transcript): array
    {
        $targetWords = $this->words($target);
        $spokenWords = $this->words($transcript);

        if (count($targetWords) === 0) {
            return ['accuracy' => 0, 'missing' => [], 'extra' => $spokenWords, 'target_words' => [], 'spoken_words' => $spokenWords];
        }

        // LCS-based alignment: matched words in order.
        $matched = $this->lcs($targetWords, $spokenWords);
        $matchedSet = [];
        foreach ($matched as $w) {
            $matchedSet[$w] = ($matchedSet[$w] ?? 0) + 1;
        }

        $missing = [];
        $counts = $matchedSet;
        foreach ($targetWords as $w) {
            if (($counts[$w] ?? 0) > 0) {
                $counts[$w]--;
            } else {
                $missing[] = $w;
            }
        }

        $extra = [];
        $counts = $matchedSet;
        foreach ($spokenWords as $w) {
            if (($counts[$w] ?? 0) > 0) {
                $counts[$w]--;
            } else {
                $extra[] = $w;
            }
        }

        $accuracy = (int) round(count($matched) / count($targetWords) * 100);

        return [
            'accuracy' => min(100, $accuracy),
            'missing' => $missing,
            'extra' => $extra,
            'target_words' => $targetWords,
            'spoken_words' => $spokenWords,
        ];
    }

    /** @return string[] */
    private function words(string $text): array
    {
        $text = mb_strtolower(trim($text));
        $text = preg_replace('/[^\p{L}\p{N}\s\']/u', '', $text);

        return array_values(array_filter(preg_split('/\s+/', $text)));
    }

    /** Longest common subsequence of two word arrays. */
    private function lcs(array $a, array $b): array
    {
        $m = count($a);
        $n = count($b);
        $dp = array_fill(0, $m + 1, array_fill(0, $n + 1, 0));

        for ($i = 1; $i <= $m; $i++) {
            for ($j = 1; $j <= $n; $j++) {
                $dp[$i][$j] = $a[$i - 1] === $b[$j - 1]
                    ? $dp[$i - 1][$j - 1] + 1
                    : max($dp[$i - 1][$j], $dp[$i][$j - 1]);
            }
        }

        $result = [];
        $i = $m;
        $j = $n;
        while ($i > 0 && $j > 0) {
            if ($a[$i - 1] === $b[$j - 1]) {
                array_unshift($result, $a[$i - 1]);
                $i--;
                $j--;
            } elseif ($dp[$i - 1][$j] >= $dp[$i][$j - 1]) {
                $i--;
            } else {
                $j--;
            }
        }

        return $result;
    }
}
