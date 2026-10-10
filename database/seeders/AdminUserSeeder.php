<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * Create the initial admin only when ADMIN_PASSWORD is provided,
 * so no environment ever ships with a known default credential.
 */
class AdminUserSeeder extends Seeder
{
    public function run(): void
    {
        $password = env('ADMIN_PASSWORD');

        if (! $password) {
            $this->command?->warn('Skipped admin user: set ADMIN_PASSWORD (and optionally ADMIN_USERNAME) then re-run `php artisan db:seed`.');

            return;
        }

        $username = env('ADMIN_USERNAME', 'admin');

        $admin = User::firstOrNew(['username' => $username]);
        $admin->forceFill([
            'display_name' => 'Administrator',
            'password' => Hash::make($password),
            'recovery_code' => $admin->exists ? $admin->recovery_code : Hash::make(Str::upper(Str::random(12))),
            'role' => 'admin',
            'timezone' => $admin->timezone ?? 'Asia/Jakarta',
            'onboarded_at' => $admin->onboarded_at ?? now(),
        ])->save();

        $this->command?->info("Admin user '{$username}' ready.");
    }
}
