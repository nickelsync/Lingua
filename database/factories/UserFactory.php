<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    protected static ?string $password;

    public function definition(): array
    {
        return [
            'username' => fake()->unique()->regexify('[a-z]{6,10}[0-9]{2}'),
            'display_name' => fake()->firstName(),
            'password' => static::$password ??= Hash::make('password'),
            'recovery_code' => Hash::make(Str::upper(Str::random(12))),
            'role' => 'user',
            'timezone' => 'Asia/Jakarta',
            'onboarded_at' => now(),
            'remember_token' => Str::random(10),
        ];
    }

    public function admin(): static
    {
        return $this->state(fn () => ['role' => 'admin']);
    }

    public function suspended(): static
    {
        return $this->state(fn () => ['suspended_at' => now()]);
    }

    public function fresh(): static
    {
        return $this->state(fn () => ['onboarded_at' => null]);
    }
}
