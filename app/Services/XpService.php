<?php

namespace App\Services;

use App\Models\LearningActivity;
use App\Models\Setting;
use App\Models\User;
use App\Models\XpTransaction;

class XpService
{
    /**
     * Award XP with anti-farming: a given (source, reference) pair only grants
     * full XP once; repeats grant a small fraction, capped per day.
     */
    public function award(User $user, int $amount, string $source, ?string $reference = null, ?string $subject = null): int
    {
        if ($amount <= 0) {
            return 0;
        }

        if ($reference !== null) {
            $already = XpTransaction::where('user_id', $user->id)
                ->where('source', $source)
                ->where('reference', $reference)
                ->exists();

            if ($already) {
                // Repeat practice: 10% XP, max 3 repeat grants per activity per day.
                $todayRepeats = XpTransaction::where('user_id', $user->id)
                    ->where('source', $source)
                    ->where('reference', $reference)
                    ->whereDate('created_at', now())
                    ->count();

                if ($todayRepeats > 3) {
                    return 0;
                }
                $percent = (int) Setting::get('repeat_xp_percent', 10);
                $amount = max(1, (int) round($amount * $percent / 100));
            }
        }

        XpTransaction::create([
            'user_id' => $user->id,
            'amount' => $amount,
            'source' => $source,
            'reference' => $reference,
        ]);

        $user->increment('xp_total', $amount);

        $localDate = now($user->timezone ?: 'UTC')->toDateString();
        LearningActivity::create([
            'user_id' => $user->id,
            'type' => $source,
            'subject' => $subject,
            'xp' => $amount,
            'local_date' => $localDate,
        ]);

        app(StreakService::class)->recordActivity($user);
        app(AchievementService::class)->checkAll($user);

        return $amount;
    }
}
