<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class AuthTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLevels();
    }

    public function test_registration_returns_recovery_code_and_starts_at_beginner(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'username' => 'sari_belajar',
            'display_name' => 'Sari',
            'password' => 'secret-pass-1',
            'password_confirmation' => 'secret-pass-1',
            'timezone' => 'Asia/Jakarta',
        ]);

        $res->assertCreated()
            ->assertJsonPath('user.username', 'sari_belajar')
            ->assertJsonStructure(['recovery_code']);

        $user = User::where('username', 'sari_belajar')->first();
        $this->assertSame('beginner', $user->currentLevel->code);
        $this->assertSame('user', $user->role);
        $this->assertDatabaseHas('level_unlocks', ['user_id' => $user->id, 'cefr_level_id' => $this->level('beginner')->id]);
        $this->assertAuthenticatedAs($user);
    }

    public function test_registration_rejects_invalid_usernames_and_duplicates(): void
    {
        $this->postJson('/api/auth/register', [
            'username' => 'bad name!', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');

        User::factory()->create(['username' => 'taken']);
        $this->postJson('/api/auth/register', [
            'username' => 'taken', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ])->assertUnprocessable()->assertJsonValidationErrors('username');
    }

    public function test_login_and_logout(): void
    {
        $user = User::factory()->create(['username' => 'budi99']);

        $this->postJson('/api/auth/login', ['username' => 'budi99', 'password' => 'wrong'])
            ->assertUnprocessable();

        $this->postJson('/api/auth/login', ['username' => 'budi99', 'password' => 'password'])
            ->assertOk()->assertJsonPath('user.username', 'budi99');

        $this->postJson('/api/auth/logout')->assertOk();
        $this->assertGuest();
    }

    public function test_suspended_user_cannot_login_or_use_api(): void
    {
        $user = User::factory()->suspended()->create(['username' => 'blocked1']);

        $this->postJson('/api/auth/login', ['username' => 'blocked1', 'password' => 'password'])
            ->assertUnprocessable();

        $this->actingAs($user)->getJson('/api/dashboard')->assertForbidden();
    }

    public function test_recovery_code_resets_password_once(): void
    {
        $res = $this->postJson('/api/auth/register', [
            'username' => 'forgetful', 'password' => 'secret-pass-1', 'password_confirmation' => 'secret-pass-1',
        ]);
        $code = $res->json('recovery_code');
        $this->postJson('/api/auth/logout');

        $recover = $this->postJson('/api/auth/recover', [
            'username' => 'forgetful', 'recovery_code' => $code,
            'password' => 'brand-new-pass2', 'password_confirmation' => 'brand-new-pass2',
        ]);
        $recover->assertOk()->assertJsonStructure(['recovery_code']);
        $newCode = $recover->json('recovery_code');
        $this->assertNotSame($code, $newCode);

        // Old code no longer works; new password does.
        $this->postJson('/api/auth/recover', [
            'username' => 'forgetful', 'recovery_code' => $code,
            'password' => 'x-another-pass3', 'password_confirmation' => 'x-another-pass3',
        ])->assertUnprocessable();

        $this->postJson('/api/auth/login', ['username' => 'forgetful', 'password' => 'brand-new-pass2'])->assertOk();
    }

    public function test_change_password_requires_current_password(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'nope', 'password' => 'new-secret-99', 'password_confirmation' => 'new-secret-99',
        ])->assertUnprocessable();

        $this->actingAs($user)->postJson('/api/auth/change-password', [
            'current_password' => 'password', 'password' => 'new-secret-99', 'password_confirmation' => 'new-secret-99',
        ])->assertOk();
    }

    public function test_guests_cannot_reach_authenticated_api(): void
    {
        $this->getJson('/api/dashboard')->assertUnauthorized();
        $this->getJson('/api/levels')->assertUnauthorized();
    }
}
