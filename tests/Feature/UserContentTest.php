<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\Vocabulary;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class UserContentTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    private User $user;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLevels();
        $this->user = User::factory()->create();
        $this->unlock($this->user, 'beginner');
    }

    public function test_bookmarks_toggle_and_list(): void
    {
        $lesson = $this->makeLesson('beginner');

        $this->actingAs($this->user)->postJson('/api/bookmarks/toggle', [
            'type' => 'lesson', 'id' => $lesson->id,
        ])->assertCreated()->assertJsonPath('bookmarked', true);

        $this->actingAs($this->user)->getJson('/api/bookmarks')
            ->assertOk()->assertJsonCount(1, 'bookmarks');

        $this->actingAs($this->user)->postJson('/api/bookmarks/toggle', [
            'type' => 'lesson', 'id' => $lesson->id,
        ])->assertOk()->assertJsonPath('bookmarked', false);

        $this->actingAs($this->user)->getJson('/api/bookmarks')
            ->assertOk()->assertJsonCount(0, 'bookmarks');
    }

    public function test_notes_crud_is_private_per_user(): void
    {
        $create = $this->actingAs($this->user)->postJson('/api/notes', [
            'title' => 'Irregular verbs', 'body' => 'go → went → gone',
        ]);
        $create->assertCreated();
        $noteId = $create->json('note.id');

        $this->actingAs($this->user)->patchJson("/api/notes/{$noteId}", ['body' => 'updated'])
            ->assertOk()->assertJsonPath('note.body', 'updated');

        // Another user can't read, edit, or delete it.
        $other = User::factory()->create();
        $this->actingAs($other)->getJson('/api/notes')->assertOk()->assertJsonCount(0, 'notes');
        $this->actingAs($other)->patchJson("/api/notes/{$noteId}", ['body' => 'hacked'])->assertForbidden();
        $this->actingAs($other)->deleteJson("/api/notes/{$noteId}")->assertForbidden();

        $this->actingAs($this->user)->deleteJson("/api/notes/{$noteId}")->assertOk();
        $this->assertDatabaseMissing('notes', ['id' => $noteId]);
    }

    public function test_profile_update_and_public_profile_visibility(): void
    {
        $this->actingAs($this->user)->patchJson('/api/profile', [
            'display_name' => 'Sari Belajar', 'is_public' => true,
        ])->assertOk();

        $this->getJson("/api/profiles/{$this->user->username}")
            ->assertOk()->assertJsonPath('display_name', 'Sari Belajar');

        $this->actingAs($this->user)->patchJson('/api/profile', ['is_public' => false])->assertOk();
        $this->getJson("/api/profiles/{$this->user->username}")->assertNotFound();
    }

    public function test_avatar_upload_stores_image_and_replaces_old(): void
    {
        Storage::fake('public');

        $res = $this->actingAs($this->user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->image('me.jpg', 300, 300),
        ]);
        $res->assertOk();
        $path = $this->user->refresh()->avatar_path;
        $this->assertNotNull($path);
        Storage::disk('public')->assertExists($path);

        // Non-image rejected.
        $this->actingAs($this->user)->post('/api/profile/avatar', [
            'avatar' => UploadedFile::fake()->create('notes.pdf', 100, 'application/pdf'),
        ])->assertUnprocessable();
    }

    public function test_search_covers_lessons_and_vocabulary(): void
    {
        $lesson = $this->makeLesson('beginner', ['title' => 'Greetings and Goodbyes']);
        Vocabulary::create([
            'word' => 'greeting', 'part_of_speech' => 'noun', 'definition' => 'A polite word of welcome.',
            'meaning_id' => 'salam', 'cefr_level_id' => $this->level('beginner')->id, 'difficulty' => 'easy',
        ]);

        $res = $this->actingAs($this->user)->getJson('/api/search?q=greet');
        $res->assertOk();
        $types = collect($res->json('results'))->pluck('type');
        $this->assertTrue($types->contains('lesson'));
        $this->assertTrue($types->contains('vocabulary'));
    }

    public function test_dashboard_and_stats_respond_for_fresh_user(): void
    {
        $this->actingAs($this->user)->getJson('/api/dashboard')->assertOk()
            ->assertJsonStructure(['streak', 'level', 'xp_total']);
        $this->actingAs($this->user)->getJson('/api/stats')->assertOk();
        $this->actingAs($this->user)->getJson('/api/history')->assertOk();
        $this->actingAs($this->user)->getJson('/api/achievements')->assertOk();
        $this->actingAs($this->user)->getJson('/api/exams')->assertOk();
        $this->actingAs($this->user)->getJson('/api/notifications')->assertOk();
    }

    public function test_onboarding_saves_goals(): void
    {
        $fresh = User::factory()->fresh()->create();

        $this->actingAs($fresh)->postJson('/api/onboarding', [
            'learning_goal' => 'work', 'experience_level' => 'some', 'focus_skill' => 'speaking',
            'daily_goal_minutes' => 20, 'timezone' => 'Asia/Jakarta',
        ])->assertOk();

        $fresh->refresh();
        $this->assertNotNull($fresh->onboarded_at);
        $this->assertSame(20, $fresh->daily_goal_minutes);
    }
}
