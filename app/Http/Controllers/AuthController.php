<?php

namespace App\Http\Controllers;

use App\Models\CefrLevel;
use App\Models\LevelUnlock;
use App\Models\User;
use App\Services\StreakService;
use Illuminate\Cache\RateLimiter;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Illuminate\Validation\Rules\Password;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function register(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string', 'min:3', 'max:30', 'regex:/^[a-zA-Z0-9_]+$/', 'unique:users,username'],
            'display_name' => ['nullable', 'string', 'max:60'],
            'password' => ['required', 'confirmed', Password::min(8)],
            'timezone' => ['nullable', 'timezone'],
        ]);

        // Username-only accounts: a one-time recovery code replaces email reset.
        $recoveryCode = Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));

        $beginner = CefrLevel::where('code', 'beginner')->first();

        $user = User::create([
            'username' => $data['username'],
            'display_name' => $data['display_name'] ?? $data['username'],
            'password' => $data['password'],
            'timezone' => $data['timezone'] ?? 'UTC',
        ]);

        $user->forceFill([
            'recovery_code' => Hash::make($recoveryCode),
            'current_level_id' => $beginner?->id,
        ])->save();

        if ($beginner) {
            LevelUnlock::create([
                'user_id' => $user->id,
                'cefr_level_id' => $beginner->id,
                'source' => 'initial',
                'unlocked_at' => now(),
            ]);
        }

        Auth::login($user, remember: true);
        $request->session()->regenerate();

        return response()->json([
            'user' => $this->userPayload($user),
            'recovery_code' => $recoveryCode,
        ], 201);
    }

    public function login(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'password' => ['required', 'string'],
            'remember' => ['boolean'],
        ]);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! Hash::check($data['password'], $user->password)) {
            throw ValidationException::withMessages([
                'username' => 'These credentials do not match our records.',
            ]);
        }

        if ($user->suspended_at !== null) {
            throw ValidationException::withMessages([
                'username' => 'This account has been suspended.',
            ]);
        }

        Auth::login($user, remember: $data['remember'] ?? false);
        $request->session()->regenerate();

        return response()->json(['user' => $this->userPayload($user)]);
    }

    public function logout(Request $request): JsonResponse
    {
        Auth::guard('web')->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json(['message' => 'Logged out.']);
    }

    public function me(Request $request): JsonResponse
    {
        return response()->json(['user' => $this->userPayload($request->user())]);
    }

    /** Reset a forgotten password using username + recovery code. */
    public function recover(Request $request): JsonResponse
    {
        $data = $request->validate([
            'username' => ['required', 'string'],
            'recovery_code' => ['required', 'string'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $key = 'recover:'.$request->ip();
        if (app(RateLimiter::class)->tooManyAttempts($key, 5)) {
            abort(429, 'Too many recovery attempts. Try again later.');
        }
        app(RateLimiter::class)->hit($key, 300);

        $user = User::where('username', $data['username'])->first();

        if (! $user || ! $user->recovery_code || ! Hash::check(strtoupper(trim($data['recovery_code'])), $user->recovery_code)) {
            throw ValidationException::withMessages([
                'recovery_code' => 'Invalid username or recovery code.',
            ]);
        }

        // Rotate: issue a fresh recovery code with the new password.
        $newCode = Str::upper(Str::random(4).'-'.Str::random(4).'-'.Str::random(4));
        $user->forceFill([
            'password' => Hash::make($data['password']),
            'recovery_code' => Hash::make($newCode),
        ])->save();

        return response()->json([
            'message' => 'Password reset. Save your new recovery code.',
            'recovery_code' => $newCode,
        ]);
    }

    public function changePassword(Request $request): JsonResponse
    {
        $data = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::min(8)],
        ]);

        $request->user()->update(['password' => $data['password']]);

        return response()->json(['message' => 'Password updated.']);
    }

    public static function userPayload(User $user): array
    {
        return [
            'id' => $user->id,
            'username' => $user->username,
            'display_name' => $user->display_name,
            'role' => $user->role,
            'timezone' => $user->timezone,
            'avatar_url' => $user->avatar_path ? '/storage/'.$user->avatar_path : null,
            'is_public' => $user->is_public,
            'onboarded' => $user->onboarded_at !== null,
            'placement_done' => $user->placement_completed_at !== null,
            'current_level' => $user->currentLevel?->only(['id', 'code', 'name']),
            'xp_total' => $user->xp_total,
            'account_level' => $user->accountLevel(),
            'streak' => app(StreakService::class)->effectiveStreak($user),
            'learning_goal' => $user->learning_goal,
            'focus_skill' => $user->focus_skill,
            'daily_goal_minutes' => $user->daily_goal_minutes,
            'created_at' => $user->created_at?->toDateString(),
        ];
    }
}
