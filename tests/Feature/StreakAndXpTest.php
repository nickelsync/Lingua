<?php

namespace Tests\Feature;

use App\Models\Setting;
use App\Models\User;
use App\Services\StreakService;
use App\Services\XpService;
use Database\Seeders\AchievementSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class StreakAndXpTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLevels();
    }

    public function test_streak_counts_consecutive_local_days(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $streaks = app(StreakService::class);

        Carbon::setTestNow('2026-03-01 10:00:00');
        $streaks->recordActivity($user);
        $this->assertSame(1, $user->streak_current);

        // Same day: no double count.
        $streaks->recordActivity($user);
        $this->assertSame(1, $user->streak_current);

        Carbon::setTestNow('2026-03-02 10:00:00');
        $streaks->recordActivity($user->refresh());
        $this->assertSame(2, $user->streak_current);

        // Missing a day resets to 1.
        Carbon::setTestNow('2026-03-04 10:00:00');
        $streaks->recordActivity($user->refresh());
        $this->assertSame(1, $user->streak_current);
        $this->assertSame(2, $user->streak_longest);

        Carbon::setTestNow();
    }

    public function test_effective_streak_decays_without_saving(): void
    {
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $streaks = app(StreakService::class);

        Carbon::setTestNow('2026-03-01 10:00:00');
        $streaks->recordActivity($user);

        Carbon::setTestNow('2026-03-02 10:00:00');
        $this->assertSame(1, $streaks->effectiveStreak($user->refresh()));

        Carbon::setTestNow('2026-03-05 10:00:00');
        $this->assertSame(0, $streaks->effectiveStreak($user->refresh()));

        Carbon::setTestNow();
    }

    public function test_streak_respects_user_timezone_boundary(): void
    {
        // 23:30 Jakarta on Mar 1 is 16:30 UTC; next morning Jakarta is still
        // consecutive even though only hours passed in UTC.
        $user = User::factory()->create(['timezone' => 'Asia/Jakarta']);
        $streaks = app(StreakService::class);

        Carbon::setTestNow('2026-03-01 16:30:00'); // Jakarta: Mar 1, 23:30
        $streaks->recordActivity($user);
        Carbon::setTestNow('2026-03-02 00:30:00'); // Jakarta: Mar 2, 07:30
        $streaks->recordActivity($user->refresh());

        $this->assertSame(2, $user->streak_current);
        Carbon::setTestNow();
    }

    public function test_xp_repeat_grants_capped_per_day(): void
    {
        $user = User::factory()->create();
        $xp = app(XpService::class);

        $this->assertSame(50, $xp->award($user, 50, 'lesson', 'lesson:1', 'Test'));
        // Repeats: 10% each, at most 3 per day.
        $this->assertSame(5, $xp->award($user, 50, 'lesson', 'lesson:1', 'Test'));
        $this->assertSame(5, $xp->award($user, 50, 'lesson', 'lesson:1', 'Test'));
        $this->assertSame(5, $xp->award($user, 50, 'lesson', 'lesson:1', 'Test'));
        $this->assertSame(0, $xp->award($user, 50, 'lesson', 'lesson:1', 'Test'));

        $this->assertSame(65, $user->refresh()->xp_total);
    }

    public function test_repeat_xp_percent_setting_changes_repeat_grants(): void
    {
        Setting::set('repeat_xp_percent', 50);

        $user = User::factory()->create();
        $xp = app(XpService::class);

        $this->assertSame(40, $xp->award($user, 40, 'lesson', 'lesson:9', 'Test'));
        $this->assertSame(20, $xp->award($user, 40, 'lesson', 'lesson:9', 'Test'));
    }

    public function test_admin_settings_expose_defaults_and_persist_changes(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/settings')
            ->assertOk()
            ->assertJsonPath('settings.xp_vocab_review', '2');

        $this->actingAs($admin)->patchJson('/api/admin/settings', [
            'settings' => ['xp_vocab_review' => '7', 'not_a_real_key' => '99'],
        ])->assertOk()->assertJsonPath('settings.xp_vocab_review', '7');

        $this->assertDatabaseHas('settings', ['key' => 'xp_vocab_review', 'value' => '7']);
        $this->assertDatabaseMissing('settings', ['key' => 'not_a_real_key']);
    }

    public function test_achievements_unlock_on_thresholds(): void
    {
        $this->seed(AchievementSeeder::class);
        $user = User::factory()->create();
        $this->unlock($user, 'beginner');

        $lesson = $this->makeLesson('beginner');
        $this->actingAs($user)->postJson("/api/lessons/{$lesson->id}/complete")->assertOk();

        // "first lesson" achievement exists and is granted.
        $this->assertDatabaseHas('user_achievements', ['user_id' => $user->id]);
        $this->assertDatabaseHas('notifications', ['user_id' => $user->id, 'type' => 'achievement']);
    }
}
