<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\Concerns\BuildsCurriculum;
use Tests\TestCase;

class AdminAccessTest extends TestCase
{
    use BuildsCurriculum, RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seedLevels();
    }

    public function test_regular_users_cannot_reach_admin_api(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->getJson('/api/admin/overview')->assertForbidden();
        $this->actingAs($user)->getJson('/api/admin/users')->assertForbidden();
        $this->actingAs($user)->postJson('/api/admin/courses', [])->assertForbidden();
    }

    public function test_admin_can_reach_admin_api(): void
    {
        $admin = User::factory()->admin()->create();

        $this->actingAs($admin)->getJson('/api/admin/overview')->assertOk();
        $this->actingAs($admin)->getJson('/api/admin/users')->assertOk()
            ->assertJsonStructure(['users' => ['data']]);
    }

    public function test_admin_can_suspend_and_unsuspend_users_but_not_self(): void
    {
        $admin = User::factory()->admin()->create();
        $victim = User::factory()->create();

        $this->actingAs($admin)->postJson("/api/admin/users/{$victim->id}/suspend")->assertOk();
        $this->assertNotNull($victim->refresh()->suspended_at);

        // Suspend endpoint is a toggle.
        $this->actingAs($admin)->postJson("/api/admin/users/{$victim->id}/suspend")->assertOk();
        $this->assertNull($victim->refresh()->suspended_at);

        $this->actingAs($admin)->postJson("/api/admin/users/{$admin->id}/suspend")->assertUnprocessable();
    }

    public function test_role_cannot_be_mass_assigned_via_profile(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->patchJson('/api/profile', [
            'display_name' => 'Hacker', 'role' => 'admin',
        ])->assertOk();

        $this->assertSame('user', $user->refresh()->role);
    }

    public function test_admin_can_regenerate_a_users_recovery_code(): void
    {
        $admin = User::factory()->admin()->create();
        $user = User::factory()->create();
        $oldHash = $user->recovery_code;

        $res = $this->actingAs($admin)->postJson("/api/admin/users/{$user->id}/recovery-code")
            ->assertOk()
            ->assertJsonStructure(['recovery_code', 'message']);

        $code = $res->json('recovery_code');
        $this->assertMatchesRegularExpression('/^[A-Z0-9]{4}-[A-Z0-9]{4}-[A-Z0-9]{4}$/', $code);
        $this->assertNotSame($oldHash, $user->refresh()->recovery_code);
        $this->assertTrue(Hash::check($code, $user->recovery_code));

        // Regular users cannot hit the endpoint.
        $this->actingAs($user)->postJson("/api/admin/users/{$admin->id}/recovery-code")->assertForbidden();
    }
}
